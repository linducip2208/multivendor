<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\RefundWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
        if ($order->customer_id !== auth()->id()) {
            abort(403);
        }

        $order->load(['shop', 'items.product', 'items.variant', 'statusHistory', 'transaction']);

        return view('storefront.orders.show', compact('order'));
    }

    public function requestRefund(Request $request, OrderItem $orderItem, RefundWorkflowService $refunds): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);
        $refunds->request($orderItem, auth()->id(), $validated['reason']);

        return back()->with('success', 'Permintaan refund telah dikirim ke penjual untuk ditinjau.');
    }
}
