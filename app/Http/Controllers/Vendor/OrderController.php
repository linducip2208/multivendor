<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $shop = auth('vendor')->user()->shop;
        if (!$shop) return redirect()->route('vendor.dashboard');

        $query = Order::where('shop_id', $shop->id)->with(['customer', 'items.product'])->latest();

        if ($request->filled('status')) {
            $query->where('order_status', $request->status);
        }
        if ($request->filled('search')) {
            $query->where('order_number', 'like', "%{$request->search}%");
        }

        $orders = $query->paginate(15)->withQueryString();

        $statusCounts = Order::where('shop_id', $shop->id)
            ->selectRaw('order_status, count(*) as count')
            ->groupBy('order_status')
            ->pluck('count', 'order_status');

        return view('vendor.orders.index', compact('orders', 'statusCounts'));
    }

    public function show(Order $order)
    {
        $shop = auth('vendor')->user()->shop;
        if ($order->shop_id !== $shop->id) abort(403);

        $order->load(['customer', 'items.product', 'items.variant', 'statusHistory.changedBy']);
        return view('vendor.orders.show', compact('order'));
    }

    public function updateStatus(Request $request, Order $order)
    {
        $shop = auth('vendor')->user()->shop;
        if ($order->shop_id !== $shop->id) abort(403);

        $validStatuses = ['confirmed', 'processing', 'shipped', 'canceled'];
        $request->validate(['status' => 'required|in:' . implode(',', $validStatuses)]);

        $order->update([
            'order_status' => $request->status,
            $request->status . '_at' => now(),
            'cancel_reason' => $request->status === 'canceled' ? $request->reason : null,
        ]);

        $order->statusHistory()->create([
            'status' => $request->status,
            'changed_by' => auth('vendor')->id(),
            'note' => $request->note,
        ]);

        $labels = [
            'confirmed' => 'Dikonfirmasi', 'processing' => 'Diproses',
            'shipped' => 'Dikirim', 'canceled' => 'Dibatalkan'
        ];
        return back()->with('success', 'Status pesanan diubah: ' . ($labels[$request->status] ?? $request->status));
    }
}
