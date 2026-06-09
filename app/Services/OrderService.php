<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\User;

class OrderService
{
    public function complete(Order $order): void
    {
        $shop = $order->shop;
        $commission = 0;
        if ($shop->commission_type === 'percentage') {
            $commission = $order->sub_total * ($shop->commission_value / 100);
        } else {
            $commission = $shop->commission_value;
        }
        $vendorAmount = $order->total - $commission;

        Transaction::create([
            'transaction_id' => 'TRX-' . $order->order_number,
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'shop_id' => $order->shop_id,
            'amount' => $order->total,
            'admin_commission' => $commission,
            'vendor_amount' => $vendorAmount,
            'payment_method' => $order->payment_method ?? 'system',
            'status' => 'success',
            'paid_at' => now(),
        ]);

        $vendor = $shop->vendor;
        if ($vendor && $vendor->wallet) {
            $vendor->wallet->credit($vendorAmount, 'Pesanan #' . $order->order_number);
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
    }

    public function cancel(Order $order): void
    {
        foreach ($order->items as $item) {
            $product = $item->product;
            if ($product) $product->increment('current_stock', $item->quantity);
        }
    }
}

class NotificationService
{
    public function sendOrderConfirmation(Order $order): void {}
    public function sendOrderShipped(Order $order): void {}
    public function sendWithdrawApproved($withdraw): void {}
}
