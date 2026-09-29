<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isSelf = $request->user()?->getAuthIdentifier() === $this->id;

        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'email' => $isSelf ? $this->email : null,
            'phone' => $isSelf ? $this->phone : null,
            'avatar' => $this->avatar ?? null,
            'role' => $isSelf ? ($this->role ?? null) : null,
            'status' => $isSelf ? ($this->status ?? null) : null,
            'referral_code' => $isSelf ? ($this->referral_code ?? null) : null,
            'referred_by' => $isSelf && $this->referred_by !== null ? (int) $this->referred_by : null,
            'wallet_balance' => $isSelf ? ApiResponse::money($this->wallet?->balance ?? 0) : null,
            'wallet_pending_balance' => $isSelf ? ApiResponse::money($this->wallet?->pending_balance ?? 0) : null,
            'loyalty_points' => $isSelf && $this->relationLoaded('loyaltyPoints')
                ? (int) ($this->loyaltyPoints?->points ?? 0)
                : null,
            'created_at' => ApiResponse::iso($this->created_at),
        ];
    }
}
