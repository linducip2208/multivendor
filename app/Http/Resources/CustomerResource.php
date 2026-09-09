<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->when($request->user()?->id === $this->id, $this->email),
            'phone' => $this->when($request->user()?->id === $this->id, $this->phone), 'avatar' => $this->avatar,
            'wallet_balance' => $this->when($request->user()?->id === $this->id, fn () => (float) ($this->wallet?->balance ?? 0))];
    }
}
