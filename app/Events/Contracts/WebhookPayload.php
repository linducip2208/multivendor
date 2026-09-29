<?php

declare(strict_types=1);

namespace App\Events\Contracts;

interface WebhookPayload
{
    public function eventName(): string;

    public function toPayload(): array;

    public function envelope(): array;

    public function eventId(): string;

    public function entityId(): ?string;

    public function entityType(): string;
}
