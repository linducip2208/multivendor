<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['webhook_endpoint_id', 'event', 'event_id', 'payload', 'attempt', 'max_attempts', 'status', 'response_status', 'response_body', 'duration_ms', 'delivered_at', 'next_retry_at'])]
class WebhookDelivery extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempt' => 'integer',
            'max_attempts' => 'integer',
            'response_status' => 'integer',
            'duration_ms' => 'integer',
            'delivered_at' => 'datetime',
            'next_retry_at' => 'datetime',
        ];
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->where(function (Builder $q): void {
                $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now());
            });
    }

    public function scopeExhausted(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('status', 'exhausted')
                ->orWhereColumn('attempt', '>=', 'max_attempts');
        });
    }

    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('status', 'delivered');
    }

    public function hasAttemptsLeft(): bool
    {
        return (int) $this->attempt < (int) $this->max_attempts;
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
