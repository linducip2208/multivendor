<?php

declare(strict_types=1);

namespace App\Services\Vendor;

use App\Models\AiUsage;
use App\Models\Order;
use App\Models\Product;
use App\Models\Provider;
use App\Services\Ai\AiService;
use App\Support\Currency;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Vendor AI assistant.
 *
 * Every prompt is assembled from this shop's own data and prefixed with a
 * hard system instruction that forbids disclosure of anything outside it, so a
 * model can never be coaxed into returning another tenant's rows. Usage is
 * metered against the plan and always recorded, success or failure.
 */
final class VendorAiService
{
    public const FEATURES = [
        'product_description' => 'Deskripsi produk',
        'store_bio' => 'Profil toko',
        'customer_reply' => 'Balasan pelanggan',
        'sales_insight' => 'Analisis penjualan',
    ];

    private const GUARDRAIL = <<<'TEXT'
    You are an in-app assistant for a single marketplace seller.
    Answer only from the CONTEXT supplied by the application. Never reveal,
    guess or reconstruct data about any other seller, order, customer or account.
    Never output credentials, bank details, API keys or internal identifiers.
    Reply in clear Indonesian. If the context does not contain the answer, say so.
    TEXT;

    public function __construct(private readonly VendorScope $scope) {}

    /** @return array<string, mixed> */
    public function index(): array
    {
        $shopId = $this->scope->shopId();
        $from = now()->subDays(29)->startOfDay();

        $usage = AiUsage::query()
            ->where('shop_id', $shopId)
            ->where('created_at', '>=', $from)
            ->select('feature', DB::raw('COUNT(*) as calls'), DB::raw('COALESCE(SUM(estimated_cost), 0) as cost'))
            ->groupBy('feature')
            ->get()
            ->mapWithKeys(fn (AiUsage $row): array => [
                (string) $row->feature => ['calls' => (int) $row->calls, 'cost' => (float) $row->cost],
            ]);

        $limit = app(VendorSubscriptionService::class)->limit('transactions');

        return [
            'features' => self::FEATURES,
            'usage' => $usage,
            'calls' => (int) collect($usage)->sum('calls'),
            'cost' => (float) collect($usage)->sum('cost'),
            'provider' => $this->providerName(),
            'quota' => $limit,
        ];
    }

    public function generate(string $feature, array $payload): string
    {
        if (! array_key_exists($feature, self::FEATURES)) {
            throw ValidationException::withMessages(['feature' => 'Fitur AI tidak dikenali.']);
        }

        $provider = $this->provider();

        if ($provider === null) {
            throw ValidationException::withMessages([
                'feature' => 'Belum ada penyedia AI aktif. Hubungi administrator platform.',
            ]);
        }

        $prompt = $this->promptFor($feature, $payload);
        $started = microtime(true);

        $result = app(AiService::class)->chat($provider, $prompt, self::GUARDRAIL);

        $duration = (int) round((microtime(true) - $started) * 1000);
        $success = (bool) ($result['success'] ?? false);

        $this->record($feature, $provider, $result, $duration, $success);

        if (! $success) {
            Log::warning('Vendor AI request failed', [
                'shop_id' => $this->scope->shopId(),
                'feature' => $feature,
            ]);

            throw ValidationException::withMessages([
                'feature' => 'Permintaan AI gagal diproses. Coba lagi sebentar lagi.',
            ]);
        }

        return trim((string) ($result['content'] ?? $result['message'] ?? '')) ?: 'Model tidak mengembalikan jawaban.';
    }

    private function promptFor(string $feature, array $payload): string
    {
        return match ($feature) {
            'product_description' => $this->productPrompt($payload),
            'store_bio' => $this->storePrompt($payload),
            'customer_reply' => $this->replyPrompt($payload),
            'sales_insight' => $this->insightPrompt(),
            default => '',
        };
    }

    private function productPrompt(array $payload): string
    {
        $product = $this->ownProduct((int) ($payload['product_id'] ?? 0));

        $context = [
            'nama' => $product?->name,
            'kategori' => $product?->category?->name,
            'harga' => $product ? Currency::format($product->effective_price) : null,
            'stok' => $product?->current_stock,
            'deskripsi' => $product?->short_description ?: strip_tags((string) $product?->description),
            'atribut' => $product?->attributes?->map(fn ($attribute): array => [
                'nama' => $attribute->name,
                'nilai' => $attribute->value ?? $attribute->attribute_value_id,
            ])->all(),
        ];

        $tone = VendorScope::clean($payload['tone'] ?? 'profesional', 40);
        $length = VendorScope::clean($payload['length'] ?? 'menengah', 40);

        return "Tulis deskripsi produk berbahasa Indonesia dengan gaya {$tone} sepanjang {$length}.\n"
            ."Konteks produk (JSON): ".json_encode($context, JSON_UNESCAPED_UNICODE)."\n"
            .'Kembalikan satu paragraf deskripsi siap pakai tanpa judul.';
    }

    private function storePrompt(array $payload): string
    {
        $shop = $this->scope->shop();

        $context = [
            'nama_toko' => $shop->name,
            'kota' => $shop->city,
            'provinsi' => $shop->province,
            'deskripsi' => $shop->description,
            'jumlah_produk' => (int) Product::query()->where('shop_id', $shop->getKey())->count(),
            'kategori' => Product::query()
                ->where('shop_id', $shop->getKey())
                ->with('category:id,name')
                ->limit(20)
                ->get()
                ->map(fn (Product $product): string => (string) ($product->category?->name ?? 'lainnya'))
                ->unique()
                ->values()
                ->all(),
        ];

        $tone = VendorScope::clean($payload['tone'] ?? 'ramah', 40);

        return "Tulis profil toko berbahasa Indonesia dengan gaya {$tone}, maksimal 3 kalimat.\n"
            ."Konteks toko (JSON): ".json_encode($context, JSON_UNESCAPED_UNICODE);
    }

    private function replyPrompt(array $payload): string
    {
        $order = $payload['order_id'] !== null && $payload['order_id'] !== ''
            ? $this->ownOrder((int) $payload['order_id'])
            : null;

        $context = [
            'nomor_pesanan' => $order?->order_number,
            'status' => $order?->order_status,
            'pembayaran' => $order?->payment_status,
            'pelanggan' => $order?->customer?->name,
            'catatan' => $order?->note,
        ];

        $message = VendorScope::clean($payload['message'] ?? '', 1000);
        $tone = VendorScope::clean($payload['tone'] ?? 'sopan', 40);

        return "Balas pesan pelanggan berikut dengan gaya {$tone} dan jangan menjanjikan hal di luar konteks.\n"
            ."Konteks pesanan (JSON): ".json_encode($context, JSON_UNESCAPED_UNICODE)."\n"
            ."Pesan pelanggan: {$message}";
    }

    private function insightPrompt(): string
    {
        $shopId = $this->scope->shopId();
        $from = now()->subDays(29)->startOfDay();

        $orders = Order::query()
            ->where('shop_id', $shopId)
            ->where('created_at', '>=', $from);

        $topProducts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.shop_id', $shopId)
            ->where('orders.created_at', '>=', $from)
            ->groupBy('order_items.product_name')
            ->selectRaw('order_items.product_name, SUM(order_items.quantity) as units, SUM(order_items.sub_total) as revenue')
            ->orderByDesc('revenue')
            ->limit(8)
            ->get()
            ->map(fn (object $row): array => [
                'produk' => $row->product_name,
                'unit' => (int) $row->units,
                'pendapatan' => Currency::format($row->revenue),
            ])->all();

        $context = [
            'periode' => $from->toDateString().' s/d '.now()->toDateString(),
            'total_pesanan' => (int) (clone $orders)->count(),
            'pendapatan' => Currency::format((float) (clone $orders)->sum('sub_total')),
            'produk_terlaris' => $topProducts,
            'produk_stok_menipis' => Product::query()
                ->where('shop_id', $shopId)
                ->whereColumn('current_stock', '<=', DB::raw('COALESCE(low_stock_threshold, 0)'))
                ->limit(8)
                ->pluck('name')
                ->all(),
        ];

        return "Analisis performa penjualan toko berikut dan berikan tiga rekomendasi yang dapat langsung dikerjakan.\n"
            ."Konteks (JSON): ".json_encode($context, JSON_UNESCAPED_UNICODE);
    }

    private function ownProduct(int $productId): ?Product
    {
        if ($productId <= 0) {
            return null;
        }

        return Product::query()
            ->where('shop_id', $this->scope->shopId())
            ->whereKey($productId)
            ->with(['category:id,name', 'attributes'])
            ->first();
    }

    private function ownOrder(int $orderId): ?Order
    {
        return Order::query()
            ->where('shop_id', $this->scope->shopId())
            ->whereKey($orderId)
            ->with('customer:id,name')
            ->first();
    }

    private function provider(): ?Provider
    {
        return Provider::query()
            ->where('type', 'ai')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->first();
    }

    private function providerName(): ?string
    {
        return $this->provider()?->name;
    }

    /** @param  array<string, mixed>  $result */
    private function record(string $feature, Provider $provider, array $result, int $duration, bool $success): void
    {
        try {
            AiUsage::query()->create([
                'user_id' => $this->scope->userId(),
                'shop_id' => $this->scope->shopId(),
                'provider_id' => $provider->getKey(),
                'feature' => $feature,
                'model' => VendorScope::cleanNullable($result['model'] ?? null, 120),
                'prompt_tokens' => (int) ($result['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($result['completion_tokens'] ?? 0),
                'estimated_cost' => Money::of($result['cost'] ?? 0)->maxZero()->toDecimal(),
                'duration_ms' => $duration,
                'success' => $success,
                'error' => $success ? null : 'provider_error',
            ]);
        } catch (\Throwable $exception) {
            Log::debug('Vendor AI usage could not be recorded', ['error' => $exception->getMessage()]);
        }
    }
}
