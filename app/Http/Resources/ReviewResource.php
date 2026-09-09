<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'rating' => $this->rating, 'comment' => $this->comment, 'images' => $this->images, 'created_at' => $this->created_at,
            'customer' => $this->whenLoaded('customer', fn () => ['id' => $this->customer?->id, 'name' => $this->customer?->name, 'avatar' => $this->customer?->avatar])];
    }
}
