<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function products(Request $request)
    {
        $shop = auth('vendor')->user()->shop;
        $query = Product::where('shop_id', $shop->id)->withSum(['orderItems as sold' => function ($q) {
            $q->whereHas('order', fn($o) => $o->where('order_status', '!=', 'canceled'));
        }], 'quantity')->withSum(['orderItems as revenue' => function ($q) {
            $q->whereHas('order', fn($o) => $o->where('order_status', '!=', 'canceled'));
        }], 'sub_total')->latest();
        if ($request->filled('search')) $query->where('name', 'like', "%{$request->search}%");
        $products = $query->paginate(15);
        return view('vendor.report.products', compact('products'));
    }

    public function orders(Request $request)
    {
        $shop = auth('vendor')->user()->shop;
        $query = Order::where('shop_id', $shop->id)->with('customer')->latest();
        if ($request->filled('status')) $query->where('order_status', $request->status);
        if ($request->filled('from')) $query->whereDate('created_at', '>=', $request->from);
        if ($request->filled('to')) $query->whereDate('created_at', '<=', $request->to);
        $orders = $query->paginate(15);
        $totalRevenue = (clone $query)->where('order_status', '!=', 'canceled')->sum('total');
        return view('vendor.report.orders', compact('orders', 'totalRevenue'));
    }

    public function transactions(Request $request)
    {
        $shop = auth('vendor')->user()->shop;
        $query = Transaction::where('shop_id', $shop->id)->with('order')->latest();
        if ($request->filled('status')) $query->where('status', $request->status);
        $transactions = $query->paginate(15);
        $totalSuccess = Transaction::where('shop_id', $shop->id)->where('status', 'success')->sum('vendor_amount');
        return view('vendor.report.transactions', compact('transactions', 'totalSuccess'));
    }
}
