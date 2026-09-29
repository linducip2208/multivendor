<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'order_number' => $this->order_number,
            'status' => $this->order_status,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method ?? null,
            'currency' => $this->currency ?? 'IDR',
            'source' => $this->source ?? null,
            'subtotal' => ApiResponse::money($this->sub_total),
            'tax' => ApiResponse::money($this->tax),
            'shipping_cost' => ApiResponse::money($this->shipping_cost),
            'discount' => ApiResponse::money((float) $this->coupon_discount + (float) $this->discount),
            'coupon_discount' => ApiResponse::money($this->coupon_discount),
            'total' => ApiResponse::money($this->total),
            'refunded_amount' => ApiResponse::money($this->refunded_amount),
            'coupon_code' => $this->coupon_code ?? null,
            'courier' => $this->shipping_method ?? null,
            'service' => $this->shipping_service ?? null,
            'tracking' => $this->shipping_tracking_id ?? null,
            'note' => $this->note ?? null,
            'cancel_reason' => $this->cancel_reason ?? null,
            'customer_id' => $this->customer_id === null ? null : (int) $this->customer_id,
            'shop_id' => $this->shop_id === null ? null : (int) $this->shop_id,
            'parent_order_id' => $this->parent_order_id === null ? null : (int) $this->parent_order_id,
            'shipping_address' => $this->shipping_address ?? null,
            'billing_address' => $this->billing_address ?? null,
            'shop' => new ShopPublicResource($this->whenLoaded('shop')),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item): array => [
                'id' => (int) $item->id,
                'product_id' => (int) $item->product_id,
                'product_variant_id' => $item->product_variant_id === null ? null : (int) $item->product_variant_id,
                'quantity' => (int) $item->quantity,
                'price' => ApiResponse::money($item->price),
                'tax' => ApiResponse::money($item->tax),
                'discount' => ApiResponse::money($item->discount),
                'sub_total' => ApiResponse::money($item->sub_total),
                'is_reviewed' => (bool) $item->is_reviewed,
                'refund_status' => $item->refund_status ?? null,
                'product' => $item->product === null ? null : [
                    'id' => (int) $item->product->id,
                    'name' => $item->product->name ?? null,
                    'slug' => $item->product->slug ?? null,
                    'thumbnail' => $item->product->thumbnail ?? null,
                ],
            ])->all()),
            'shipments' => ShipmentResource::collection($this->whenLoaded('shipments')),
            'refunds' => RefundResource::collection($this->whenLoaded('refunds')),
            'status_history' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($history): array => [
                'id' => (int) $history->id,
                'status' => $history->status,
                'note' => $history->note ?? null,
                'at' => ApiResponse::iso($history->created_at),
            ])->all()),
            'confirmed_at' => ApiResponse::iso($this->confirmed_at),
            'processing_at' => ApiResponse::iso($this->processing_at),
            'packed_at' => ApiResponse::iso($this->packed_at),
            'shipped_at' => ApiResponse::iso($this->shipped_at),
            'delivered_at' => ApiResponse::iso($this->delivered_at),
            'canceled_at' => ApiResponse::iso($this->canceled_at),
            'returned_at' => ApiResponse::iso($this->returned_at),
            'refunded_at' => ApiResponse::iso($this->refunded_at),
            'created_at' => ApiResponse::iso($this->created_at),
            'updated_at' => ApiResponse::iso($this->updated_at),
        ];
    }
}
