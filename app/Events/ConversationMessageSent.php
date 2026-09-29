<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\BuildsWebhookPayload;
use App\Events\Contracts\WebhookPayload;
use App\Models\Message;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ConversationMessageSent implements WebhookPayload
{
    use BuildsWebhookPayload;
    use Dispatchable;
    use SerializesModels;

    public const EVENT = 'conversation.message_sent';

    public const ENTITY = 'message';

    public function __construct(public Message $message) {}

    public function entityId(): ?string
    {
        return (string) $this->message->getKey();
    }

    public function shopId(): ?int
    {
        return null;
    }

    public function toPayload(): array
    {
        $message = $this->message->loadMissing(['conversation', 'author']);

        return [
            'message' => [
                'id' => (int) $message->getKey(),
                'uuid' => (string) $message->uuid,
                'conversation_id' => $message->conversation_id,
                'subject' => $message->conversation?->subject,
                'user_id' => $message->user_id,
                'author_name' => $message->author?->name,
                'author_role' => $message->author?->role,
                'body' => (string) $message->body,
                'has_attachments' => is_array($message->attachments) && $message->attachments !== [],
                'sent_at' => $message->created_at?->toIso8601String(),
            ],
        ];
    }
}
