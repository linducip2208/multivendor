<?php

namespace App\Services;

use App\Models\DeliveryManEarning;
use App\Models\LoyaltyPoint;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;

/** Settlement and inventory side effects. State transitions belong to OrderWorkflowService. */
class OrderService
{
    public function settleDelivered(Order $order): void
    {
        $order = Order::with(['shop.vendor.wallet', 'customer', 'deliveryMan.wallet'])->lockForUpdate()->findOrFail($order->id);
        $shop = $order->shop;
        $commission = $shop->commission_type === 'percentage'
            ? round((float) $order->sub_total * ((float) $shop->commission_value / 100), 2)
            : min((float) $shop->commission_value, (float) $order->sub_total);
        $vendorDiscount = $this->vendorDiscountShare($order);
        // Shipping is pass-through by default and is never assumed to be vendor revenue.
        $vendorAmount = max(0, (float) $order->sub_total + (float) $order->tax - $commission - $vendorDiscount);

        Transaction::updateOrCreate(
            ['transaction_id' => 'TRX-'.$order->order_number],
            [
                'payment_group_id' => $order->payment_group_id, 'order_id' => $order->id, 'customer_id' => $order->customer_id,
                'shop_id' => $order->shop_id, 'amount' => $order->total, 'admin_commission' => $commission + $vendorDiscount,
                'vendor_amount' => $vendorAmount, 'payment_method' => $order->payment_method ?: 'transfer',
                'status' => 'success', 'paid_at' => $order->paymentGroup?->paid_at ?? now(),
            ],
        );

        if ($shop->vendor?->wallet && $vendorAmount > 0) {
            $shop->vendor->wallet->credit($vendorAmount, 'Settlement order #'.$order->order_number, 'order_settlement', $order->id, 'settlement:'.$order->id);
        }
        if ($order->customer) $this->awardLoyaltyOnce($order);
        if ($order->delivery_man_id) $this->creditDeliveryOnce($order);
        app(NotificationService::class)->sendOrderDelivered($order);
    }

    public function restoreStock(Order $order): void
    {
        $order = Order::with(['items.product', 'items.variant'])->lockForUpdate()->findOrFail($order->id);
        if ($order->stock_released_at) return;
        foreach ($order->items as $item) {
            if ($item->product_variant_id && $item->variant) $item->variant->increment('stock', $item->quantity);
            elseif ($item->product) $item->product->increment('current_stock', $item->quantity);
        }
        $order->update(['stock_released_at' => now()]);
    }

    /** Backwards-compatible facade for legacy callers. */
    public function complete(Order $order): void { app(OrderWorkflowService::class)->deliver($order, auth()->id()); }
    public function cancel(Order $order): void { app(OrderWorkflowService::class)->cancel($order, auth()->id()); }
    public function confirm(Order $order): void { app(OrderWorkflowService::class)->confirm($order, auth()->id()); }
    public function ship(Order $order, ?string $trackingId = null): void { app(OrderWorkflowService::class)->ship($order, auth()->id(), $trackingId); }

    private function vendorDiscountShare(Order $order): float
    {
        $discount = (float) $order->discount + (float) $order->coupon_discount;
        if ($discount <= 0) return 0;
        $bearer = \App\Models\SystemSetting::get('coupon_bearer', 'admin');
        if ($bearer === 'vendor') return $discount;
        if ($bearer === 'split') return round($discount * ((float) \App\Models\SystemSetting::get('coupon_vendor_share', 50) / 100), 2);
        return 0;
    }

    private function awardLoyaltyOnce(Order $order): void
    {
        if (LoyaltyTransaction::where('customer_id', $order->customer_id)->where('reference_type', 'order')->where('reference_id', $order->id)->where('type', 'earn')->exists()) return;
        $points = (int) floor((float) $order->total / 1000);
        if ($points <= 0) return;
        $point = LoyaltyPoint::firstOrCreate(['customer_id' => $order->customer_id], ['points' => 0]);
        $point->increment('points', $points);
        LoyaltyTransaction::create(['customer_id' => $order->customer_id, 'points' => $points, 'type' => 'earn', 'description' => 'Pembelian #'.$order->order_number, 'reference_type' => 'order', 'reference_id' => $order->id]);
    }

    private function creditDeliveryOnce(Order $order): void
    {
        $delivery = User::with('wallet')->find($order->delivery_man_id);
        if (!$delivery || !$delivery->wallet) return;
        $earning = DeliveryManEarning::firstOrCreate(
            ['delivery_man_id' => $delivery->id, 'order_id' => $order->id],
            ['amount' => round((float) $order->shipping_cost * 0.8, 2), 'description' => 'Delivery #'.$order->order_number],
        );
        if ((float) $earning->amount > 0) $delivery->wallet->credit((float) $earning->amount, 'Delivery #'.$order->order_number, 'delivery_earning', $earning->id, 'delivery:'.$order->id);
    }
}
