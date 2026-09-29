<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoyaltyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $points = (int) ($this->points ?? 0);

        return [
            'customer_id' => $this->customer_id === null ? null : (int) $this->customer_id,
            'points' => $points,
            'tier' => $this->tier($points),
            'next_tier' => $this->nextTier($points),
            'points_to_next_tier' => max(0, $this->threshold($this->nextTier($points)) - $points),
            'redeemable_value' => ApiResponse::money($points),
            'transactions' => $this->whenLoaded('transactions', fn () => $this->transactions->map(fn ($transaction): array => [
                'id' => (int) $transaction->id,
                'points' => (int) $transaction->points,
                'type' => $transaction->type,
                'description' => $transaction->description ?? null,
                'reference_type' => $transaction->reference_type ?? null,
                'reference_id' => $transaction->reference_id === null ? null : (int) $transaction->reference_id,
                'created_at' => ApiResponse::iso($transaction->created_at),
            ])->all()),
            'created_at' => ApiResponse::iso($this->created_at),
            'updated_at' => ApiResponse::iso($this->updated_at),
        ];
    }

    private function tier(int $points): string
    {
        return match (true) {
            $points >= 100000 => 'platinum',
            $points >= 25000 => 'gold',
            $points >= 5000 => 'silver',
            default => 'bronze',
        };
    }

    private function nextTier(int $points): ?string
    {
        return match (true) {
            $points >= 100000 => null,
            $points >= 25000 => 'platinum',
            $points >= 5000 => 'gold',
            default => 'silver',
        };
    }

    private function threshold(?string $tier): int
    {
        return match ($tier) {
            'platinum' => 100000,
            'gold' => 25000,
            'silver' => 5000,
            default => 0,
        };
    }
}
