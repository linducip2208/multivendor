<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Order\OrderStateMachine;
use App\Enums\OrderStatus;
use App\Jobs\SendOrderNotification;
use App\Models\Order;
use App\Models\OrderReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderWorkflowService
{
    private const TIMESTAMP_COLUMNS = [
        'confirmed' => 'confirmed_at',
        'processing' => 'processing_at',
        'packed' => 'packed_at',
        'shipped' => 'shipped_at',
        'delivered' => 'delivered_at',
        'completed' => 'completed_at',
        'canceled' => 'canceled_at',
        'returned' => 'returned_at',
        'refunded' => 'refunded_at',
    ];

    private const NOTIFYING = [
        'confirmed' => 'order_confirmed',
        'packed' => 'order_packed',
        'shipped' => 'order_shipped',
        'delivered' => 'order_delivered',
        'completed' => 'order_completed',
        'canceled' => 'order_cancelled',
        'return_requested' => 'return_requested',
        'returned' => 'returned',
        'refund_pending' => 'refund_pending',
        'refunded' => 'refunded',
    ];

    public function confirm(Order $order, ?int $actorId = null, ?string $note = null): Order
    {
        return $this->transition($order, OrderStatus::Confirmed, $actorId, $note);
    }

    public function process(Order $order, ?int $actorId = null, ?string $note = null): Order
    {
        return $this->transition($order, OrderStatus::Processing, $actorId, $note);
    }

    public function pack(Order $order, ?int $actorId = null, ?string $note = null): Order
    {
        return $this->transition($order, OrderStatus::Packed, $actorId, $note);
    }

    public function ship(Order $order, ?int $actorId = null, ?string $tracking = null, ?string $note = null): Order
    {
        $fresh = Order::whereKey($order->getKey())->firstOrFail();

        if (! $fresh->hasSettledPreorder()) {
            throw ValidationException::withMessages(['preorder' => 'Pre-order belum dilunasi. Selesaikan pelunasan sebelum pengiriman.']);
        }

        return $this->transition($order, OrderStatus::Shipped, $actorId, $note, $tracking);
    }

    public function deliver(Order $order, ?int $actorId = null, ?string $note = null): Order
    {
        return $this->transition($order, OrderStatus::Delivered, $actorId, $note);
    }

    public function complete(Order $order, ?int $actorId = null, ?string $note = null): Order
    {
        return $this->transition($order, OrderStatus::Completed, $actorId, $note);
    }

    public function cancel(Order $order, ?int $actorId = null, ?string $reason = null): Order
    {
        return $this->transition($order, OrderStatus::Cancelled, $actorId, $reason);
    }

    public function requestReturn(Order $order, ?int $actorId = null, ?string $reason = null, ?int $orderItemId = null): OrderReturn
    {
        return DB::transaction(function () use ($order, $actorId, $reason, $orderItemId): OrderReturn {
            $order = Order::with(['items'])->lockForUpdate()->findOrFail($order->id);
            OrderStateMachine::assertCanTransition($order->order_status, OrderStatus::ReturnRequested, $order->payment_status);

            $existing = OrderReturn::where('order_id', $order->id)
                ->when($orderItemId !== null, fn ($query) => $query->where('order_item_id', $orderItemId))
                ->whereNull('decided_at')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $item = $orderItemId !== null ? $order->items->firstWhere('id', $orderItemId) : null;

            $structured = OrderReturn::normalizeReason($reason);

            $return = OrderReturn::create([
                'rma_number' => $this->rmaNumber(),
                'order_id' => $order->id,
                'order_item_id' => $orderItemId,
                'reason' => $structured,
                'description' => $reason,
                'status' => 'requested',
                'amount' => $item ? (string) $item->sub_total : (string) $order->total,
            ]);

            $this->applyProjection($order, OrderStatus::ReturnRequested, $actorId, 'Permintaan retur diterima.');
            $this->notify($order, OrderStatus::ReturnRequested);

            return $return;
        }, 3);
    }

    public function markReturnRequested(Order $order, ?int $actorId = null, ?string $note = null): Order
    {
        return $this->transition($order, OrderStatus::ReturnRequested, $actorId, $note);
    }

    public function markReturned(Order $order, ?int $actorId = null, ?string $note = null): Order
    {
        return $this->transition($order, OrderStatus::Returned, $actorId, $note);
    }

    public function markRefundPending(Order $order, ?int $actorId = null, ?string $note = null): Order
    {
        return $this->transition($order, OrderStatus::RefundPending, $actorId, $note);
    }

    public function markRefunded(Order $order, ?int $actorId = null, ?string $note = null): Order
    {
        return $this->transition($order, OrderStatus::Refunded, $actorId, $note);
    }

    /**
     * Pelunasan pre-order sebelum kirim. Atomik via lockForUpdate dalam
     * transaksi; idempoten bila sudah lunas (kembalikan order apa adanya).
     */
    public function recordPreorderSettlement(Order $order, ?int $actorId = null, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $actorId, $note) {
            $locked = Order::lockForUpdate()->findOrFail($order->getKey());

            if (! $locked->isPreorder()) {
                throw ValidationException::withMessages(['preorder' => 'Order ini bukan pre-order.']);
            }

            if ($locked->hasSettledPreorder()) {
                return $locked;
            }

            $locked->forceFill([
                'preorder_remaining' => 0,
                'preorder_settled_at' => now(),
                'payment_status' => 'paid',
            ])->save();

            $locked->statusHistory()->create([
                'status' => $locked->order_status,
                'changed_by' => $actorId,
                'note' => $note ?? 'Pelunasan pre-order diterima.',
            ]);

            return $locked->fresh();
        }, 3);
    }

    /**
     * Bulk fulfillment: ubah status massal per order dalam transaksi
     * masing-masing (atomicity per order), kumpulkan hasil OK/gagal.
     *
     * @param  list<int>  $orderIds
     * @return array{ok: list<int>, fail: array<int,string>}
     */
    public function bulkTransition(array $orderIds, OrderStatus $to, ?int $actorId = null, ?string $note = null): array
    {
        $ok = [];
        $fail = [];

        foreach (array_values(array_unique(array_map('intval', $orderIds))) as $id) {
            if ($id <= 0) {
                continue;
            }

            try {
                $order = Order::whereKey($id)->firstOrFail();

                match ($to) {
                    OrderStatus::Confirmed => $this->confirm($order, $actorId, $note),
                    OrderStatus::Processing => $this->process($order, $actorId, $note),
                    OrderStatus::Packed => $this->pack($order, $actorId, $note),
                    OrderStatus::Shipped => $this->ship($order, $actorId, null, $note),
                    OrderStatus::Delivered => $this->deliver($order, $actorId, $note),
                    OrderStatus::Completed => $this->complete($order, $actorId, $note),
                    OrderStatus::Cancelled => $this->cancel($order, $actorId, $note),
                    default => $this->transition($order, $to, $actorId, $note),
                };

                $ok[] = $id;
            } catch (\Throwable $e) {
                $fail[$id] = $e instanceof ValidationException
                    ? (string) collect($e->errors())->flatten()->first()
                    : 'Gagal memproses pesanan.';
            }
        }

        return ['ok' => $ok, 'fail' => $fail];
    }

    /**
     * Project the order state onto a return stage that another flow owns.
     *
     * RefundWorkflowService already writes `refund.requested` / `refund.approved`
     * to the audit trail for the same business event, so this entry point
     * deliberately records the state change and the status history only.
     */
    public function projectStage(Order $order, OrderStatus $to, ?int $actorId = null, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $to, $actorId, $note) {
            $order = Order::lockForUpdate()->findOrFail($order->getKey());

            if ($order->order_status === $to->stored()) {
                return $order;
            }

            OrderStateMachine::assertCanTransition($order->order_status, $to, $order->payment_status);

            $this->applyProjection($order, $to, $actorId, $note);
            $this->syncReturnRecords($order, $to, $actorId);
            $this->notify($order, $to);

            return $order->fresh();
        }, 3);
    }

    private function transition(
        Order $order,
        OrderStatus $to,
        ?int $actorId,
        ?string $note = null,
        ?string $tracking = null,
    ): Order {
        return DB::transaction(function () use ($order, $to, $actorId, $note, $tracking) {
            $order = Order::with(['items.product', 'items.variant', 'shop.vendor.wallet', 'customer', 'paymentGroup'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($order->order_status === $to->stored()) {
                return $order;
            }

            OrderStateMachine::assertCanTransition($order->order_status, $to, $order->payment_status);

            $previous = $order->order_status;
            $this->applyProjection($order, $to, $actorId, $note, $tracking);

            app(AuditLogger::class)->log('order.status_changed', $order, [
                'order_status' => $previous,
            ], [
                'order_status' => $to->stored(),
                'note' => $note,
            ], $actorId);

            if ($to === OrderStatus::Cancelled) {
                app(OrderService::class)->restoreStock($order);
            }

            if ($to === OrderStatus::Delivered || $to === OrderStatus::Completed) {
                app(OrderService::class)->settleDelivered($order);
            }

            $this->syncReturnRecords($order, $to, $actorId);
            $this->notify($order, $to);

            return $order->fresh();
        }, 3);
    }

    private function syncReturnRecords(Order $order, OrderStatus $to, ?int $actorId): void
    {
        if ($to === OrderStatus::ReturnRequested) {
            OrderReturn::where('order_id', $order->id)
                ->where('status', 'requested')
                ->update(['status' => 'approved', 'decided_by' => $actorId, 'decided_at' => now()]);
        }

        if ($to === OrderStatus::Returned) {
            OrderReturn::where('order_id', $order->id)
                ->where('status', 'approved')
                ->update(['status' => 'received', 'decided_by' => $actorId, 'decided_at' => now()]);
        }
    }

    private function notify(Order $order, OrderStatus $to): void
    {
        $event = self::NOTIFYING[$to->stored()] ?? null;

        if ($event !== null) {
            SendOrderNotification::dispatch((int) $order->getKey(), $event)->afterCommit();
        }
    }

    private function applyProjection(
        Order $order,
        OrderStatus $to,
        ?int $actorId,
        ?string $note,
        ?string $tracking = null,
    ): void {
        $attributes = ['order_status' => $to->stored()];
        $timestamp = self::TIMESTAMP_COLUMNS[$to->stored()] ?? null;

        if ($timestamp !== null) {
            $attributes[$timestamp] = now();
        }

        if ($to === OrderStatus::Cancelled) {
            $attributes['cancel_reason'] = $note;
        }

        if ($to === OrderStatus::ReturnRequested || $to === OrderStatus::Returned) {
            $attributes['return_reason'] = $note;
        }

        if ($tracking !== null && $tracking !== '') {
            $attributes['shipping_tracking_id'] = $tracking;
        }

        $order->forceFill($attributes)->save();

        $order->statusHistory()->create([
            'status' => $to->stored(),
            'changed_by' => $actorId,
            'note' => $note,
        ]);
    }

    private function rmaNumber(): string
    {
        do {
            $number = 'RMA-'.now()->format('Ymd').'-'.strtoupper(Str::random(8));
        } while (OrderReturn::where('rma_number', $number)->exists());

        return $number;
    }
}
