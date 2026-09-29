<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'barcode' => $this->barcode ?? null,
            'short_description' => $this->short_description ?? null,
            'description' => $this->description ?? null,
            'thumbnail' => $this->thumbnail ?? null,
            'images' => $this->images ?? [],
            'price' => ApiResponse::money($this->price),
            'special_price' => ApiResponse::moneyOrNull($this->special_price),
            'effective_price' => ApiResponse::money($this->getEffectivePrice()),
            'discount_percentage' => $this->getDiscountPercentage(),
            'is_on_sale' => $this->isOnSale(),
            'tax' => ApiResponse::money($this->tax),
            'shipping_cost' => ApiResponse::money($this->shipping_cost),
            'product_type' => $this->product_type,
            'weight' => $this->weight === null ? null : (int) $this->weight,
            'unit' => $this->unit ?? null,
            'min_qty' => (int) ($this->min_qty ?? 1),
            'max_qty' => $this->max_qty === null ? null : (int) $this->max_qty,
            'multiply_qty' => (bool) ($this->multiply_qty ?? false),
            'refundable' => (bool) ($this->refundable ?? false),
            'featured' => (bool) ($this->featured ?? false),
            'condition' => $this->condition ?? null,
            'warranty' => $this->warranty === null ? null : (int) $this->warranty,
            'warranty_unit' => $this->warranty_unit ?? null,
            'stock_available' => (int) $this->current_stock,
            'is_out_of_stock' => (int) $this->current_stock <= 0,
            'rating_average' => ApiResponse::moneyOrNull($this->rating_average),
            'rating_count' => (int) ($this->rating_count ?? 0),
            'sold_count' => (int) ($this->sold_count ?? 0),
            'view_count' => (int) ($this->view_count ?? 0),
            'shop_id' => $this->shop_id === null ? null : (int) $this->shop_id,
            'category_id' => $this->category_id === null ? null : (int) $this->category_id,
            'brand_id' => $this->brand_id === null ? null : (int) $this->brand_id,
            'status' => $this->status ?? null,
            'shop' => new ShopPublicResource($this->whenLoaded('shop')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'brand' => new BrandResource($this->whenLoaded('brand')),
            'variants' => VariantResource::collection($this->whenLoaded('variants')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'created_at' => ApiResponse::iso($this->created_at),
            'updated_at' => ApiResponse::iso($this->updated_at),
        ];
    }
}
