<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Order\OrderStateMachine;
use App\Events\NewVendorRegistered;
use App\Events\OrderCancelled;
use App\Events\OrderCompleted;
use App\Events\OrderDelivered;
use App\Events\OrderShipped;
use App\Events\WithdrawalApproved;
use App\Mail\WithdrawApprovedMail;
use App\Models\Order;
use App\Models\Shop;
use App\Models\Transaction;
use App\Models\VendorWithdrawRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Reacts to the order lifecycle events.
 *
 * It deliberately never runs a `service` side effect. OrderWorkflowService
 * already settles, restores stock and sends the customer notification inside
 * the transition transaction; running any of them again here is exactly the
 * double-settle this listener used to cause. OrderStateMachine::effectsFor()
 * stays the single source of truth: the listener only checks that the
 * declared effects actually landed and reports a gap when they did not.
 */
class OrderEventListener implements ShouldQueue, ShouldQueueAfterCommit
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function viaQueue(): string
    {
        return 'default';
    }

    public function handle(object $event): void
    {
        if ($event instanceof OrderShipped
            || $event instanceof OrderDelivered
            || $event instanceof OrderCompleted
            || $event instanceof OrderCancelled) {
            $this->onOrderState($event->order);

            return;
        }

        if ($event instanceof WithdrawalApproved) {
            $this->onWithdrawalApproved($event->withdraw, $event->actorId);

            return;
        }

        if ($event instanceof NewVendorRegistered) {
            $this->onVendorRegistered($event->shop);
        }
    }

    private function onOrderState(Order $order): void
    {
        $state = (string) $order->order_status;

        foreach (OrderStateMachine::effectsFor($state) as $effect) {
            if (($effect[0] ?? null) !== 'service') {
                continue;
            }

            if (($effect[1] ?? null) === 'settle_delivered') {
                $this->assertSettled($order);
            }

            if (($effect[1] ?? null) === 'restore_stock') {
                $this->assertStockRestored($order);
            }
        }
    }

    private function assertSettled(Order $order): void
    {
        $exists = Transaction::query()
            ->where('transaction_id', 'TRX-'.$order->order_number)
            ->exists();

        if ($exists) {
            return;
        }

        Log::warning('order.settlement_missing', [
            'order_id' => (int) $order->getKey(),
            'order_number' => (string) $order->order_number,
            'order_status' => (string) $order->order_status,
        ]);
    }

    private function assertStockRestored(Order $order): void
    {
        if ($order->stock_released_at !== null) {
            return;
        }

        Log::warning('order.stock_release_missing', [
            'order_id' => (int) $order->getKey(),
            'order_number' => (string) $order->order_number,
            'order_status' => (string) $order->order_status,
        ]);
    }

    private function onWithdrawalApproved(VendorWithdrawRequest $withdraw, ?int $actorId): void
    {
        $key = 'listener:withdrawal-approved:'.$withdraw->getKey().':'.($actorId ?? 0);

        if (! Cache::add($key, now()->getTimestamp(), now()->addDay())) {
            return;
        }

        $vendor = $withdraw->vendor;

        if (! $vendor?->email) {
            return;
        }

        Mail::to($vendor->email)->queue(new WithdrawApprovedMail($withdraw));
    }

    private function onVendorRegistered(Shop $shop): void
    {
        Log::info('vendor.registered', [
            'shop_id' => (int) $shop->getKey(),
            'vendor_id' => $shop->vendor_id,
        ]);
    }
}
