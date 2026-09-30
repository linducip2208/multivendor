<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendOrderNotification;
use App\Models\DeliveryManEarning;
use App\Models\LoyaltyPoint;
use App\Models\LoyaltyTransaction;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Shop;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Finance\LedgerService;
use App\Support\Money;

/** Settlement and inventory side effects. State transitions belong to OrderWorkflowService. */
class OrderService
{
    public function settleDelivered(Order $order): void
    {
        $order = Order::with(['shop.vendor.wallet', 'customer', 'deliveryMan.wallet', 'paymentGroup'])
            ->lockForUpdate()
            ->findOrFail($order->id);

        $shop = $order->shop;

        if (! $shop) {
            return;
        }

        $subTotal = Money::of($order->sub_total);
        $tax = Money::of($order->tax);
        $commission = $this->commissionFor($shop, $subTotal);
        $vendorDiscount = $this->vendorDiscountShare($order);
        $platformTake = $commission->add($vendorDiscount);
        $vendorAmount = $subTotal->add($tax)->subtract($platformTake)->maxZero();

        Transaction::updateOrCreate(
            ['transaction_id' => 'TRX-'.$order->order_number],
            [
                'payment_group_id' => $order->payment_group_id, 'order_id' => $order->id, 'customer_id' => $order->customer_id,
                'shop_id' => $order->shop_id, 'amount' => $order->total, 'admin_commission' => $platformTake->toDecimal(),
                'vendor_amount' => $vendorAmount->toDecimal(), 'payment_method' => $order->payment_method ?: 'transfer',
                'status' => 'success', 'paid_at' => $order->paymentGroup?->paid_at ?? now(),
            ],
        );

        if ($shop->vendor?->wallet && $vendorAmount->isPositive()) {
            $shop->vendor->wallet->credit(
                $vendorAmount->toFloat(),
                'Settlement order #'.$order->order_number,
                'order_settlement',
                (int) $order->getKey(),
                'settlement:'.$order->getKey(),
            );
        }

        $this->postSettlement($order, $platformTake, $vendorAmount, $tax);

        if ($order->customer) {
            $this->awardLoyaltyOnce($order);
        }

        if ($order->delivery_man_id) {
            $this->creditDeliveryOnce($order);
        }

        SendOrderNotification::dispatch((int) $order->getKey(), 'order_delivered')->afterCommit();
    }

    public function restoreStock(Order $order): void
    {
        $order = Order::with(['items.product', 'items.variant'])->lockForUpdate()->findOrFail($order->id);

        if ($order->stock_released_at) {
            return;
        }

        foreach ($order->items as $item) {
            if ($item->product_variant_id && $item->variant) {
                $item->variant->increment('stock', (int) $item->quantity);
            } elseif ($item->product) {
                $item->product->increment('current_stock', (int) $item->quantity);
            }
        }

        $order->forceFill(['stock_released_at' => now()])->save();
    }

    /** Backwards-compatible facade for legacy callers. */
    public function complete(Order $order): void
    {
        app(OrderWorkflowService::class)->deliver($order, auth()->id());
    }

    public function cancel(Order $order): void
    {
        app(OrderWorkflowService::class)->cancel($order, auth()->id());
    }

    public function confirm(Order $order): void
    {
        app(OrderWorkflowService::class)->confirm($order, auth()->id());
    }

    public function ship(Order $order, ?string $trackingId = null): void
    {
        app(OrderWorkflowService::class)->ship($order, auth()->id(), $trackingId);
    }

    /**
     * Settlement deterministik order induk multi-vendor: tiap child disettle
     * via jalur existing (idempoten), lalu verifikasi jumlah = total induk.
     *
     * @return array{children:int, vendor_total:float, balanced:bool}
     */
    public function settleSuborders(Order $parent): array
    {
        $children = $parent->children()->lockForUpdate()->get();

        if ($children->isEmpty()) {
            $this->settleDelivered($parent->fresh() ?? $parent);

            return ['children' => 0, 'vendor_total' => (float) $parent->total, 'balanced' => true];
        }

        $vendorTotal = 0.0;

        foreach ($children as $child) {
            $this->settleDelivered($child);
            $vendorTotal += (float) $child->fresh()->total;
        }

        $allocation = $parent->suborderAllocation();

        return [
            'children' => $children->count(),
            'vendor_total' => round($vendorTotal, 2),
            'balanced' => abs($vendorTotal - (float) ($allocation['totals']['grand_total'] ?? $vendorTotal)) < 0.01,
        ];
    }

    private function commissionFor(Shop $shop, Money $subTotal): Money
    {
        $value = (float) ($shop->commission_value ?? 0);

        if ($shop->commission_type === 'percentage') {
            return $subTotal->multiply($value / 100)->maxZero();
        }

        return Money::of($value)->maxZero()->min($subTotal);
    }

    private function vendorDiscountShare(Order $order): Money
    {
        $discount = Money::of($order->discount)->add($order->coupon_discount);

        if (! $discount->isPositive()) {
            return Money::zero();
        }

        $bearer = (string) (SystemSetting::get('coupon_bearer', 'admin') ?: 'admin');

        return match ($bearer) {
            'vendor' => $discount,
            'split' => $discount->multiply(((float) (SystemSetting::get('coupon_vendor_share', 50) ?: 50)) / 100),
            default => Money::zero(),
        };
    }

    private function postSettlement(Order $order, Money $platformTake, Money $vendorAmount, Money $tax): void
    {
        if (LedgerEntry::where('entry_type', 'order_settlement')->where('order_id', $order->getKey())->exists()) {
            return;
        }

        $taxesRemitted = $tax->isPositive() && $vendorAmount->minor >= $tax->minor ? $tax : Money::zero();
        $vendorPayable = $taxesRemitted->isPositive() ? $vendorAmount->subtract($taxesRemitted) : $vendorAmount;

        app(LedgerService::class)->postOrderSettlement($order, [
            'commission' => $platformTake->toDecimal(),
            'vendor_amount' => $vendorPayable->maxZero()->toDecimal(),
            'tax' => $taxesRemitted->toDecimal(),
        ]);
    }

    private function awardLoyaltyOnce(Order $order): void
    {
        if (LoyaltyTransaction::where('customer_id', $order->customer_id)
            ->where('reference_type', 'order')
            ->where('reference_id', $order->id)
            ->where('type', 'earn')
            ->exists()) {
            return;
        }

        $rate = max(1, (int) (SystemSetting::get('loyalty_earn_rate', '1000') ?: 1000));
        $points = intdiv(Money::of($order->total)->minor, $rate * Money::SCALE_DEFAULT);

        if ($points <= 0) {
            return;
        }

        $point = LoyaltyPoint::firstOrCreate(['customer_id' => $order->customer_id], ['points' => 0]);
        $point->increment('points', $points);

        LoyaltyTransaction::create([
            'customer_id' => $order->customer_id, 'points' => $points, 'type' => 'earn',
            'description' => 'Pembelian #'.$order->order_number, 'reference_type' => 'order', 'reference_id' => $order->id,
        ]);
    }

    private function creditDeliveryOnce(Order $order): void
    {
        $delivery = User::with('wallet')->find($order->delivery_man_id);
        $wallet = $delivery?->wallet;

        if (! $wallet) {
            return;
        }

        $rate = (float) (SystemSetting::get('delivery_earning_rate', '80') ?: 80);
        $amount = Money::of($order->shipping_cost)->multiply($rate / 100);

        $earning = DeliveryManEarning::firstOrCreate(
            ['delivery_man_id' => $delivery->id, 'order_id' => $order->id],
            ['amount' => $amount->toDecimal(), 'description' => 'Delivery #'.$order->order_number],
        );

        if ((float) $earning->amount > 0) {
            $wallet->credit((float) $earning->amount, 'Delivery #'.$order->order_number, 'delivery_earning', (int) $earning->getKey(), 'delivery:'.$order->id);
        }
    }
}
