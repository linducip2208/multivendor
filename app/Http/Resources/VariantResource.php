<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $special = $this->special_price !== null
            && (! $this->discount_start || $this->discount_start <= now())
            && (! $this->discount_end || $this->discount_end >= now());

        return [
            'id' => (int) $this->id,
            'product_id' => $this->product_id === null ? null : (int) $this->product_id,
            'sku' => $this->sku ?? null,
            'variant' => $this->variant ?? null,
            'variant_attributes' => $this->variant_attributes ?? null,
            'price' => ApiResponse::money($this->price),
            'special_price' => ApiResponse::moneyOrNull($this->special_price),
            'effective_price' => ApiResponse::money($this->getEffectivePrice()),
            'is_on_sale' => $special && (float) $this->special_price < (float) $this->price,
            'stock' => (int) ($this->stock ?? 0),
            'is_out_of_stock' => (int) ($this->stock ?? 0) <= 0,
            'discount_start' => ApiResponse::iso($this->discount_start),
            'discount_end' => ApiResponse::iso($this->discount_end),
            'created_at' => ApiResponse::iso($this->created_at),
            'updated_at' => ApiResponse::iso($this->updated_at),
        ];
    }
}
