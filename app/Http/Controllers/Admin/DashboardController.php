<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_vendors' => User::where('role', 'vendor')->count(),
            'total_customers' => User::where('role', 'customer')->count(),
            'total_products' => Product::count(),
            'total_orders' => Order::count(),
            'total_revenue' => \App\Models\Transaction::where('status', 'success')->sum('amount'),
            'pending_shops' => Shop::where('status', 'pending')->count(),
            'pending_products' => Product::where('status', 'pending')->count(),
            'pending_orders' => Order::where('order_status', 'pending')->count(),
        ];

        $recentOrders = Order::with(['customer', 'shop'])->latest()->take(10)->get();
        $recentShops = Shop::with('vendor')->latest()->take(5)->get();

        return view('admin.dashboard', compact('stats', 'recentOrders', 'recentShops'));
    }
}
