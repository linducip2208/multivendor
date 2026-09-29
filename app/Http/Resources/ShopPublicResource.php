<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShopPublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo' => $this->logo ?? null,
            'banner' => $this->banner ?? null,
            'description' => $this->description ?? null,
            'rating' => $this->rating_average === null ? null : ApiResponse::money($this->rating_average),
            'rating_count' => $this->rating_count === null ? null : (int) $this->rating_count,
            'products_count' => $this->when(isset($this->products_count), (int) $this->products_count),
            'product_count' => $this->product_count === null ? null : (int) $this->product_count,
            'sold_count' => $this->sold_count === null ? null : (int) $this->sold_count,
            'city' => $this->city ?? null,
            'province' => $this->province ?? null,
            'phone' => $this->phone ?? null,
            'status' => $this->status ?? null,
            'is_on_vacation' => (bool) ($this->vacation_mode ?? false),
            'vacation_message' => $this->vacation_message ?? null,
            'created_at' => ApiResponse::iso($this->created_at),
        ];
    }
}
