<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;

class PosController extends Controller
{
    public function index(Request $request)
    {
        $shop = auth('vendor')->user()->shop;
        $query = Product::where('shop_id', $shop->id)->where('status', 'approved');
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
        ]);

        $subTotal = 0;
        foreach ($request->items as $item) {
            $subTotal += $item['price'] * $item['quantity'];
        }
        $discount = (float) ($request->discount ?? 0);
        $total = max(0, $subTotal - $discount);

        $order = Order::create([
            'order_number' => 'POS-' . now()->format('YmdHis') . '-' . rand(100, 999),
            'customer_id' => auth('vendor')->id(),
            'shop_id' => $shop->id,
            'sub_total' => $subTotal,
            'discount' => $discount,
            'total' => $total,
            'tax' => 0,
            'shipping_cost' => 0,
            'payment_method' => $request->payment_method,
            'payment_status' => 'paid',
            'order_status' => 'delivered',
            'delivered_at' => now(),
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

            $product = Product::find($item['product_id']);
            if ($product) {
                $product->decrement('current_stock', $item['quantity']);
            }
        }

        $order->statusHistory()->create(['status' => 'delivered', 'changed_by' => auth('vendor')->id(), 'note' => 'POS sale']);

        return response()->json(['success' => true, 'order_number' => $order->order_number, 'total' => $total]);
    }
}
