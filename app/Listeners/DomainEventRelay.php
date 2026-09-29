<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Order\OrderStateMachine;
use App\Enums\PaymentStatus;
use App\Events\ConversationMessageSent;
use App\Events\CouponRedeemed;
use App\Events\CustomerRegistered;
use App\Events\NewVendorRegistered;
use App\Events\OrderCancelled;
use App\Events\OrderCompleted;
use App\Events\OrderConfirmed;
use App\Events\OrderCreated;
use App\Events\OrderDelivered;
use App\Events\OrderPacked;
use App\Events\OrderPaid;
use App\Events\OrderShipped;
use App\Events\PaymentCreated;
use App\Events\PaymentFailed;
use App\Events\PaymentPaid;
use App\Events\PayoutCompleted;
use App\Events\ProductCreated;
use App\Events\ProductRejected;
use App\Events\ProductUpdated;
use App\Events\RefundCompleted;
use App\Events\RefundCreated;
use App\Events\ReturnCompleted;
use App\Events\ReturnRequested;
use App\Events\ReviewCreated;
use App\Events\StockLow;
use App\Events\StockOut;
use App\Events\SupportTicketOpened;
use App\Events\VendorApproved;
use App\Events\VendorSuspended;
use App\Events\WithdrawalApproved;
use App\Events\WithdrawalRequested;
use App\Models\CouponUsage;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\OrderStatusHistory;
use App\Models\PaymentGroup;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Refund;
use App\Models\Shop;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\VendorWithdrawRequest;

/**
 * Bridges persisted state changes onto the domain event catalogue.
 *
 * Every natural call site for the catalogue lives in a service or controller
 * this agent does not own, so the dispatch happens here instead: the events
 * are raised from the model hooks those flows already run inside their own
 * transaction, and the downstream fan-out listener is queued after commit.
 *
 * OrderStateMachine::effectsFor() stays the single source of truth for order
 * transitions. {@see self::ORDER_EVENTS} only fills the states it does not yet
 * declare an event arm for.
 */
final class DomainEventRelay
{
    /** @var array<string, class-string> */
    private const ORDER_EVENTS = [
        'confirmed' => OrderConfirmed::class,
        'packed' => OrderPacked::class,
        'shipped' => OrderShipped::class,
        'delivered' => OrderDelivered::class,
        'completed' => OrderCompleted::class,
        'canceled' => OrderCancelled::class,
    ];

    /** @var list<string> Columns that must not raise a product.updated event. */
    private const PRODUCT_QUIET_COLUMNS = [
        'current_stock', 'sold_count', 'view_count', 'rating_average', 'rating_count',
        'updated_at', 'request_status', 'approved_at', 'approved_by', 'seo_score',
    ];

    public function onOrderCreated(Order $order): void
    {
        OrderCreated::dispatch($order);
    }

    public function onOrderStatusCreated(OrderStatusHistory $history): void
    {
        $order = Order::find($history->order_id);

        if (! $order) {
            return;
        }

        $this->dispatchOrderEvent((string) $history->status, $order, $history->changed_by);
        $this->dispatchReturnEvent((string) $history->status, $history, $order);
    }

    public function onPaymentCreated(PaymentGroup $payment): void
    {
        PaymentCreated::dispatch($payment);
    }

    public function onPaymentUpdated(PaymentGroup $payment): void
    {
        if (! $payment->wasChanged('status')) {
            return;
        }

        $status = PaymentStatus::tryFrom((string) $payment->status);

        if ($status === PaymentStatus::Paid) {
            PaymentPaid::dispatch($payment, $payment->gateway_reference);

            foreach ($payment->orders()->get() as $order) {
                if ((string) $order->payment_status === PaymentStatus::Paid->value) {
                    OrderPaid::dispatch($order);
                }
            }

            return;
        }

        if ($status === PaymentStatus::Failed || $status === PaymentStatus::Expired) {
            PaymentFailed::dispatch($payment, $status->value);
        }
    }

    public function onRefundCreated(Refund $refund): void
    {
        RefundCreated::dispatch($refund);
    }

    public function onRefundUpdated(Refund $refund): void
    {
        if ($refund->wasChanged('status') && $refund->status === Refund::STATUS_SUCCEEDED) {
            RefundCompleted::dispatch($refund);
        }
    }

    public function onProductCreated(Product $product): void
    {
        ProductCreated::dispatch($product);
    }

    public function onProductUpdated(Product $product): void
    {
        $this->relayStock($product);

        if ($product->wasChanged('request_status') && (string) $product->request_status === 'rejected') {
            ProductRejected::dispatch($product);
        }

        $changed = array_values(array_diff(array_keys($product->getChanges()), self::PRODUCT_QUIET_COLUMNS));

        if ($changed !== []) {
            ProductUpdated::dispatch($product, $changed);
        }
    }

    public function onReviewCreated(ProductReview $review): void
    {
        ReviewCreated::dispatch($review);
    }

    public function onCustomerCreated(User $user): void
    {
        if ((string) $user->role === 'customer') {
            CustomerRegistered::dispatch($user);
        }
    }

    public function onCouponUsageCreated(CouponUsage $usage): void
    {
        CouponRedeemed::dispatch($usage);
    }

    public function onWithdrawalCreated(VendorWithdrawRequest $withdraw): void
    {
        WithdrawalRequested::dispatch($withdraw);
    }

    public function onWithdrawalUpdated(VendorWithdrawRequest $withdraw): void
    {
        if (! $withdraw->wasChanged('status')) {
            return;
        }

        $status = (string) $withdraw->status;

        if ($status === 'approved') {
            WithdrawalApproved::dispatch($withdraw, $withdraw->approved_by);
        }

        if ($status === 'completed') {
            PayoutCompleted::dispatch($withdraw);
        }
    }

    public function onShopCreated(Shop $shop): void
    {
        NewVendorRegistered::dispatch($shop);
    }

    public function onShopUpdated(Shop $shop): void
    {
        if (! $shop->wasChanged('status')) {
            return;
        }

        $status = (string) $shop->status;

        if (in_array($status, ['active', 'approved'], true)) {
            VendorApproved::dispatch($shop);
        }

        if (in_array($status, ['suspended', 'banned', 'inactive'], true)) {
            VendorSuspended::dispatch($shop, $shop->rejection_reason);
        }
    }

    public function onMessageCreated(Message $message): void
    {
        if ($message->is_internal_note === true) {
            return;
        }

        ConversationMessageSent::dispatch($message);
    }

    public function onSupportTicketCreated(SupportTicket $ticket): void
    {
        SupportTicketOpened::dispatch($ticket);
    }

    private function dispatchOrderEvent(string $status, Order $order, ?int $actorId): void
    {
        $class = $this->orderEventClass($status);

        if ($class === null) {
            return;
        }

        match ($class) {
            OrderConfirmed::class => OrderConfirmed::dispatch($order, $actorId),
            OrderPacked::class => OrderPacked::dispatch($order, $actorId),
            OrderShipped::class => OrderShipped::dispatch(
                $order,
                $order->shipping_tracking_id,
                $order->shipping_service,
                $actorId,
            ),
            OrderDelivered::class => OrderDelivered::dispatch($order, $actorId),
            OrderCompleted::class => OrderCompleted::dispatch($order, $actorId),
            OrderCancelled::class => OrderCancelled::dispatch($order, $order->cancel_reason, $actorId),
            default => null,
        };
    }

    private function dispatchReturnEvent(string $status, OrderStatusHistory $history, Order $order): void
    {
        if ($status === 'return_requested') {
            $return = OrderReturn::where('order_id', $order->getKey())
                ->whereNull('decided_at')
                ->latest('id')
                ->first();

            if ($return) {
                ReturnRequested::dispatch($return, $history->changed_by);
            }

            return;
        }

        if ($status === 'returned') {
            $return = OrderReturn::where('order_id', $order->getKey())
                ->where('status', 'received')
                ->latest('id')
                ->first()
                ?? OrderReturn::where('order_id', $order->getKey())->latest('id')->first();

            if ($return) {
                ReturnCompleted::dispatch($return, $history->changed_by);
            }
        }
    }

    /** @return class-string|null */
    private function orderEventClass(string $status): ?string
    {
        $declared = $this->stateMachineEventClass($status);

        if ($declared !== null && in_array($declared, self::ORDER_EVENTS, true)) {
            return $declared;
        }

        return self::ORDER_EVENTS[$status] ?? null;
    }

    /** @return class-string|null */
    private function stateMachineEventClass(string $status): ?string
    {
        foreach (OrderStateMachine::effectsFor($status) as $effect) {
            if (($effect[0] ?? null) !== 'event' || ! is_string($effect[1] ?? null)) {
                continue;
            }

            $class = 'App\\Events\\'.$effect[1];

            if (class_exists($class)) {
                return $class;
            }
        }

        return null;
    }

    private function relayStock(Product $product): void
    {
        if (! $product->wasChanged('current_stock')) {
            return;
        }

        $previous = (int) $product->getOriginal('current_stock');
        $remaining = (int) $product->current_stock;
        $threshold = (int) $product->low_stock_threshold;

        if ($remaining <= 0) {
            if ($previous > 0) {
                StockOut::dispatch($product);
            }

            return;
        }

        if ($threshold > 0 && $previous > $threshold && $remaining <= $threshold) {
            StockLow::dispatch($product, $remaining, $threshold);
        }
    }
}
