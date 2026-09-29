<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'customer_id' => $this->customer_id === null ? null : (int) $this->customer_id,
            'label' => $this->label ?? null,
            'receiver_name' => $this->receiver_name ?? null,
            'receiver_phone' => $this->receiver_phone ?? null,
            'address' => $this->address ?? null,
            'city' => $this->city ?? null,
            'province' => $this->province ?? null,
            'postal_code' => $this->postal_code ?? null,
            'shipping_destination_id' => $this->shipping_destination_id === null ? null : (int) $this->shipping_destination_id,
            'latitude' => $this->latitude === null ? null : (string) $this->latitude,
            'longitude' => $this->longitude === null ? null : (string) $this->longitude,
            'is_default' => (bool) ($this->is_default ?? false),
            'created_at' => ApiResponse::iso($this->created_at),
            'updated_at' => ApiResponse::iso($this->updated_at),
        ];
    }
}
