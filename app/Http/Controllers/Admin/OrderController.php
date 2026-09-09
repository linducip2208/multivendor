<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use App\Services\OrderWorkflowService;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['customer', 'shop'])->latest();

        if ($request->filled('search')) {
            $query->where('order_number', 'like', "%{$request->search}%");
        }
        if ($request->filled('status')) {
            $query->where('order_status', $request->status);
        }
        if ($request->filled('payment')) {
            $query->where('payment_status', $request->payment);
        }

        $orders = $query->paginate(15)->withQueryString();

        $statusCounts = Order::selectRaw('order_status, count(*) as count')
            ->groupBy('order_status')->pluck('count', 'order_status');

        return view('admin.orders.index', compact('orders', 'statusCounts'));
    }

    public function show(Order $order)
    {
        $order->load(['customer', 'shop', 'items.product', 'items.variant', 'statusHistory.changedBy', 'transaction']);
        return view('admin.orders.show', compact('order'));
    }

    public function updateStatus(Request $request, Order $order, OrderWorkflowService $workflow)
    {
        $validStatuses = ['confirmed', 'processing', 'shipped', 'delivered', 'canceled'];
        $request->validate([
            'status' => 'required|in:' . implode(',', $validStatuses),
            'note' => 'nullable|string',
            'tracking_id' => 'nullable|string',
        ]);

        match ($request->status) {
            'confirmed' => $workflow->confirm($order, auth('admin')->id(), $request->note),
            'processing' => $workflow->process($order, auth('admin')->id(), $request->note),
            'shipped' => $workflow->ship($order, auth('admin')->id(), $request->tracking_id, $request->note),
            'delivered' => $workflow->deliver($order, auth('admin')->id(), $request->note),
            'canceled' => $workflow->cancel($order, auth('admin')->id(), $request->note),
        };

        $labels = ['confirmed' => 'Dikonfirmasi', 'processing' => 'Diproses', 'shipped' => 'Dikirim', 'delivered' => 'Terkirim', 'canceled' => 'Dibatalkan'];
        return back()->with('success', 'Status diubah: ' . ($labels[$request->status] ?? $request->status));
    }
}
