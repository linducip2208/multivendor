<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'order_number' => $this->order_number, 'status' => $this->order_status, 'payment_status' => $this->payment_status,
            'subtotal' => (float) $this->sub_total, 'tax' => (float) $this->tax, 'shipping_cost' => (float) $this->shipping_cost, 'discount' => (float) $this->coupon_discount + (float) $this->discount, 'total' => (float) $this->total,
            'courier' => $this->shipping_method, 'service' => $this->shipping_service, 'tracking' => $this->shipping_tracking_id, 'created_at' => $this->created_at,
            'shop' => new ShopPublicResource($this->whenLoaded('shop')),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => ['id' => $item->id, 'quantity' => $item->quantity, 'price' => (float) $item->price, 'product' => ['id' => $item->product?->id, 'name' => $item->product?->name, 'slug' => $item->product?->slug, 'thumbnail' => $item->product?->thumbnail]])),
            'status_history' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($history) => ['status' => $history->status, 'note' => $history->note, 'at' => $history->created_at]))];
    }
}
