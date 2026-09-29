<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['title', 'start_date', 'end_date', 'banner', 'status', 'featured'])]
class FlashDeal extends Model
{
    /**
     * SQL fragment returning the best effective discount percentage (0-100+)
     * across a deal's pivot rows. Percentage rows contribute discount_value
     * directly; flat rows are converted against products.price.
     *
     * Used for ORDER BY without persisting a discount_percentage column
     * (discounts live on the flash_deal_products pivot).
     */
    public static function bestDiscountExpression(string $dealIdColumn = 'flash_deals.id'): string
    {
        return '(select max(case when fdp.discount_type = '
            ."'flat' then fdp.discount_value * 100.0 / nullif(p.price, 0) "
            .'else fdp.discount_value end) '
            .'from flash_deal_products fdp '
            .'inner join products p on p.id = fdp.product_id '
            .'where fdp.flash_deal_id = '.$dealIdColumn.')';
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<FlashDeal> $query
     */
    public function scopeOrderByBestDiscount($query, string $direction = 'desc'): void
    {
        $query->orderByRaw(static::bestDiscountExpression().' '.($direction === 'asc' ? 'asc' : 'desc'));
    }
    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'status' => 'boolean',
            'featured' => 'boolean',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'flash_deal_products')
            ->withPivot(['discount_type', 'discount_value']);
    }

    /**
     * Best effective discount percentage across attached products.
     * Mirrors Product::priceFromFlashDealRow() semantics in PHP for
     * display/analytics use (ordering uses scopeOrderByBestDiscount).
     */
    public function bestDiscountPercentage(): float
    {
        $best = 0.0;

        $rows = $this->relationLoaded('products')
            ? $this->products->map(fn ($p) => (object) [
                'discount_type' => $p->pivot?->discount_type,
                'discount_value' => $p->pivot?->discount_value,
                'price' => $p->price,
                'special_price' => $p->special_price,
                'discount_start' => $p->discount_start,
                'discount_end' => $p->discount_end,
            ])
            : \Illuminate\Support\Facades\DB::table('flash_deal_products as fdp')
                ->join('products as p', 'p.id', '=', 'fdp.product_id')
                ->where('fdp.flash_deal_id', $this->getKey())
                ->get([
                    'fdp.discount_type', 'fdp.discount_value',
                    'p.price', 'p.special_price', 'p.discount_start', 'p.discount_end',
                ]);

        foreach ($rows as $row) {
            $pct = self::effectivePercentage(
                $row->discount_type ?? null,
                (float) ($row->discount_value ?? 0),
                (float) ($row->price ?? 0),
                $row->special_price,
                $row->discount_start ?? null,
                $row->discount_end ?? null,
            );

            if ($pct > $best) {
                $best = $pct;
            }
        }

        return $best;
    }

    public function getBestDiscountPercentageAttribute(): float
    {
        return $this->bestDiscountPercentage();
    }

    public static function effectivePercentage(
        ?string $type,
        float $value,
        float $price,
        mixed $specialPrice = null,
        mixed $start = null,
        mixed $end = null,
    ): float {
        if ($value <= 0) {
            return 0.0;
        }

        if ($type === 'percentage') {
            return min(100.0, $value);
        }

        $base = $price;
        if ($specialPrice !== null && (float) $specialPrice > 0) {
            $now = now();
            $afterStart = $start === null || $start <= $now;
            $beforeEnd = $end === null || $end >= $now;
            if ($afterStart && $beforeEnd) {
                $base = (float) $specialPrice;
            }
        }

        if ($base <= 0) {
            return 0.0;
        }

        return min(100.0, $value / $base * 100);
    }
}
