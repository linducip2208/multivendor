<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\User;
use App\Models\SystemSetting;

class OrderService
{
    protected NotificationService $notifications;

    public function __construct()
    {
        $this->notifications = new NotificationService;
    }

    public function complete(Order $order): void
    {
        $shop = $order->shop;
        $commission = 0;
        if ($shop->commission_type === 'percentage') {
            $commission = $order->sub_total * ($shop->commission_value / 100);
        } else {
            $commission = $shop->commission_value;
        }

        $discountBearer = SystemSetting::get('discount_bearer', 'admin');
        $discountAdminShare = (float) SystemSetting::get('discount_admin_share', '50');
        $discountVendorShare = (float) SystemSetting::get('discount_vendor_share', '50');
        $couponBearer = SystemSetting::get('coupon_bearer', 'admin');
        $couponAdminShare = (float) SystemSetting::get('coupon_admin_share', '50');
        $couponVendorShare = (float) SystemSetting::get('coupon_vendor_share', '50');

        $totalDiscount = ($order->discount ?? 0) + ($order->coupon_discount ?? 0);
        $adminDiscountAbsorption = 0;

        if ($totalDiscount > 0) {
            $discountBearerType = $discountBearer === 'vendor' ? 'vendor' : ($discountBearer === 'split' ? 'split' : 'admin');
            $discountSplit = $discountBearerType === 'split' ? ($discountVendorShare / 100) : 0;
            $adminDiscountAbsorption = $totalDiscount * (1 - $discountSplit);
        }

        if ($order->coupon_discount > 0) {
            $couponBearerType = $couponBearer === 'vendor' ? 'vendor' : ($couponBearer === 'split' ? 'split' : 'admin');
            $couponSplit = $couponBearerType === 'split' ? ($couponVendorShare / 100) : 0;
            $adminDiscountAbsorption += $order->coupon_discount * (1 - $couponSplit);
        }

        $vendorAmount = $order->total - $commission - $adminDiscountAbsorption;

        Transaction::create([
            'transaction_id' => 'TRX-' . $order->order_number,
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'shop_id' => $order->shop_id,
            'amount' => $order->total,
            'admin_commission' => $commission + $adminDiscountAbsorption,
            'vendor_amount' => max(0, $vendorAmount),
            'payment_method' => $order->payment_method ?? 'system',
            'status' => 'success',
            'paid_at' => now(),
        ]);

        $vendor = $shop->vendor;
        if ($vendor && $vendor->wallet && $vendorAmount > 0) {
            $vendor->wallet->credit(max(0, $vendorAmount), 'Pesanan #' . $order->order_number);
        }

        $customer = $order->customer;
        if ($customer) {
            \App\Models\LoyaltyPoint::earn($customer, (int)($order->total / 1000), 'Pembelian #' . $order->order_number, 'order', $order->id);
        }

        if ($order->delivery_man_id) {
            $deliveryFee = $order->shipping_cost * 0.8;
            $dm = User::find($order->delivery_man_id);
            if ($dm && $dm->wallet) {
                $dm->wallet->credit($deliveryFee, 'Delivery #' . $order->order_number);
                \App\Models\DeliveryManEarning::create(['delivery_man_id' => $dm->id, 'order_id' => $order->id, 'amount' => $deliveryFee, 'description' => 'Delivery #' . $order->order_number]);
            }
        }

        $this->notifications->sendOrderDelivered($order);
    }

    public function cancel(Order $order): void
    {
        foreach ($order->items as $item) {
            $product = $item->product;
            if ($product) $product->increment('current_stock', $item->quantity);
        }
    }

    public function confirm(Order $order): void
    {
        $order->update(['order_status' => 'confirmed', 'confirmed_at' => now()]);
        $order->statusHistory()->create(['status' => 'confirmed', 'changed_by' => auth()->id() ?? 1, 'note' => 'Order confirmed']);
        $this->notifications->sendOrderConfirmation($order);
    }

    public function ship(Order $order, ?string $trackingId = null): void
    {
        $update = ['order_status' => 'shipped', 'shipped_at' => now()];
        if ($trackingId) $update['shipping_tracking_id'] = $trackingId;
        $order->update($update);
        $order->statusHistory()->create(['status' => 'shipped', 'changed_by' => auth()->id() ?? 1, 'note' => 'Order shipped']);
        $this->notifications->sendOrderShipped($order);
    }
}
