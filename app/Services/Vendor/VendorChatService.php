<?php

declare(strict_types=1);

namespace App\Services\Vendor;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Vendor-side persistence for customer conversations.
 *
 * Every read is proven against the shop: a vendor may only open a thread where
 * it is a participant, and the thread itself must belong to the shop. Messages
 * are always written inside a transaction that re-locks the conversation so two
 * simultaneous replies cannot interleave their read markers.
 */
final class VendorChatService
{
    public function __construct(private readonly VendorScope $scope) {}

    public function inbox(int $perPage = 20)
    {
        $shopId = $this->scope->shopId();
        $vendorId = $this->scope->userId();

        return Conversation::query()
            ->where('shop_id', $shopId)
            ->whereHas('participants', fn ($query) => $query->where('user_id', $vendorId))
            ->with(['participants.user:id,name,email,phone'])
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $conversationId): Conversation
    {
        $conversation = Conversation::query()
            ->with(['participants.user:id,name,email,phone', 'order:id,order_number,total'])
            ->find($conversationId);

        abort_if($conversation === null, 404);
        abort_if(! $this->isOwned($conversation), 403);

        $this->assertParticipant($conversation);

        return $conversation;
    }

    public function withCustomer(int $customerId): Conversation
    {
        $customer = $this->assertCustomer($customerId);
        $shopId = $this->scope->shopId();

        $conversation = DB::transaction(function () use ($customer, $shopId): Conversation {
            $existing = Conversation::query()
                ->where('shop_id', $shopId)
                ->where('type', 'support')
                ->whereHas('participants', fn ($query) => $query->where('user_id', $this->scope->userId()))
                ->whereHas('participants', fn ($query) => $query->where('user_id', $customer->getKey()))
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return Conversation::query()->create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'type' => 'support',
                'shop_id' => $shopId,
                'subject' => 'Percakapan dengan '.$customer->name,
                'status' => 'open',
            ]);
        }, 3);

        $this->attach($conversation, $customer->getKey(), 'customer');
        $this->attach($conversation, $this->scope->userId(), 'vendor');

        return $conversation->fresh(['participants.user', 'order']);
    }

    public function store(Conversation $conversation, array $payload): Message
    {
        $this->assertParticipant($conversation);

        $body = VendorScope::clean($payload['body'] ?? '', 5000);

        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Pesan tidak boleh kosong.']);
        }

        $message = DB::transaction(function () use ($conversation, $body, $payload): Message {
            $locked = Conversation::query()->lockForUpdate()->findOrFail($conversation->getKey());
            $this->assertParticipant($locked);

            $message = Message::query()->create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'conversation_id' => $locked->getKey(),
                'user_id' => $this->scope->userId(),
                'body' => $body,
                'attachments' => $this->attachments($payload),
            ]);

            $locked->forceFill([
                'status' => 'open',
                'first_reply_at' => $locked->first_reply_at ?? $message->created_at,
                'last_message_at' => $message->created_at,
            ])->save();

            ConversationParticipant::query()
                ->where('conversation_id', $locked->getKey())
                ->where('user_id', '!=', $this->scope->userId())
                ->increment('unread_count');

            return $message;
        }, 3);

        app(AuditLogger::class)->log('vendor.chat.replied', $conversation, [], [
            'conversation_id' => $conversation->getKey(),
            'message_id' => $message->getKey(),
        ], $this->scope->userId());

        return $message->refresh();
    }

    public function markRead(Conversation $conversation): int
    {
        $this->assertParticipant($conversation);

        $updated = Message::query()
            ->where('conversation_id', $conversation->getKey())
            ->where('user_id', '!=', $this->scope->userId())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        ConversationParticipant::query()
            ->where('conversation_id', $conversation->getKey())
            ->where('user_id', $this->scope->userId())
            ->update(['unread_count' => 0, 'last_read_at' => now()]);

        return $updated;
    }

    public function customers(Conversation $conversation): Collection
    {
        return ConversationParticipant::query()
            ->with('user:id,name,email,phone')
            ->where('conversation_id', $conversation->getKey())
            ->where('user_id', '!=', $this->scope->userId())
            ->get();
    }

    /**
     * Saran balasan AI (3 opsi) untuk percakapan — read-only, tidak mengubah
     * alur chat apa pun. Aman bila provider AI tidak dikonfigurasi: dipakai
     * template lokal deterministik.
     *
     * @return array{options: list<string>, topik: string, source: string}
     */
    public function saranBalasan(Conversation $conversation, int $limit = 3): array
    {
        try {
            $this->assertParticipant($conversation);

            $pesan = Message::query()
                ->where('conversation_id', $conversation->getKey())
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->limit(5)
                ->pluck('body');

            $terakhirPelanggan = '';
            foreach ($pesan as $body) {
                $teks = trim((string) $body);
                if ($teks !== '') {
                    $terakhirPelanggan = $teks;
                    break;
                }
            }

            $order = $conversation->relationLoaded('order') ? $conversation->order : $conversation->order()->first();

            $konteks = [
                'nomor_pesanan' => (string) ($order?->order_number ?? ''),
                'status_pesanan' => (string) ($order?->order_status ?? ''),
            ];

            $hasil = (new \App\Services\Ai\BalasanChat)->suggest($terakhirPelanggan, array_filter($konteks), null);

            return [
                'options' => array_values(array_slice($hasil['options'], 0, max(1, min(3, $limit)))),
                'topik' => (string) $hasil['topik'],
                'source' => (string) $hasil['source'],
            ];
        } catch (\Throwable) {
            return [
                'options' => \App\Services\Ai\BalasanChat::fallbackOptions('', []),
                'topik' => 'umum',
                'source' => 'fallback',
            ];
        }
    }

    public function isOwned(Conversation $conversation): bool
    {
        return (int) $conversation->shop_id === $this->scope->shopId();
    }

    /**
     * Template balasan cepat (Bahasa Indonesia). Disimpan di system_settings
     * `vendor_chat_templates` bila ada, fallback ke bawaan.
     *
     * @return list<array{key: string, label: string, body: string}>
     */
    public function quickReplies(): array
    {
        $fallback = [
            ['key' => 'salam', 'label' => 'Salam pembuka', 'body' => 'Halo kak, terima kasih sudah menghubungi kami. Ada yang bisa kami bantu?'],
            ['key' => 'cek_pesanan', 'label' => 'Cek pesanan', 'body' => 'Baik kak, mohon informasikan nomor pesanannya agar kami cek segera.'],
            ['key' => 'pengiriman', 'label' => 'Info pengiriman', 'body' => 'Pesanan kakak sedang kami siapkan. Estimasi pengiriman 1-2 hari kerja ya kak.'],
            ['key' => 'penutup', 'label' => 'Penutup', 'body' => 'Terima kasih kak. Jangan ragu hubungi kami lagi bila ada kendala.'],
        ];
        try {
            $raw = \App\Models\SystemSetting::get('vendor_chat_templates', '');
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && $decoded !== []) {
                    return array_values(array_filter(array_map(fn (mixed $t): ?array => is_array($t) && isset($t['body']) ? [
                        'key' => (string) ($t['key'] ?? \Illuminate\Support\Str::slug((string) ($t['label'] ?? 'template'))),
                        'label' => (string) ($t['label'] ?? 'Template'),
                        'body' => (string) $t['body'],
                    ] : null, $decoded)));
                }
            }
        } catch (\Throwable) {
        }

        return $fallback;
    }

    /**
     * Status SLA + penugasan percakapan, tetap dalam scope toko.
     *
     * @return array{sla_hours: int, elapsed_hours: float, breached: bool, assignee: string|null, rating: float|null}
     */
    public function slaStatus(Conversation $conversation): array
    {
        $this->assertParticipant($conversation);
        $slaHours = 24;
        try {
            $slaHours = max(1, (int) \App\Models\SystemSetting::get('vendor_chat_sla_hours', 24));
        } catch (\Throwable) {
        }
        $start = $conversation->created_at;
        $elapsed = $start !== null ? round($start->diffInMinutes(now()) / 60, 1) : 0.0;
        $repliedAt = $conversation->first_reply_at;
        $breached = $repliedAt === null ? $elapsed > $slaHours : ($repliedAt->diffInMinutes($start) / 60) > $slaHours;
        $assignee = null;
        $rating = null;
        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn('conversations', 'assigned_to')) {
                $assignee = $conversation->getAttribute('assigned_to') ? (string) $conversation->getAttribute('assigned_to') : null;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('conversations', 'rating')) {
                $rating = $conversation->getAttribute('rating') !== null ? (float) $conversation->getAttribute('rating') : null;
            }
        } catch (\Throwable) {
        }

        return ['sla_hours' => $slaHours, 'elapsed_hours' => $elapsed, 'breached' => $breached, 'assignee' => $assignee, 'rating' => $rating];
    }

    /** Tetapkan percakapan ke anggota tim (disimpan defensif bila kolom tersedia). */
    public function assign(Conversation $conversation, string $assignee): void
    {
        $this->assertParticipant($conversation);
        $assignee = VendorScope::clean($assignee, 120);
        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn('conversations', 'assigned_to')) {
                $conversation->forceFill(['assigned_to' => $assignee !== '' ? $assignee : null])->save();
            }
        } catch (\Throwable) {
        }
        app(AuditLogger::class)->log('vendor.chat.assigned', $conversation, [], ['assignee' => $assignee], $this->scope->userId());
    }

    /** Nilai percakapan 1-5 dari vendor (disimpan defensif bila kolom tersedia). */
    public function rate(Conversation $conversation, int $stars): void
    {
        $this->assertParticipant($conversation);
        $stars = max(1, min(5, $stars));
        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn('conversations', 'rating')) {
                $conversation->forceFill(['rating' => $stars])->save();
            }
        } catch (\Throwable) {
        }
        app(AuditLogger::class)->log('vendor.chat.rated', $conversation, [], ['rating' => $stars], $this->scope->userId());
    }

    public function assertParticipant(Conversation $conversation): void
    {
        abort_if(! $this->isOwned($conversation), 403);

        $isParticipant = ConversationParticipant::query()
            ->where('conversation_id', $conversation->getKey())
            ->where('user_id', $this->scope->userId())
            ->exists();

        abort_if(! $isParticipant, 403);
    }

    public function assertCustomer(int $customerId): User
    {
        $shopId = $this->scope->shopId();

        $customer = User::query()
            ->whereKey($customerId)
            ->whereHas('orders', fn ($query) => $query->where('shop_id', $shopId))
            ->first();

        abort_if($customer === null, 404);

        return $customer;
    }

    private function attach(Conversation $conversation, int $userId, string $role): void
    {
        ConversationParticipant::query()->firstOrCreate(
            ['conversation_id' => $conversation->getKey(), 'user_id' => $userId],
            ['role' => $role, 'unread_count' => 0],
        );
    }

    /** @return list<array<string, mixed>>|null */
    private function attachments(array $payload): ?array
    {
        $raw = $payload['attachments'] ?? null;

        if (! is_array($raw) || $raw === []) {
            return null;
        }

        $out = [];

        foreach (array_slice($raw, 0, 5) as $attachment) {
            $path = VendorScope::clean(is_array($attachment) ? ($attachment['path'] ?? null) : $attachment, 255);

            if ($path !== '') {
                $out[] = ['path' => $path];
            }
        }

        return $out === [] ? null : $out;
    }
}
