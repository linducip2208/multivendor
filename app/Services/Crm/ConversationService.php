<?php

declare(strict_types=1);

namespace App\Services\Crm;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Services\ActivityLogger;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Support inbox.
 *
 * A reply is either a customer-visible message or an internal note; the two
 * never share a code path, so an internal note can never leak to a customer.
 */
final class ConversationService
{
    public const PAGE_SIZE = 20;

    public const STATUSES = [
        'open' => 'Terbuka',
        'pending' => 'Menunggu',
        'closed' => 'Ditutup',
        'spam' => 'Spam',
    ];

    /**
     * @return array<string, mixed>
     */
    public function index(int $page = 1, string $search = '', string $status = ''): array
    {
        $query = Conversation::query()->with(['shop:id,name', 'participants.user:id,name']);

        if ($status !== '' && array_key_exists($status, self::STATUSES)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('subject', 'like', '%'.$search.'%')
                    ->orWhere('uuid', 'like', '%'.$search.'%');
            });
        }

        $page = max(1, $page);
        $total = (int) (clone $query)->count();
        $lastPage = (int) max(1, (int) ceil($total / self::PAGE_SIZE));

        $rows = $query->orderByRaw('COALESCE(last_message_at, created_at) DESC')
            ->forPage($page, self::PAGE_SIZE)
            ->get()
            ->map(fn (Conversation $conversation): array => [
                'id' => (int) $conversation->id,
                'uuid' => (string) $conversation->uuid,
                'subject' => (string) ($conversation->subject ?? '(tanpa subjek)'),
                'type' => (string) $conversation->type,
                'status' => (string) $conversation->status,
                'status_label' => self::STATUSES[$conversation->status] ?? $conversation->status,
                'priority' => (string) $conversation->priority,
                'shop' => (string) ($conversation->shop?->name ?? '-'),
                'participants' => $conversation->participants->map(fn (ConversationParticipant $p): string => (string) ($p->user?->name ?? '#'.$p->user_id))->all(),
                'messages' => (int) $conversation->messages()->whereNull('deleted_at')->count(),
                'unread' => (int) $conversation->participants()->sum('unread_count'),
                'last_message_at' => (string) ($conversation->last_message_at?->diffForHumans() ?? $conversation->created_at?->diffForHumans() ?? '-'),
                'url' => route('admin.conversations.show', $conversation->id),
            ])
            ->all();

        return [
            'rows' => $rows,
            'counts' => $this->counts(),
            'pagination' => [
                'total' => $total,
                'per_page' => self::PAGE_SIZE,
                'current_page' => $page,
                'last_page' => $lastPage,
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = ['all' => 0, 'unread' => 0];

        foreach (array_keys(self::STATUSES) as $status) {
            $counts[$status] = 0;
        }

        try {
            foreach (Conversation::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->get() as $row) {
                $key = (string) $row->status;
                if (array_key_exists($key, $counts)) {
                    $counts[$key] = (int) $row->aggregate;
                }
                $counts['all'] += (int) $row->aggregate;
            }

            $counts['unread'] = (int) ConversationParticipant::query()->where('unread_count', '>', 0)->sum('unread_count');
        } catch (\Throwable) {
            foreach ($counts as $key => $_) {
                $counts[$key] = 0;
            }
        }

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Conversation $conversation, ?int $actorId): array
    {
        $conversation->loadMissing(['shop:id,name', 'order:id,order_number,total', 'participants.user:id,name,email,avatar']);

        $actor = $actorId !== null ? ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $actorId)
            ->first() : null;

        $this->markRead($conversation, $actor);

        $messages = Message::query()
            ->with('author:id,name,avatar,role')
            ->where('conversation_id', $conversation->id)
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(300)
            ->get()
            ->map(fn (Message $message): array => [
                'id' => (int) $message->id,
                'author' => (string) ($message->author?->name ?? 'Sistem'),
                'role' => (string) ($message->author?->role ?? 'system'),
                'body' => (string) $message->body,
                'internal' => (bool) $message->is_internal_note,
                'flagged' => (bool) $message->is_flagged,
                'mine' => $actorId !== null && (int) $message->user_id === $actorId,
                'at' => (string) ($message->created_at?->format('Y-m-d H:i') ?? ''),
            ])
            ->all();

        return [
            'conversation' => [
                'id' => (int) $conversation->id,
                'uuid' => (string) $conversation->uuid,
                'subject' => (string) ($conversation->subject ?? '(tanpa subjek)'),
                'type' => (string) $conversation->type,
                'status' => (string) $conversation->status,
                'status_label' => self::STATUSES[$conversation->status] ?? $conversation->status,
                'priority' => (string) $conversation->priority,
                'shop' => (string) ($conversation->shop?->name ?? '-'),
                'order' => (string) ($conversation->order?->order_number ?? '-'),
                'created_at' => (string) ($conversation->created_at?->format('Y-m-d H:i') ?? ''),
            ],
            'participants' => $conversation->participants->map(fn (ConversationParticipant $p): array => [
                'name' => (string) ($p->user?->name ?? '#'.$p->user_id),
                'email' => (string) ($p->user?->email ?? ''),
                'role' => (string) $p->role,
                'unread' => (int) $p->unread_count,
            ])->all(),
            'messages' => $messages,
        ];
    }

    public function reply(Conversation $conversation, string $body, bool $internalNote, ?int $actorId): Message
    {
        $message = DB::transaction(function () use ($conversation, $body, $internalNote, $actorId): Message {
            $locked = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);

            $message = $locked->messages()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $actorId,
                'body' => $body,
                'is_internal_note' => $internalNote,
                'read_at' => $internalNote ? null : now(),
            ]);

            $locked->forceFill([
                'last_message_at' => now(),
                'first_reply_at' => $locked->first_reply_at ?? ($internalNote ? null : now()),
                'status' => $internalNote ? $locked->status : 'pending',
            ])->save();

            $participant = ConversationParticipant::query()
                ->where('conversation_id', $locked->id)
                ->where('user_id', $actorId)
                ->first();

            if ($participant !== null) {
                $participant->forceFill([
                    'last_read_at' => now(),
                    'unread_count' => 0,
                    'role' => $participant->role === 'customer' ? 'admin' : $participant->role,
                ])->save();
            } else {
                ConversationParticipant::query()->create([
                    'conversation_id' => $locked->id,
                    'user_id' => $actorId,
                    'role' => 'admin',
                    'last_read_at' => now(),
                    'unread_count' => 0,
                ]);
            }

            return $message;
        });

        app(AuditLogger::class)->log(
            $internalNote ? 'conversation.internal_note' : 'conversation.replied',
            $message,
            [],
            ['conversation_id' => $conversation->id, 'internal' => $internalNote],
            $actorId,
        );

        if (! $internalNote && $actorId !== null) {
            ActivityLogger::log(
                \App\Models\User::query()->findOrFail($actorId),
                'support.replied',
                ['conversation_id' => $conversation->id],
            );
        }

        return $message;
    }

    public function markRead(Conversation $conversation, ?ConversationParticipant $participant): void
    {
        if ($participant === null) {
            return;
        }

        $participant->forceFill(['last_read_at' => now(), 'unread_count' => 0])->save();
    }
}
