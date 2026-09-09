<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'slug' => $this->slug, 'short_description' => $this->short_description,
            'thumbnail' => $this->thumbnail, 'images' => $this->images, 'price' => (float) $this->price, 'special_price' => $this->special_price ? (float) $this->special_price : null,
            'effective_price' => $this->getEffectivePrice(), 'product_type' => $this->product_type, 'stock_available' => (int) $this->current_stock,
            'shop' => new ShopPublicResource($this->whenLoaded('shop')), 'category' => $this->whenLoaded('category', fn () => ['id' => $this->category?->id, 'name' => $this->category?->name, 'slug' => $this->category?->slug]),
            'brand' => $this->whenLoaded('brand', fn () => ['id' => $this->brand?->id, 'name' => $this->brand?->name]),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(fn ($variant) => ['id' => $variant->id, 'sku' => $variant->sku, 'variant' => $variant->variant, 'price' => $variant->getEffectivePrice(), 'stock' => $variant->stock])),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews'))];
    }
}
