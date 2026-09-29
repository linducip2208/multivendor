<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'parent_id' => $this->parent_id === null ? null : (int) $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'icon' => $this->icon ?? null,
            'image' => $this->image ?? null,
            'description' => $this->description ?? null,
            'banner_image' => $this->banner_image ?? null,
            'is_featured' => (bool) ($this->is_featured ?? false),
            'sort_order' => (int) ($this->sort_order ?? 0),
            'status' => (bool) ($this->status ?? false),
            'meta_title' => $this->meta_title ?? null,
            'meta_description' => $this->meta_description ?? null,
            'parent' => new self($this->whenLoaded('parent')),
            'children' => self::collection($this->whenLoaded('children')),
            'created_at' => ApiResponse::iso($this->created_at),
            'updated_at' => ApiResponse::iso($this->updated_at),
        ];
    }
}
