<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'rating' => (int) $this->rating,
            'comment' => $this->comment ?? null,
            'images' => $this->images ?? [],
            'product_id' => $this->product_id === null ? null : (int) $this->product_id,
            'customer_id' => $this->customer_id === null ? null : (int) $this->customer_id,
            'status' => (bool) ($this->status ?? true),
            'created_at' => ApiResponse::iso($this->created_at),
            'updated_at' => ApiResponse::iso($this->updated_at),
            'product' => new ProductResource($this->whenLoaded('product')),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer === null ? null : [
                'id' => (int) $this->customer->id,
                'name' => $this->customer->name ?? null,
                'avatar' => $this->customer->avatar ?? null,
            ]),
        ];
    }
}
