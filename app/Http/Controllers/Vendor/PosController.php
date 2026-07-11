<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;

class PosController extends Controller
{
    public function index(Request $request)
    {
        $shop = auth('vendor')->user()->shop;
        $query = $shop->products()->where('status', 'approved');
        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }
        $products = $query->paginate(12);
        return view('vendor.pos.index', compact('products'));
    }

    public function storeOrder(Request $request)
    {
        $shop = auth('vendor')->user()->shop;
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'discount' => 'nullable|numeric|min:0',
            'payment_method' => 'required|in:cash,transfer,qris',
            'hold' => 'nullable|boolean',
        ]);

        $subTotal = 0;
        foreach ($request->items as $item) {
            $subTotal += $item['price'] * $item['quantity'];
        }
        $discount = (float) ($request->discount ?? 0);
        $total = max(0, $subTotal - $discount);

        $orderStatus = $request->boolean('hold') ? 'pending' : 'delivered';

        $order = Order::create([
            'order_number' => $request->boolean('hold')
                ? 'HOLD-' . now()->format('YmdHis') . '-' . rand(100, 999)
                : 'POS-' . now()->format('YmdHis') . '-' . rand(100, 999),
            'customer_id' => auth('vendor')->id(),
            'shop_id' => $shop->id,
            'sub_total' => $subTotal,
            'discount' => $discount,
            'total' => $total,
            'tax' => 0,
            'shipping_cost' => 0,
            'payment_method' => $request->payment_method,
            'payment_status' => $request->boolean('hold') ? 'unpaid' : 'paid',
            'order_status' => $orderStatus,
            'delivered_at' => $request->boolean('hold') ? null : now(),
            'note' => 'POS: ' . ($request->customer_name ?? 'Walk-in Customer'),
        ]);

        foreach ($request->items as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'tax' => 0,
                'discount' => 0,
                'sub_total' => $item['price'] * $item['quantity'],
            ]);

            if (!$request->boolean('hold')) {
                $product = Product::find($item['product_id']);
                if ($product) {
                    $product->decrement('current_stock', $item['quantity']);
                }
            }
        }

        $order->statusHistory()->create([
            'status' => $orderStatus,
            'changed_by' => auth('vendor')->id(),
            'note' => $request->boolean('hold') ? 'POS hold order' : 'POS sale',
        ]);

        return response()->json([
            'success' => true,
            'order_number' => $order->order_number,
            'total' => $total,
            'hold' => $request->boolean('hold'),
        ]);
    }

    public function heldOrders()
    {
        $shop = auth('vendor')->user()->shop;
        $orders = Order::where('shop_id', $shop->id)
            ->where('order_status', 'pending')
            ->where('order_number', 'like', 'HOLD-%')
            ->with('items.product')
            ->latest()
            ->paginate(10);

        return view('vendor.pos.held', compact('orders'));
    }

    public function resumeHeldOrder(Order $order)
    {
        $shop = auth('vendor')->user()->shop;
        if ($order->shop_id !== $shop->id) abort(403);
        if (!str_starts_with($order->order_number, 'HOLD-')) abort(400);

        foreach ($order->items as $item) {
            $product = $item->product;
            if ($product) {
                $product->decrement('current_stock', $item->quantity);
            }
        }

        $order->update(['payment_status' => 'paid', 'order_status' => 'delivered', 'delivered_at' => now()]);
        $order->statusHistory()->create(['status' => 'delivered', 'changed_by' => auth('vendor')->id(), 'note' => 'POS hold resumed']);

        return back()->with('success', 'Hold order #' . $order->order_number . ' resumed.');
    }

    public function printInvoice(Order $order)
    {
        $shop = auth('vendor')->user()->shop;
        if ($order->shop_id !== $shop->id) abort(403);
        $order->load(['items.product', 'customer', 'shop']);
        return view('vendor.pos.invoice-print', compact('order'));
    }

    public function printInvoicePdf(Order $order)
    {
        $shop = auth('vendor')->user()->shop;
        if ($order->shop_id !== $shop->id) abort(403);
        $order->load(['items.product', 'customer', 'shop']);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('vendor.pos.invoice-pdf', compact('order'));
        return $pdf->download("pos-invoice-{$order->order_number}.pdf");
    }
}
