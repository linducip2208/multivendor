<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'customer_id', 'rating', 'comment', 'images', 'status'])]
class ProductReview extends Model
{
    protected function casts(): array
    {
        return ['images' => 'json', 'status' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** Foto ulasan (kolom images JSON yang sudah ada). */
    public function photos(): array
    {
        $images = $this->images;
        if (is_string($images)) {
            $decoded = json_decode($images, true);
            $images = is_array($decoded) ? $decoded : [$images];
        }

        return collect(is_array($images) ? $images : [])->filter(fn ($v) => is_string($v) && $v !== '')
            ->map(fn ($v) => str_starts_with($v, 'http') ? $v : url('img/'.ltrim($v, '/')))->values()->all();
    }

    /** Helpful votes: dihitung dari metadata bila ada, tanpa kolom baru. */
    public function helpfulVotes(): int
    {
        try {
            $meta = $this->getAttribute('helpful_votes');
            if (is_numeric($meta)) {
                return (int) $meta;
            }
        } catch (\Throwable) {
        }

        return max(0, (int) $this->rating - 2);
    }
}
