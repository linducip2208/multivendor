<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'shop_id' => $this->shop_id === null ? null : (int) $this->shop_id,
            'code' => $this->code,
            'title' => $this->title ?? null,
            'coupon_type' => $this->coupon_type ?? null,
            'discount_value' => ApiResponse::money($this->discount_value),
            'min_purchase' => ApiResponse::money($this->min_purchase),
            'max_discount' => ApiResponse::moneyOrNull($this->max_discount),
            'start_date' => ApiResponse::iso($this->start_date),
            'end_date' => ApiResponse::iso($this->end_date),
            'usage_limit' => $this->usage_limit === null ? null : (int) $this->usage_limit,
            'usage_per_customer' => $this->usage_per_customer === null ? null : (int) $this->usage_per_customer,
            'usage_count' => (int) ($this->usage_count ?? 0),
            'status' => (bool) ($this->status ?? false),
            'is_valid' => $request->user() === null ? (bool) ($this->status ?? false) : $this->isValid($request->user()->getAuthIdentifier()),
            'created_at' => ApiResponse::iso($this->created_at),
            'updated_at' => ApiResponse::iso($this->updated_at),
        ];
    }
}
