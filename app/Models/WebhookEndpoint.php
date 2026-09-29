<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['tenant_id', 'shop_id', 'name', 'url', 'secret', 'events', 'is_active', 'description', 'last_triggered_at', 'failure_count'])]
class WebhookEndpoint extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'failure_count' => 'integer',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function subscribesTo(string $event): bool
    {
        $events = $this->events;

        if (is_string($events)) {
            $decoded = json_decode($events, true);
            $events = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($events) || $events === []) {
            return true;
        }

        foreach ($events as $pattern) {
            if (! is_string($pattern)) {
                continue;
            }

            $pattern = trim($pattern);

            if ($pattern === '') {
                continue;
            }

            if ($pattern === '*' || $pattern === $event) {
                return true;
            }

            if (str_ends_with($pattern, '*') && str_starts_with($event, rtrim($pattern, '*'))) {
                return true;
            }
        }

        return false;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
