<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'shop_id', 'category_id', 'brand_id', 'name', 'slug', 'description',
    'short_description', 'thumbnail', 'images', 'unit', 'min_qty', 'max_qty',
    'current_stock', 'sku', 'barcode', 'product_type', 'refundable', 'featured',
    'published', 'created_by', 'price', 'special_price', 'discount_type',
    'discount_start', 'discount_end', 'tax', 'tax_type', 'shipping_cost',
    'shipping_cost_type', 'multiply_qty', 'meta_title', 'meta_description',
    'meta_image', 'video_url', 'digital_file', 'request_status', 'approved_by',
    'approved_at', 'status'
])]
class Product extends Model
{
    protected function casts(): array
    {
        return [
            'images' => 'json',
            'refundable' => 'boolean',
            'featured' => 'boolean',
            'published' => 'boolean',
            'multiply_qty' => 'boolean',
            'price' => 'decimal:2',
            'special_price' => 'decimal:2',
            'tax' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
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

    public function getEffectivePrice(): float
    {
        if ($this->special_price && $this->discount_start <= now() && $this->discount_end >= now()) {
            return (float) $this->special_price;
        }
        return (float) $this->price;
    }

    public function getDiscountPercentage(): ?int
    {
        if (!$this->special_price || $this->price <= 0) return null;
        return (int) round((($this->price - $this->special_price) / $this->price) * 100);
    }
}
