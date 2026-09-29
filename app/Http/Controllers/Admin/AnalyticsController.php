<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Analytics\CsvExportService;
use App\Services\Analytics\CustomerAnalyticsService;
use App\Services\Analytics\DateRange;
use App\Services\Analytics\ExecutiveAnalyticsService;
use App\Services\Analytics\FinanceAnalyticsService;
use App\Services\Analytics\MarketingAnalyticsService;
use App\Services\Analytics\ProductAnalyticsService;
use App\Services\Analytics\SalesAnalyticsService;
use App\Services\Analytics\StockAnalyticsService;
use App\Services\Analytics\VendorAnalyticsService;
use App\Support\Currency;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Every figure on these screens is produced by a service under
 * {@see \App\Services\Analytics}. The controller only resolves the reporting
 * window, forwards the paging and hands shaped arrays to Blade.
 */
class AnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        $range = DateRange::fromRequest($request);

        return view('admin.analytics.index', [
            'range' => $range,
            'summary' => app(ExecutiveAnalyticsService::class)->summary($range),
            'tabs' => $this->tabs('admin.analytics.index'),
        ]);
    }

    public function sales(Request $request): View
    {
        $range = DateRange::fromRequest($request);

        $report = app(SalesAnalyticsService::class)->report(
            $range,
            $this->perPage($request),
            $this->page($request),
            $this->search($request),
        );

        return view('admin.analytics.sales', [
            'range' => $range,
            'report' => $report,
            'orders' => $report['orders'],
            'tabs' => $this->tabs('admin.analytics.sales'),
        ]);
    }

    public function customers(Request $request): View
    {
        $range = DateRange::fromRequest($request);

        $report = app(CustomerAnalyticsService::class)->report(
            $range,
            $this->perPage($request),
            $this->page($request),
            $this->search($request),
        );

        return view('admin.analytics.customers', [
            'range' => $range,
            'report' => $report,
            'rows' => $report['rows'],
            'tabs' => $this->tabs('admin.analytics.customers'),
        ]);
    }

    public function vendors(Request $request): View
    {
        $range = DateRange::fromRequest($request);

        $report = app(VendorAnalyticsService::class)->report(
            $range,
            $this->perPage($request),
            $this->page($request),
            $this->search($request),
        );

        return view('admin.analytics.vendors', [
            'range' => $range,
            'report' => $report,
            'tabs' => $this->tabs('admin.analytics.vendors'),
        ]);
    }

    public function products(Request $request): View
    {
        $range = DateRange::fromRequest($request);

        $report = app(ProductAnalyticsService::class)->report(
            $range,
            $this->perPage($request),
            $this->page($request),
            $this->search($request),
            (string) $request->query('sort', 'revenue'),
        );

        return view('admin.analytics.products', [
            'range' => $range,
            'report' => $report,
            'tabs' => $this->tabs('admin.analytics.products'),
        ]);
    }

    public function marketing(Request $request): View
    {
        $range = DateRange::fromRequest($request);

        $report = app(MarketingAnalyticsService::class)->report(
            $range,
            $this->perPage($request),
            $this->page($request),
        );

        return view('admin.analytics.marketing', [
            'range' => $range,
            'report' => $report,
            'campaigns' => $report['campaigns'],
            'funnel' => app(MarketingAnalyticsService::class)->checkoutFunnel($range),
            'compare' => app(MarketingAnalyticsService::class)->compare($range),
            'tabs' => $this->tabs('admin.analytics.marketing'),
        ]);
    }

    public function finance(Request $request): View
    {
        $range = DateRange::fromRequest($request);

        $report = app(FinanceAnalyticsService::class)->report(
            $range,
            $this->perPage($request),
            $this->page($request),
            (string) $request->query('account', ''),
            $this->search($request),
        );

        return view('admin.analytics.finance', [
            'range' => $range,
            'report' => $report,
            'tabs' => $this->tabs('admin.analytics.finance'),
        ]);
    }

    public function stock(Request $request): View
    {
        $range = DateRange::fromRequest($request);

        $report = app(StockAnalyticsService::class)->report(
            $range,
            $this->perPage($request),
            $this->page($request),
            $this->search($request),
            (string) $request->query('filter', ''),
        );

        return view('admin.analytics.stock', [
            'range' => $range,
            'report' => $report,
            'tabs' => $this->tabs('admin.stock-report.index'),
        ]);
    }

    public function vendorSales(Request $request): View
    {
        $range = DateRange::fromRequest($request);

        $report = app(CustomerAnalyticsService::class)->vendorSalesReport(
            $range,
            $this->perPage($request),
            $this->page($request),
            $this->search($request),
        );

        return view('admin.analytics.vendor-sales', [
            'range' => $range,
            'report' => $report,
            'tabs' => $this->tabs('admin.vendor-sale-report.index'),
        ]);
    }

    public function exportProducts(Request $request): StreamedResponse
    {
        $range = DateRange::fromRequest($request, 365);
        $export = app(CsvExportService::class);

        $query = Product::query()
            ->with(['shop:id,name', 'category:id,name', 'brand:id,name'])
            ->where('status', 'approved')
            ->orderBy('id');

        if (($search = $this->search($request)) !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('sku', 'like', '%'.$search.'%'));
        }

        return $export->stream(
            'produk',
            ['ID', 'SKU', 'Nama', 'Toko', 'Kategori', 'Brand', 'Harga', 'Stok', 'Rating', 'Terjual'],
            $query,
            null,
            [
                fn (Product $product): string => (string) $product->id,
                fn (Product $product): string => (string) ($product->sku ?? ''),
                fn (Product $product): string => (string) $product->name,
                fn (Product $product): string => (string) ($product->shop?->name ?? ''),
                fn (Product $product): string => (string) ($product->category?->name ?? ''),
                fn (Product $product): string => (string) ($product->brand?->name ?? ''),
                fn (Product $product): string => $export->money($product->price),
                fn (Product $product): string => (string) (int) $product->current_stock,
                fn (Product $product): string => (string) (float) $product->rating_average,
                fn (Product $product): string => (string) (int) $product->sold_count,
            ],
        );
    }

    public function exportOrders(Request $request): StreamedResponse
    {
        $range = DateRange::fromRequest($request, 365);
        $export = app(CsvExportService::class);

        $query = Order::query()
            ->with(['customer:id,name,email', 'shop:id,name'])
            ->whereBetween('created_at', [$range->from, $range->to])
            ->orderBy('id');

        return $export->stream(
            'pesanan',
            ['ID', 'Nomor', 'Pelanggan', 'Toko', 'Status', 'Pembayaran', 'Subtotal', 'Ongkir', 'Diskon', 'Pajak', 'Total', 'Dibuat'],
            $query,
            null,
            [
                fn (Order $order): string => (string) $order->id,
                fn (Order $order): string => (string) $order->order_number,
                fn (Order $order): string => (string) ($order->customer?->name ?? ''),
                fn (Order $order): string => (string) ($order->shop?->name ?? ''),
                fn (Order $order): string => (string) $order->order_status,
                fn (Order $order): string => (string) $order->payment_status,
                fn (Order $order): string => $export->money($order->sub_total),
                fn (Order $order): string => $export->money($order->shipping_cost),
                fn (Order $order): string => $export->money($order->discount),
                fn (Order $order): string => $export->money($order->tax),
                fn (Order $order): string => $export->money($order->total),
                fn (Order $order): string => $export->raw($order->created_at),
            ],
        );
    }

    public function exportCustomers(Request $request): StreamedResponse
    {
        $export = app(CsvExportService::class);

        $query = User::query()->where('role', 'customer')->orderBy('id');

        if (($search = $this->search($request)) !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'));
        }

        return $export->stream(
            'pelanggan',
            ['ID', 'Nama', 'Email', 'Telepon', 'Status', 'Kode Referral', 'Pesanan', 'Total Belanja', 'Bergabung'],
            $query,
            null,
            [
                fn (User $user): string => (string) $user->id,
                fn (User $user): string => (string) $user->name,
                fn (User $user): string => (string) $user->email,
                fn (User $user): string => (string) ($user->phone ?? ''),
                fn (User $user): string => (string) ($user->status ?? ''),
                fn (User $user): string => (string) ($user->referral_code ?? ''),
                fn (User $user): string => (string) $user->orders()->count(),
                fn (User $user): string => $export->money($user->orders()->sum('total')),
                fn (User $user): string => $export->raw($user->created_at),
            ],
        );
    }

    public function exportTransactions(Request $request): StreamedResponse
    {
        $range = DateRange::fromRequest($request, 365);
        $export = app(CsvExportService::class);

        $query = Transaction::query()
            ->with(['customer:id,name', 'shop:id,name'])
            ->whereBetween('created_at', [$range->from, $range->to])
            ->orderBy('id');

        return $export->stream(
            'transaksi',
            ['ID', 'Nomor Transaksi', 'Pelanggan', 'Toko', 'Metode', 'Status', 'Nilai', 'Komisi', 'Dibayar ke Vendor', 'Dibuat'],
            $query,
            null,
            [
                fn (Transaction $transaction): string => (string) $transaction->id,
                fn (Transaction $transaction): string => (string) $transaction->transaction_id,
                fn (Transaction $transaction): string => (string) ($transaction->customer?->name ?? ''),
                fn (Transaction $transaction): string => (string) ($transaction->shop?->name ?? ''),
                fn (Transaction $transaction): string => (string) ($transaction->payment_method ?? ''),
                fn (Transaction $transaction): string => (string) $transaction->status,
                fn (Transaction $transaction): string => $export->money($transaction->amount),
                fn (Transaction $transaction): string => $export->money($transaction->admin_commission),
                fn (Transaction $transaction): string => $export->money($transaction->vendor_amount),
                fn (Transaction $transaction): string => $export->raw($transaction->created_at),
            ],
        );
    }

    /**
     * @return list<array{label: string, href: string, icon: string, active: bool}>
     */
    private function tabs(string $current): array
    {
        return collect([
            'admin.analytics.index' => ['label' => 'Eksekutif', 'icon' => 'trending-up'],
            'admin.analytics.sales' => ['label' => 'Penjualan', 'icon' => 'shopping-cart'],
            'admin.analytics.customers' => ['label' => 'Pelanggan', 'icon' => 'users'],
            'admin.analytics.vendors' => ['label' => 'Vendor', 'icon' => 'store'],
            'admin.analytics.products' => ['label' => 'Produk', 'icon' => 'package'],
            'admin.analytics.marketing' => ['label' => 'Marketing', 'icon' => 'megaphone'],
            'admin.analytics.finance' => ['label' => 'Keuangan', 'icon' => 'cash'],
            'admin.stock-report.index' => ['label' => 'Stok', 'icon' => 'boxes'],
        ])
            ->filter(fn (array $tab, string $name): bool => \Illuminate\Support\Str::is('admin.analytics.*', $name) || \Illuminate\Support\Str::is($name, $name) || \Route::has($name))
            ->map(fn (array $tab, string $name): array => [
                'label' => $tab['label'],
                'href' => route($name),
                'icon' => $tab['icon'],
                'active' => request()->routeIs($name),
            ])
            ->values()
            ->all();
    }

    private function perPage(Request $request): int
    {
        return max(5, min(100, (int) $request->query('per_page', 20)));
    }

    private function page(Request $request): int
    {
        return max(1, (int) $request->query('page', 1));
    }

    private function search(Request $request): string
    {
        return trim((string) $request->query('search', ''));
    }
}
