<?php

declare(strict_types=1);

namespace App\Events\Support;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Carbon;

final class OrderSnapshot
{
    /** @return array<string, mixed> */
    public static function summary(Order $order): array
    {
        return [
            'id' => (int) $order->getKey(),
            'order_number' => (string) $order->order_number,
            'status' => (string) $order->order_status,
            'payment_status' => (string) $order->payment_status,
            'payment_method' => (string) $order->payment_method,
            'payment_group_id' => $order->payment_group_id,
            'currency' => (string) ($order->currency ?: 'IDR'),
            'sub_total' => (string) $order->sub_total,
            'discount' => (string) $order->discount,
            'coupon_discount' => (string) $order->coupon_discount,
            'coupon_code' => $order->coupon_code,
            'shipping_cost' => (string) $order->shipping_cost,
            'tax' => (string) $order->tax,
            'total' => (string) $order->total,
            'refunded_amount' => (string) $order->refunded_amount,
            'customer_id' => $order->customer_id,
            'shop_id' => $order->shop_id,
            'delivery_man_id' => $order->delivery_man_id,
            'source' => $order->source,
            'shipping_service' => $order->shipping_service,
            'tracking_number' => $order->shipping_tracking_id,
            'cancel_reason' => $order->cancel_reason,
            'return_reason' => $order->return_reason,
            'note' => $order->note,
            'created_at' => self::iso($order->created_at),
            'confirmed_at' => self::iso($order->confirmed_at),
            'packed_at' => self::iso($order->packed_at),
            'shipped_at' => self::iso($order->shipped_at),
            'delivered_at' => self::iso($order->delivered_at),
            'completed_at' => self::iso($order->completed_at),
            'cancelled_at' => self::iso($order->canceled_at),
            'returned_at' => self::iso($order->returned_at),
            'refunded_at' => self::iso($order->refunded_at),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function items(Order $order): array
    {
        return $order->items->map(fn (OrderItem $item): array => [
            'id' => (int) $item->getKey(),
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'name' => (string) ($item->product?->name),
            'sku' => (string) ($item->product?->sku),
            'variant' => is_array($item->variant_detail) ? $item->variant_detail : null,
            'quantity' => (int) $item->quantity,
            'price' => (string) $item->price,
            'tax' => (string) $item->tax,
            'discount' => (string) $item->discount,
            'sub_total' => (string) $item->sub_total,
            'refund_status' => $item->refund_status,
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    public static function recipient(Order $order): array
    {
        $address = is_array($order->shipping_address) ? $order->shipping_address : [];

        return [
            'name' => $address['name'] ?? $order->customer?->name,
            'phone' => $address['phone'] ?? $order->customer?->phone,
            'address_line' => $address['address'] ?? null,
            'city' => $address['city'] ?? null,
            'province' => $address['province'] ?? null,
            'postal_code' => $address['postal_code'] ?? null,
            'country' => $address['country'] ?? null,
        ];
    }

    public static function iso(mixed $value): ?string
    {
        return $value instanceof Carbon ? $value->toIso8601String() : null;
    }
}
