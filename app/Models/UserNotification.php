<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['uuid', 'notifiable_type', 'notifiable_id', 'channel', 'category', 'title', 'body', 'action_url', 'action_label', 'data', 'dedupe_key', 'read_at', 'archived_at'])]
class UserNotification extends Model
{
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function markAsRead(): self
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => now()])->save();
        }

        return $this;
    }

    public function markAsUnread(): self
    {
        if ($this->read_at !== null) {
            $this->forceFill(['read_at' => null])->save();
        }

        return $this;
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
