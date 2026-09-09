<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderWorkflowService
{
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'canceled'],
        'confirmed' => ['processing', 'canceled'],
        'processing' => ['shipped', 'canceled'],
        'shipped' => ['delivered'],
        'delivered' => [], 'canceled' => [], 'returned' => [], 'failed' => [],
    ];

    public function confirm(Order $order, ?int $actorId = null, ?string $note = null): Order
    {
        return $this->transition($order, 'confirmed', $actorId, $note);
    }

    public function process(Order $order, ?int $actorId = null, ?string $note = null): Order
    {
        return $this->transition($order, 'processing', $actorId, $note);
    }

    public function ship(Order $order, ?int $actorId = null, ?string $tracking = null, ?string $note = null): Order
    {
        return $this->transition($order, 'shipped', $actorId, $note, $tracking);
    }

    public function deliver(Order $order, ?int $actorId = null, ?string $note = null): Order
    {
        return $this->transition($order, 'delivered', $actorId, $note);
    }

    public function cancel(Order $order, ?int $actorId = null, ?string $reason = null): Order
    {
        return $this->transition($order, 'canceled', $actorId, $reason);
    }

    private function transition(Order $order, string $to, ?int $actorId, ?string $note, ?string $tracking = null): Order
    {
        return DB::transaction(function () use ($order, $to, $actorId, $note, $tracking) {
            $order = Order::with(['items.product', 'items.variant', 'shop.vendor.wallet', 'customer'])->lockForUpdate()->findOrFail($order->id);
            if ($order->order_status === $to) {
                return $order;
            }
            if (! in_array($to, self::TRANSITIONS[$order->order_status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "Transisi {$order->order_status} ke {$to} tidak diizinkan."]);
            }
            if (in_array($to, ['confirmed', 'processing', 'shipped', 'delivered'], true) && $order->payment_status !== 'paid') {
                throw ValidationException::withMessages(['payment' => 'Pesanan belum dibayar.']);
            }
            $fields = ['order_status' => $to, "{$to}_at" => now()];
            if ($to === 'canceled') {
                $fields['cancel_reason'] = $note;
            }
            if ($tracking) {
                $fields['shipping_tracking_id'] = $tracking;
            }
            $order->update($fields);
            $order->statusHistory()->create(['status' => $to, 'changed_by' => $actorId, 'note' => $note]);

            if ($to === 'canceled') {
                app(OrderService::class)->restoreStock($order);
            }
            if ($to === 'delivered') {
                app(OrderService::class)->settleDelivered($order);
            }
            if ($to === 'confirmed') {
                app(NotificationService::class)->sendOrderConfirmation($order);
            }
            if ($to === 'shipped') {
                app(NotificationService::class)->sendOrderShipped($order);
            }

            return $order->fresh();
        });
    }
}
