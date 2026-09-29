<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Services\Ai\AiService;
use App\Services\Ai\ReportAggregator;
use App\Support\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * AI report analysis.
 *
 * The prompts live in {@see ReportAggregator}. The upstream error is never
 * echoed: only a short, sanitised summary is returned so a gateway's response
 * body cannot leak into the browser or into a log.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly AiService $ai,
        private readonly ReportAggregator $aggregator,
    ) {}

    public function index(): View
    {
        $stats = $this->aggregator->gather();

        return view('admin.reports.index', [
            'aiProviders' => Provider::ofType('ai')->active()->get(),
            'stats' => [
                [
                    'label' => 'Total Pendapatan',
                    'value' => Currency::format((float) $stats['totalRevenue']),
                    'icon' => 'cash',
                    'color' => 'success',
                ],
                [
                    'label' => 'Total Pesanan',
                    'value' => Currency::number((int) $stats['orderStats']['total']),
                    'icon' => 'shopping-cart',
                    'color' => 'primary',
                ],
                [
                    'label' => 'Total Vendor',
                    'value' => Currency::number((int) \App\Models\Shop::query()->where('status', 'active')->count()),
                    'icon' => 'store',
                    'color' => 'info',
                ],
                [
                    'label' => 'Total Produk',
                    'value' => Currency::number((int) \App\Models\Product::query()->where('status', 'approved')->count()),
                    'icon' => 'package',
                    'color' => 'warning',
                ],
            ],
            'topProducts' => $stats['topProducts'],
            'topCategories' => $stats['topCategories'],
            'topShops' => $stats['topShops'],
            'windowDays' => (int) $stats['window_days'],
        ]);
    }

    public function aiAnalysis(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider_id' => ['required', 'integer', 'exists:providers,id'],
            'model' => ['nullable', 'string', 'max:160'],
        ]);

        $provider = Provider::query()->where('type', 'ai')->active()->find($validated['provider_id']);

        if ($provider === null) {
            return response()->json(['error' => 'Provider AI tidak ditemukan atau sedang nonaktif.'], 422);
        }

        try {
            $stats = $this->aggregator->gather();
        } catch (Throwable) {
            return response()->json(['error' => 'Data laporan tidak dapat dibaca saat ini.'], 500);
        }

        try {
            $result = $this->ai->chat(
                $provider,
                $this->aggregator->prompt($stats),
                $this->aggregator->systemPrompt(),
                $validated['model'] ?? null,
                ['temperature' => 0.3, 'max_tokens' => 3000],
            );
        } catch (Throwable) {
            return response()->json(['error' => 'Layanan AI tidak dapat dihubungi.'], 502);
        }

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'error' => $this->sanitise((string) ($result['error'] ?? 'Gagal analisis AI')),
            ], 502);
        }

        return response()->json([
            'success' => true,
            'content' => (string) ($result['content'] ?? ''),
            'model' => (string) ($result['model'] ?? ($validated['model'] ?? 'default')),
            'tokens' => is_array($result['tokens'] ?? null) ? $result['tokens'] : [],
            'advisory' => true,
        ]);
    }

    public function fetchModels(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider_id' => ['required', 'integer', 'exists:providers,id'],
        ]);

        $provider = Provider::query()->where('type', 'ai')->active()->find($validated['provider_id']);

        if ($provider === null) {
            return response()->json(['success' => false, 'error' => 'Provider AI tidak ditemukan.'], 422);
        }

        try {
            $result = $this->ai->fetchModels($provider);
        } catch (Throwable) {
            return response()->json(['success' => false, 'error' => 'Daftar model tidak dapat diambil.'], 502);
        }

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'error' => $this->sanitise((string) ($result['error'] ?? 'Gagal mengambil model')),
            ], 502);
        }

        $models = array_values(array_map('strval', (array) ($result['models'] ?? [])));

        return response()->json(['success' => true, 'models' => $models]);
    }

    /**
     * Reduce an upstream error to a short, single-line message. Upstream bodies
     * frequently contain provider keys, request ids and internal stack traces;
     * none of that belongs in a browser response.
     */
    private function sanitise(string $error): string
    {
        $message = trim((string) preg_replace('/\s+/', ' ', $error));

        if ($message === '') {
            return 'Layanan AI tidak merespons. Coba lagi nanti.';
        }

        $redacted = (string) preg_replace(
            '/(sk|pk|key|token|secret|bearer)[-_a-z0-9]{6,}/i',
            '$1***',
            $message,
        );

        return \Illuminate\Support\Str::limit($redacted, 160);
    }
}
