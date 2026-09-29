<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug', 'type', 'description', 'rules', 'audience', 'budget', 'discount_value', 'discount_type', 'usage_limit', 'used_count', 'per_user_limit', 'status', 'starts_at', 'ends_at', 'clicks', 'conversions', 'revenue', 'activated_at', 'activated_by'])]
class Campaign extends Model
{
    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'audience' => 'array',
            'budget' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'revenue' => 'decimal:2',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'per_user_limit' => 'integer',
            'clicks' => 'integer',
            'conversions' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where(function (Builder $q): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $q): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    public function isLive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    public function hasQuotaLeft(): bool
    {
        return $this->usage_limit === null || (int) $this->used_count < (int) $this->usage_limit;
    }

    /**
     * ID segmen audiens kampanye (dibaca dari kolom audience, tanpa migrasi).
     *
     * @return list<int>
     */
    public function segmentIds(): array
    {
        $audience = is_array($this->audience) ? $this->audience : [];
        $ids = $audience['segment_ids'] ?? [];

        return array_values(array_unique(array_map(
            'intval',
            is_array($ids) ? $ids : [$ids],
        )));
    }

    /**
     * Apakah kampanye ini menargetkan segmen tertentu.
     * Kampanye tanpa segmen dianggap menargetkan semua pelanggan.
     */
    public function menargetkanSegmen(int $segmentId): bool
    {
        $ids = $this->segmentIds();

        return $ids === [] || in_array($segmentId, $ids, true);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'campaign_products');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'campaign_categories');
    }
}
