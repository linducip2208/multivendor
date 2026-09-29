<?php

declare(strict_types=1);

namespace App\Events\Support;

use App\Models\Product;

final class ProductSnapshot
{
    /** @return array<string, mixed> */
    public static function summary(Product $product): array
    {
        return [
            'id' => (int) $product->getKey(),
            'name' => (string) $product->name,
            'slug' => (string) $product->slug,
            'sku' => (string) $product->sku,
            'barcode' => (string) $product->barcode,
            'shop_id' => $product->shop_id,
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'price' => (string) $product->price,
            'special_price' => $product->special_price === null ? null : (string) $product->special_price,
            'currency' => 'IDR',
            'tax' => (string) $product->tax,
            'current_stock' => (int) $product->current_stock,
            'low_stock_threshold' => (int) $product->low_stock_threshold,
            'status' => (string) $product->status,
            'request_status' => $product->request_status,
            'published' => (bool) $product->published,
            'featured' => (bool) $product->featured,
            'rating_average' => (string) $product->rating_average,
            'rating_count' => (int) $product->rating_count,
            'sold_count' => (int) $product->sold_count,
            'created_at' => OrderSnapshot::iso($product->created_at),
            'updated_at' => OrderSnapshot::iso($product->updated_at),
        ];
    }
}
