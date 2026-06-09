<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Shop;
use App\Models\Transaction;
use App\Services\Ai\AiService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index()
    {
        $aiProviders = Provider::ofType('ai')->active()->get();

        return view('admin.reports.index', compact('aiProviders'));
    }

    public function aiAnalysis(Request $request)
    {
        $request->validate([
            'provider_id' => 'required|exists:providers,id',
            'model' => 'nullable|string',
        ]);

        $provider = Provider::findOrFail($request->provider_id);

        $stats = $this->gatherStats();
        $prompt = $this->buildAnalysisPrompt($stats);

        $aiService = app(AiService::class);
        $result = $aiService->chat($provider, $prompt, $this->systemPrompt(), $request->model, [
            'temperature' => 0.3,
            'max_tokens' => 3000,
        ]);

        if (!$result['success']) {
            return response()->json(['error' => $result['error'] ?? 'Gagal analisis AI'], 500);
        }

        return response()->json([
            'success' => true,
            'content' => $result['content'],
            'model' => $result['model'],
            'tokens' => $result['tokens'],
        ]);
    }

    public function fetchModels(Request $request)
    {
        $request->validate(['provider_id' => 'required|exists:providers,id']);
        $provider = Provider::findOrFail($request->provider_id);
        $aiService = app(AiService::class);
        $result = $aiService->fetchModels($provider);

        return response()->json($result);
    }

    protected function gatherStats(): array
    {
        $now = now();
        $thirtyDaysAgo = $now->copy()->subDays(30);
        $sevenDaysAgo = $now->copy()->subDays(7);

        $totalRevenue = Transaction::where('status', 'success')->sum('amount');
        $monthRevenue = Transaction::where('status', 'success')->where('created_at', '>=', $thirtyDaysAgo)->sum('amount');

        $topProducts = Product::where('status', 'approved')
            ->withCount(['orderItems as total_sold' => function ($q) use ($thirtyDaysAgo) {
                $q->whereHas('order', fn ($o) => $o->where('order_status', '!=', 'canceled')
                    ->where('created_at', '>=', $thirtyDaysAgo));
            }])
            ->withSum(['orderItems as revenue' => function ($q) use ($thirtyDaysAgo) {
                $q->whereHas('order', fn ($o) => $o->where('order_status', '!=', 'canceled')
                    ->where('created_at', '>=', $thirtyDaysAgo));
            }], 'sub_total')
            ->orderByDesc('revenue')
            ->take(10)
            ->get()
            ->map(fn ($p) => [
                'name' => $p->name,
                'shop' => $p->shop->name ?? '',
                'price' => (float) $p->price,
                'sold' => (int) $p->total_sold,
                'revenue' => (float) $p->revenue,
                'category' => $p->category->name ?? '',
            ])->toArray();

        $topCategories = Category::whereNull('parent_id')
            ->withSum(['products as revenue' => function ($q) use ($thirtyDaysAgo) {
                $q->whereHas('orderItems.order', fn ($o) => $o->where('order_status', '!=', 'canceled')
                    ->where('created_at', '>=', $thirtyDaysAgo));
            }], 'price')
            ->orderByDesc('revenue')
            ->take(5)
            ->get()
            ->map(fn ($c) => ['name' => $c->name, 'revenue' => (float) $c->revenue])
            ->toArray();

        $topShops = Shop::where('status', 'active')
            ->withSum(['orders as revenue' => function ($q) use ($thirtyDaysAgo) {
                $q->where('order_status', '!=', 'canceled')
                    ->where('created_at', '>=', $thirtyDaysAgo);
            }], 'total')
            ->withCount(['orders as total_orders' => function ($q) use ($thirtyDaysAgo) {
                $q->where('order_status', '!=', 'canceled')
                    ->where('created_at', '>=', $thirtyDaysAgo);
            }])
            ->orderByDesc('revenue')
            ->take(5)
            ->get()
            ->map(fn ($s) => ['name' => $s->name, 'revenue' => (float) $s->revenue, 'orders' => $s->total_orders])
            ->toArray();

        $orderStats = Order::selectRaw("
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
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->first()
            ->toArray();

        $dailyRevenue = Transaction::where('status', 'success')
            ->where('created_at', '>=', $sevenDaysAgo)
            ->selectRaw('DATE(created_at) as date, SUM(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('total', 'date')
            ->toArray();

        return compact(
            'totalRevenue', 'monthRevenue', 'topProducts', 'topCategories',
            'topShops', 'orderStats', 'dailyRevenue'
        );
    }

    protected function systemPrompt(): string
    {
        return <<<PROMPT
Kamu adalah AI analis data untuk platform multivendor e-commerce Indonesia bernama MultiVendor.
Berikan analisis bisnis yang actionable dalam Bahasa Indonesia.

Format respons:
1. **Ringkasan Eksekutif** (2-3 kalimat)
2. **Produk Paling Laris** — sebutkan top 3, kenapa laris, dan rekomendasi
3. **Kategori Terlaris** — analisis tren kategori
4. **Vendor Teratas** — performa toko terbaik
5. **Analisis Pendapatan** — daily revenue trend, prediksi
6. **Masalah & Peluang** — apa yang perlu diperbaiki
7. **Rekomendasi Aksi** — 3-5 konkret langkah untuk meningkatkan penjualan

Gunakan data yang diberikan. Tulis dalam format Markdown dengan heading dan bullet points.
Bahasa: Indonesia, tone: profesional tapi friendly.
PROMPT;
    }

    protected function buildAnalysisPrompt(array $stats): string
    {
        $topProducts = json_encode($stats['topProducts'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $topCategories = json_encode($stats['topCategories'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $topShops = json_encode($stats['topShops'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $orderStats = json_encode($stats['orderStats'], JSON_UNESCAPED_UNICODE);
        $dailyRevenue = json_encode($stats['dailyRevenue'], JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Analisis data platform multivendor berikut:

**Pendapatan Total:** Rp {$stats['totalRevenue']}
**Pendapatan Bulan Ini (30 hari):** Rp {$stats['monthRevenue']}

**Top 10 Produk Terlaris (30 hari):**
```json
{$topProducts}
```

**Top 5 Kategori (30 hari):**
```json
{$topCategories}
```

**Top 5 Vendor (30 hari):**
```json
{$topShops}
```

**Statistik Pesanan (30 hari):**
```json
{$orderStats}
```

**Pendapatan Harian (7 hari):**
```json
{$dailyRevenue}
```

Berikan analisis lengkap sesuai format yang diminta.
PROMPT;
    }
}
