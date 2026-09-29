<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo' => $this->logo ?? null,
            'description' => $this->description ?? null,
            'is_featured' => (bool) ($this->is_featured ?? false),
            'status' => (bool) ($this->status ?? false),
            'meta_title' => $this->meta_title ?? null,
            'meta_description' => $this->meta_description ?? null,
            'products_count' => $this->when(isset($this->products_count), (int) $this->products_count),
            'created_at' => ApiResponse::iso($this->created_at),
            'updated_at' => ApiResponse::iso($this->updated_at),
        ];
    }
}
