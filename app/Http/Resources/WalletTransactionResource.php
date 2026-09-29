<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'wallet_id' => $this->wallet_id === null ? null : (int) $this->wallet_id,
            'amount' => ApiResponse::money($this->amount),
            'type' => $this->type,
            'operation' => $this->operation ?? $this->type,
            'description' => $this->description ?? null,
            'reference_type' => $this->reference_type ?? null,
            'reference_id' => $this->reference_id === null ? null : (int) $this->reference_id,
            'balance_before' => ApiResponse::money($this->balance_before),
            'balance_after' => ApiResponse::money($this->balance_after),
            'status' => $this->status ?? null,
            'created_at' => ApiResponse::iso($this->created_at),
        ];
    }
}
