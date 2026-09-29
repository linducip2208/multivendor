<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'user_id' => $this->user_id === null ? null : (int) $this->user_id,
            'balance' => ApiResponse::money($this->balance),
            'pending_balance' => ApiResponse::money($this->pending_balance),
            'currency' => 'IDR',
            'created_at' => ApiResponse::iso($this->created_at),
            'updated_at' => ApiResponse::iso($this->updated_at),
        ];
    }
}
