<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\Vendor\VendorDashboardService;
use App\Support\Currency;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, VendorDashboardService $dashboard): View
    {
        [$from, $to] = $this->resolveRange($request);
        $data = $dashboard->summary($from, $to);

        $subscription = $data['shop']->subscription;

        return view('vendor.dashboard', [
            'shop' => $data['shop'],
            'shopStatus' => $this->shopStatus($data['shop']),
            'range' => $data['range'],
            'from' => $from,
            'to' => $to,
            'kpi' => $data['kpi'],
            'trend' => $data['trend'],
            'orderStatus' => $data['order_status'],
            'bestProducts' => $data['best_products'],
            'lowStock' => $data['low_stock'],
            'pendingFulfillment' => $data['pending_fulfillment'],
            'pendingPayouts' => $data['pending_payouts'],
            'reviews' => $data['reviews'],
            'campaigns' => $data['campaigns'],
            'recentOrders' => $data['recent_orders'],
            'topCustomers' => $data['top_customers'],
            'completeness' => $data['completeness'],
            'performance' => $data['performance'],
            'subscription' => $subscription,
            'productCount' => (int) $data['shop']->products()->where('status', 'approved')->count(),
            'orderStatusCases' => OrderStatus::cases(),
            'currency' => Currency::config(),
        ]);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function resolveRange(Request $request): array
    {
        $to = CarbonImmutable::now()->endOfDay();
        $days = (int) $request->integer('days', 30);
        $days = in_array($days, [7, 30, 90, 180, 365], true) ? $days : 30;

        return [$to->subDays($days - 1)->startOfDay(), $to];
    }

    /** @return array{label: string, badge: string, tone: string} */
    private function shopStatus(Shop $shop): array
    {
        return match ((string) $shop->status) {
            'active' => ['label' => 'Toko aktif', 'badge' => 'success', 'tone' => 'active'],
            'suspended' => ['label' => 'Toko ditangguhkan', 'badge' => 'danger', 'tone' => 'suspended'],
            'pending' => ['label' => 'Menunggu persetujuan', 'badge' => 'warning', 'tone' => 'pending'],
            default => ['label' => ucfirst((string) $shop->status), 'badge' => 'secondary', 'tone' => 'unknown'],
        };
    }

    public function revenueTile(Money $value): string
    {
        return Currency::format($value->toFloat());
    }
}
