<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\Analytics\DateRange;
use App\Services\Vendor\VendorAnalyticsService;
use App\Support\Currency;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private readonly VendorAnalyticsService $analytics) {}

    public function index(Request $request): View
    {
        $range = DateRange::fromRequest($request);

        return view('vendor.analytics.index', $this->analytics->overview($range) + [
            'currency' => Currency::config(),
        ]);
    }

    public function sales(Request $request): View
    {
        $data = $this->analytics->sales(DateRange::fromRequest($request));

        return view('vendor.analytics.sales', $data + [
            'currency' => Currency::config(),
        ]);
    }

    public function customers(Request $request): View
    {
        return view('vendor.analytics.customers', $this->analytics->customers(DateRange::fromRequest($request)) + [
            'currency' => Currency::config(),
        ]);
    }

    public function productsAnalytics(Request $request): View
    {
        return view('vendor.analytics.products', $this->analytics->products(DateRange::fromRequest($request)) + [
            'currency' => Currency::config(),
        ]);
    }

    public function products(Request $request): View
    {
        $shop = auth('vendor')->user()->shop;
        $search = VendorScopeRequest::search($request);

        $query = Product::query()
            ->where('shop_id', (int) $shop?->id)
            ->withSum(['orderItems as sold' => fn ($q) => $q->whereHas('order', fn ($o) => $o->where('order_status', '!=', 'canceled'))], 'quantity')
            ->withSum(['orderItems as revenue' => fn ($q) => $q->whereHas('order', fn ($o) => $o->where('order_status', '!=', 'canceled'))], 'sub_total')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->latest();

        return view('vendor.report.products', [
            'products' => $query->paginate(15)->withQueryString(),
            'search' => $search,
        ]);
    }

    public function orders(Request $request): View
    {
        $shop = auth('vendor')->user()->shop;
        $status = VendorScopeRequest::enum($request, 'status', ['pending', 'paid', 'confirmed', 'processing', 'packed', 'shipped', 'delivered', 'completed', 'canceled', 'refunded']);

        $query = Order::query()
            ->where('shop_id', (int) $shop?->id)
            ->with('customer:id,name')
            ->when($status !== '', fn ($q) => $q->where('order_status', $status))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->latest();

        $totalRevenue = (clone $query)->where('order_status', '!=', 'canceled')->sum('total');

        return view('vendor.report.orders', [
            'orders' => $query->paginate(15)->withQueryString(),
            'totalRevenue' => Money::of($totalRevenue),
            'status' => $status,
        ]);
    }

    public function transactions(Request $request): View
    {
        $shop = auth('vendor')->user()->shop;
        $status = VendorScopeRequest::enum($request, 'status', ['success', 'pending', 'failed', 'refunded']);

        $query = Transaction::query()
            ->where('shop_id', (int) $shop?->id)
            ->with('order:id,order_number')
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->latest();

        return view('vendor.report.transactions', [
            'transactions' => $query->paginate(15)->withQueryString(),
            'totalSuccess' => Money::of((float) Transaction::query()
                ->where('shop_id', (int) $shop?->id)
                ->where('status', 'success')
                ->sum('vendor_amount')),
            'status' => $status,
        ]);
    }
}
