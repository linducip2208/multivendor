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

    public function isOwned(Conversation $conversation): bool
    {
        return (int) $conversation->shop_id === $this->scope->shopId();
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
