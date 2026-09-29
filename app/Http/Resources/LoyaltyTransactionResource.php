<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoyaltyTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'customer_id' => $this->customer_id === null ? null : (int) $this->customer_id,
            'points' => (int) $this->points,
            'type' => $this->type,
            'description' => $this->description ?? null,
            'reference_type' => $this->reference_type ?? null,
            'reference_id' => $this->reference_id === null ? null : (int) $this->reference_id,
            'created_at' => ApiResponse::iso($this->created_at),
        ];
    }
}
