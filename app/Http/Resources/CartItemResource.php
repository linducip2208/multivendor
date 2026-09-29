<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $price = ApiResponse::money($this->price);
        $quantity = (int) $this->quantity;

        return [
            'id' => (int) $this->id,
            'product_id' => (int) $this->product_id,
            'product_variant_id' => $this->product_variant_id === null ? null : (int) $this->product_variant_id,
            'quantity' => $quantity,
            'price' => $price,
            'tax' => ApiResponse::money($this->tax),
            'line_total' => ApiResponse::money((float) $price * $quantity),
            'product' => new ProductResource($this->whenLoaded('product')),
            'variant' => new VariantResource($this->whenLoaded('variant')),
            'created_at' => ApiResponse::iso($this->created_at),
            'updated_at' => ApiResponse::iso($this->updated_at),
        ];
    }
}
