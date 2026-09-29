<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'shop_id', 'provider_id', 'feature', 'model', 'prompt_tokens', 'completion_tokens', 'estimated_cost', 'duration_ms', 'success', 'error'])]
class AiUsage extends Model
{
    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'estimated_cost' => 'decimal:6',
            'duration_ms' => 'integer',
            'success' => 'boolean',
        ];
    }

    public function totalTokens(): int
    {
        return (int) $this->prompt_tokens + (int) $this->completion_tokens;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
