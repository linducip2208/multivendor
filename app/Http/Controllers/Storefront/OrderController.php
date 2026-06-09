<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::where('customer_id', auth()->id())
            ->with(['shop', 'items.product'])
            ->latest()
            ->paginate(10);

        return view('storefront.orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        if ($order->customer_id !== auth()->id()) abort(403);

        $order->load(['shop', 'items.product', 'items.variant', 'statusHistory', 'transaction']);
        return view('storefront.orders.show', compact('order'));
    }
}
