<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uuid', 'conversation_id', 'user_id', 'body', 'attachments', 'is_internal_note', 'is_flagged', 'read_at', 'deleted_at'])]
class Message extends Model
{
    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'is_internal_note' => 'boolean',
            'is_flagged' => 'boolean',
            'read_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
