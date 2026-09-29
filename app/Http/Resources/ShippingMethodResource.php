<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingMethodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'cost' => ApiResponse::money($this->cost),
            'currency' => 'IDR',
            'duration' => $this->duration ?? null,
            'description' => $this->description ?? null,
            'status' => (bool) ($this->status ?? false),
            'created_at' => ApiResponse::iso($this->created_at),
        ];
    }
}
