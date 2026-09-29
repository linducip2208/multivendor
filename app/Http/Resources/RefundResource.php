<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RefundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'refund_number' => $this->refund_number ?? null,
            'order_id' => $this->order_id === null ? null : (int) $this->order_id,
            'order_item_id' => $this->order_item_id === null ? null : (int) $this->order_item_id,
            'payment_group_id' => $this->payment_group_id === null ? null : (int) $this->payment_group_id,
            'provider_id' => $this->provider_id === null ? null : (int) $this->provider_id,
            'gateway_refund_id' => $this->gateway_refund_id ?? null,
            'amount' => ApiResponse::money($this->amount),
            'currency' => $this->currency ?? 'IDR',
            'reason' => $this->reason ?? null,
            'status' => $this->status,
            'attempts' => (int) ($this->attempts ?? 0),
            'failure_reason' => $this->failure_reason ?? null,
            'last_attempt_at' => ApiResponse::iso($this->last_attempt_at),
            'succeeded_at' => ApiResponse::iso($this->succeeded_at),
            'created_at' => ApiResponse::iso($this->created_at),
            'updated_at' => ApiResponse::iso($this->updated_at),
        ];
    }
}
