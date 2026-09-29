<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Transaction;
use App\Support\Currency;

/**
 * Statistics gathering and prompt assembly for the reports screen.
 *
 * The heredocs that used to live in the controller now live here so the prompt
 * can be reviewed, reused by the copilot, and unit tested without a database.
 */
final class ReportAggregator
{
    /**
     * @return array<string, mixed>
     */
    public function gather(int $days = 30): array
    {
        $now = now();
        $from = $now->copy()->subDays($days);
        $sevenDaysAgo = $now->copy()->subDays(7);

        return [
            'window_days' => $days,
            'totalRevenue' => (float) Transaction::where('status', 'success')->sum('amount'),
            'monthRevenue' => (float) Transaction::where('status', 'success')->where('created_at', '>=', $from)->sum('amount'),
            'topProducts' => $this->topProducts($from),
            'topCategories' => $this->topCategories($from),
            'topShops' => $this->topShops($from),
            'orderStats' => $this->orderStats($from),
            'dailyRevenue' => $this->dailyRevenue($sevenDaysAgo),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topProducts(\DateTimeInterface $from): array
    {
        return Product::query()
            ->where('status', 'approved')
            ->withCount(['orderItems as total_sold' => fn ($q) => $q->whereHas('order', fn ($o) => $o
                ->whereNotIn('order_status', ['canceled', 'failed'])
                ->where('created_at', '>=', $from))])
            ->withSum(['orderItems as revenue' => fn ($q) => $q->whereHas('order', fn ($o) => $o
                ->whereNotIn('order_status', ['canceled', 'failed'])
                ->where('created_at', '>=', $from))], 'sub_total')
            ->orderByDesc('revenue')
            ->take(10)
            ->get()
            ->map(fn (Product $product): array => [
                'name' => $product->name,
                'shop' => $product->shop->name ?? '',
                'price' => (float) $product->price,
                'sold' => (int) $product->total_sold,
                'revenue' => (float) $product->revenue,
                'category' => $product->category->name ?? '',
            ])
            ->all();
    }

    /**
     * @return list<array{name: string, revenue: float}>
     */
    private function topCategories(\DateTimeInterface $from): array
    {
        return Category::query()
            ->whereNull('parent_id')
            ->withSum(['products as revenue' => fn ($q) => $q->whereHas('orderItems.order', fn ($o) => $o
                ->whereNotIn('order_status', ['canceled', 'failed'])
                ->where('created_at', '>=', $from))], 'price')
            ->orderByDesc('revenue')
            ->take(5)
            ->get()
            ->map(fn (Category $category): array => ['name' => $category->name, 'revenue' => (float) $category->revenue])
            ->all();
    }

    /**
     * @return list<array{name: string, orders: int, revenue: float}>
     */
    private function topShops(\DateTimeInterface $from): array
    {
        return Shop::query()
            ->where('status', 'active')
            ->withSum(['orders as revenue' => fn ($q) => $q
                ->whereNotIn('order_status', ['canceled', 'failed'])
                ->where('created_at', '>=', $from)], 'total')
            ->withCount(['orders as total_orders' => fn ($q) => $q
                ->whereNotIn('order_status', ['canceled', 'failed'])
                ->where('created_at', '>=', $from)])
            ->orderByDesc('revenue')
            ->take(5)
            ->get()
            ->map(fn (Shop $shop): array => [
                'name' => $shop->name,
                'orders' => (int) $shop->total_orders,
                'revenue' => (float) $shop->revenue,
            ])
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function orderStats(\DateTimeInterface $from): array
    {
        $row = Order::query()
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN order_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN order_status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN order_status = 'shipped' THEN 1 ELSE 0 END) as shipped,
                SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN order_status = 'canceled' THEN 1 ELSE 0 END) as canceled,
                SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) as unpaid,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid
            ")
            ->where('created_at', '>=', $from)
            ->first();

        $out = [];
        foreach ((array) $row as $key => $value) {
            $out[(string) $key] = (int) $value;
        }

        return $out + [
            'total' => 0,
            'pending' => 0,
            'confirmed' => 0,
            'processing' => 0,
            'shipped' => 0,
            'delivered' => 0,
            'canceled' => 0,
            'unpaid' => 0,
            'paid' => 0,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function dailyRevenue(\DateTimeInterface $from): array
    {
        return Transaction::query()
            ->where('status', 'success')
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as bucket, SUM(amount) as total')
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->pluck('total', 'bucket')
            ->map(fn ($value): float => (float) $value)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    public function prompt(array $stats): string
    {
        return implode("\n", [
            'Analisis data platform multivendor berikut:',
            '',
            '**Pendapatan Total:** '.Currency::format((float) ($stats['totalRevenue'] ?? 0)),
            '**Pendapatan Periode ('.(int) ($stats['window_days'] ?? 30).' hari):** '.Currency::format((float) ($stats['monthRevenue'] ?? 0)),
            '',
            '**Top 10 Produk Terlaris:**',
            '```json',
            $this->json($stats['topProducts'] ?? []),
            '```',
            '',
            '**Top 5 Kategori:**',
            '```json',
            $this->json($stats['topCategories'] ?? []),
            '```',
            '',
            '**Top 5 Vendor:**',
            '```json',
            $this->json($stats['topShops'] ?? []),
            '```',
            '',
            '**Statistik Pesanan:**',
            '```json',
            $this->json($stats['orderStats'] ?? []),
            '```',
            '',
            '**Pendapatan Harian:**',
            '```json',
            $this->json($stats['dailyRevenue'] ?? []),
            '```',
            '',
            'Berikan analisis lengkap sesuai format yang diminta. Jangan mengarang angka di luar data di atas.',
        ]);
    }

    public function systemPrompt(): string
    {
        return AdminPrompts::systemPrompt('sales_analysis');
    }

    private function json(mixed $value): string
    {
        return (string) json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR,
        );
    }
}
