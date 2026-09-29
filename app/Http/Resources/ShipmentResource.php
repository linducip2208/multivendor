<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'order_id' => $this->order_id === null ? null : (int) $this->order_id,
            'provider_id' => $this->provider_id === null ? null : (int) $this->provider_id,
            'courier' => $this->courier ?? null,
            'service' => $this->service ?? null,
            'tracking_number' => $this->tracking_number ?? null,
            'label_url' => $this->label_url ?? null,
            'weight' => ApiResponse::moneyOrNull($this->weight),
            'cost' => ApiResponse::money($this->cost),
            'status' => $this->status ?? null,
            'is_delivered' => $this->isDelivered(),
            'tracking_history' => $this->tracking_history ?? [],
            'shipped_at' => ApiResponse::iso($this->shipped_at),
            'delivered_at' => ApiResponse::iso($this->delivered_at),
            'created_at' => ApiResponse::iso($this->created_at),
            'updated_at' => ApiResponse::iso($this->updated_at),
        ];
    }
}
