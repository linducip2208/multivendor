<?php

declare(strict_types=1);

namespace App\Events\Concerns;

use Illuminate\Support\Str;

trait BuildsWebhookPayload
{
    private ?string $resolvedEventId = null;

    public function eventName(): string
    {
        return (string) static::EVENT;
    }

    public function entityType(): string
    {
        return (string) static::ENTITY;
    }

    public function eventId(): string
    {
        return $this->resolvedEventId ??= (string) Str::uuid();
    }

    public function envelope(): array
    {
        return [
            'id' => $this->eventId(),
            'event' => $this->eventName(),
            'entity' => [
                'type' => $this->entityType(),
                'id' => $this->entityId(),
            ],
            'occurred_at' => now()->toIso8601String(),
            'data' => $this->toPayload(),
        ];
    }
}
