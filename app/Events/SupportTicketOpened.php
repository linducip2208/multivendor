<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\BuildsWebhookPayload;
use App\Events\Contracts\WebhookPayload;
use App\Models\SupportTicket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SupportTicketOpened implements WebhookPayload
{
    use BuildsWebhookPayload;
    use Dispatchable;
    use SerializesModels;

    public const EVENT = 'support.ticket_opened';

    public const ENTITY = 'support_ticket';

    public function __construct(public SupportTicket $ticket) {}

    public function entityId(): ?string
    {
        return (string) $this->ticket->getKey();
    }

    public function shopId(): ?int
    {
        return null;
    }

    public function toPayload(): array
    {
        $ticket = $this->ticket->loadMissing('customer');

        return [
            'ticket' => [
                'id' => (int) $ticket->getKey(),
                'customer_id' => $ticket->customer_id,
                'customer_name' => $ticket->customer?->name,
                'customer_email' => $ticket->customer?->email,
                'subject' => (string) $ticket->subject,
                'type' => (string) $ticket->type,
                'priority' => (string) $ticket->priority,
                'status' => (string) $ticket->status,
                'description' => (string) $ticket->description,
                'opened_at' => $ticket->created_at?->toIso8601String(),
            ],
        ];
    }
}
