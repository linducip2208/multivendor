<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

#[Fillable([
    'shop_id', 'category_id', 'brand_id', 'name', 'slug', 'description',
    'short_description', 'thumbnail', 'images', 'unit', 'min_qty', 'max_qty',
    'current_stock', 'weight', 'sku', 'barcode', 'product_type', 'refundable', 'featured',
    'published', 'created_by', 'price', 'special_price', 'discount_type',
    'discount_start', 'discount_end', 'tax', 'tax_type', 'shipping_cost',
    'shipping_cost_type', 'multiply_qty', 'meta_title', 'meta_description',
    'meta_image', 'video_url', 'digital_file', 'request_status', 'approved_by',
    'approved_at', 'status',
    'rating_average', 'rating_count', 'sold_count', 'view_count', 'search_keywords',
    'warranty', 'warranty_unit', 'condition', 'low_stock_threshold', 'seo_score'
])]
class Product extends Model
{
    protected function casts(): array
    {
        return [
            'images' => 'array',
            'refundable' => 'boolean',
            'featured' => 'boolean',
            'published' => 'boolean',
            'multiply_qty' => 'boolean',
            'price' => 'decimal:2',
            'special_price' => 'decimal:2',
            'tax' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'rating_average' => 'decimal:2',
            'rating_count' => 'integer',
            'sold_count' => 'integer',
            'view_count' => 'integer',
            'low_stock_threshold' => 'integer',
            'seo_score' => 'integer',
            'discount_start' => 'datetime',
            'discount_end' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function movementHistory(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class, 'coupon_product');
    }

    public function flashDeals(): BelongsToMany
    {
        return $this->belongsToMany(FlashDeal::class, 'flash_deal_products');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ProductTag::class, 'product_tag_pivot', 'product_id', 'product_tag_id');
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (empty($this->thumbnail)) {
            return null;
        }

        return url('img/'.ltrim((string) $this->thumbnail, '/'));
    }

    public function getStorefrontUrlAttribute(): string
    {
        try {
            return route('products.show', $this->slug);
        } catch (Throwable) {
            return url('products/'.$this->slug);
        }
    }

    public function getIsOutOfStockAttribute(): bool
    {
        return (int) $this->current_stock <= 0;
    }

    public function getIsLowStockAttribute(): bool
    {
        $stock = (int) $this->current_stock;

        return $stock > 0 && $stock <= (int) $this->low_stock_threshold;
    }

    /**
     * Gandakan produk beserta variannya memakai kolom yang sudah ada.
     * Salinan selalu berstatus menunggu persetujuan dan tidak tayang,
     * sehingga tidak ada produk ganda yang lolos tanpa moderasi.
     */
    public function duplikasikan(): Product
    {
        $salinan = $this->replicate([
            'slug', 'sku', 'barcode', 'sold_count', 'view_count',
            'rating_average', 'rating_count', 'approved_by', 'approved_at',
        ]);

        $dasar = trim((string) $this->name).' (Salinan)';
        $salinan->name = $dasar;
        $salinan->slug = static::slugUnik(\Illuminate\Support\Str::slug($dasar) ?: 'produk');
        $salinan->sku = null;
        $salinan->barcode = null;
        $salinan->status = 'pending';
        $salinan->published = false;
        $salinan->approved_by = null;
        $salinan->approved_at = null;
        $salinan->sold_count = 0;
        $salinan->view_count = 0;
        $salinan->rating_average = 0;
        $salinan->rating_count = 0;
        $salinan->save();

        foreach ($this->relationLoaded('variants') ? $this->variants : $this->variants()->get() as $varian) {
            $salinanVarian = $varian->replicate(['sku']);
            $salinanVarian->product_id = $salinan->id;
            $salinanVarian->sku = null;
            $salinanVarian->save();
        }

        return $salinan->refresh();
    }

    public static function slugUnik(string $dasar): string
    {
        $slug = $dasar;
        $angka = 1;

        while (static::query()->where('slug', $slug)->exists()) {
            $angka++;
            $slug = $dasar.'-'.$angka;
        }

        return $slug;
    }

    /**
     * Arsip ringan memakai kolom existing: tidak tayang + ditangguhkan.
     * Penjadwalan tayang otomatis (published_at) belum didukung model,
     * jadi arsip selalu manual lewat metode ini.
     */
    public function arsipkan(): bool
    {
        return (bool) $this->forceFill([
            'published' => false,
            'status' => 'suspended',
        ])->save();
    }

    public function pulihkanDariArsip(): bool
    {
        return (bool) $this->forceFill([
            'published' => false,
            'status' => 'pending',
        ])->save();
    }

    public function diarsipkan(): bool
    {
        return ! (bool) $this->published && (string) $this->status === 'suspended';
    }

    public function hasActiveSpecialPrice(): bool
    {
        if ($this->special_price === null) {
            return false;
        }

        if ($this->discount_start !== null && $this->discount_start > now()) {
            return false;
        }

        if ($this->discount_end !== null && $this->discount_end < now()) {
            return false;
        }

        return true;
    }

    public function isOnSale(): bool
    {
        return $this->hasActiveSpecialPrice() && (float) $this->special_price < (float) $this->price;
    }

    public function flashDealPrice(): ?float
    {
        if ($this->id === null) {
            return null;
        }

        $base = $this->hasActiveSpecialPrice() ? (float) $this->special_price : (float) $this->price;

        try {
            $query = DB::table('flash_deal_products as fdp')
                ->join('flash_deals as fd', 'fd.id', '=', 'fdp.flash_deal_id')
                ->where('fdp.product_id', $this->id)
                ->where('fd.status', true)
                ->where('fd.start_date', '<=', now())
                ->where('fd.end_date', '>=', now());

            if (static::hasDiscountedPriceColumn()) {
                $value = (float) $query->min('fdp.discounted_price');

                return $value > 0 ? min($value, $base) : null;
            }

            $best = null;

            foreach ($query->get(['fdp.discount_type', 'fdp.discount_value']) as $row) {
                $price = $this->priceFromFlashDealRow($row, $base);

                if ($price !== null && $price > 0 && ($best === null || $price < $best)) {
                    $best = $price;
                }
            }

            return $best === null ? null : min($best, $base);
        } catch (Throwable) {
            return null;
        }
    }

    public function getEffectivePrice(): float
    {
        $base = $this->hasActiveSpecialPrice() ? (float) $this->special_price : (float) $this->price;

        $flash = $this->flashDealPrice();

        if ($flash !== null && $flash > 0 && $flash < $base) {
            return $flash;
        }

        return $base;
    }

    public function getDiscountPercentage(): ?int
    {
        if (! $this->hasActiveSpecialPrice()) {
            return null;
        }

        if ((float) $this->price <= 0) {
            return null;
        }

        return (int) round(((float) $this->price - (float) $this->special_price) / (float) $this->price * 100);
    }

    private function priceFromFlashDealRow(object $row, float $base): ?float
    {
        $value = (float) ($row->discount_value ?? 0);

        if ($value <= 0) {
            return null;
        }

        if (($row->discount_type ?? null) === 'flat') {
            return max(0.0, $base - $value);
        }

        return max(0.0, $base - ($base * $value / 100));
    }

    private static function hasDiscountedPriceColumn(): bool
    {
        static $exists = null;

        if ($exists === null) {
            try {
                $exists = Schema::hasColumn('flash_deal_products', 'discounted_price');
            } catch (Throwable) {
                $exists = false;
            }
        }

        return $exists;
    }
}
