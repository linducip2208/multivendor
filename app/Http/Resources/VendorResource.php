<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorResource extends JsonResource
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
            'role' => $this->role ?? null,
            'status' => $this->status ?? null,
            'joined_at' => ApiResponse::iso($this->created_at),
            'shop' => new ShopPublicResource($this->whenLoaded('shop')),
            'wallet' => new WalletResource($this->whenLoaded('wallet')),
        ];
    }
}
