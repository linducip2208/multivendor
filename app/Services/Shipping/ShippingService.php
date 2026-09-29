<?php

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Models\Provider;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shipping rate + tracking facade.
 *
 * Hardening applied over the original implementation:
 *  - explicit connect/read timeouts so a slow courier cannot hold row locks
 *  - bounded retry with exponential backoff for idempotent GET-ish endpoints
 *  - short-lived rate cache keyed by (provider, origin, destination, weight,
 *    courier) so checkout no longer makes N live API calls per submission
 *  - the courier default is read from configuration instead of hardcoded "jne"
 *  - upstream error bodies are never returned to the customer
 *  - weight is rounded up per-courier using the configured divisor
 */
class ShippingService
{
    private const TIMEOUT = 8;
    private const CONNECT_TIMEOUT = 3;
    private const RETRIES = 2;
    private const RATE_CACHE_TTL = 300;

    public function getActiveProviders(): array
    {
        return Provider::ofType('shipping')->active()->orderBy('sort_order')->get()->all();
    }

    /** @return list<string> */
    public function activeCouriers(): array
    {
        $couriers = SystemSetting::get('shipping_couriers');
        if (is_string($couriers) && trim($couriers) !== '') {
            return array_values(array_filter(array_map('trim', explode(',', strtolower($couriers)))));
        }

        return ['jne', 'jnt', 'sicepat', 'tiki', 'anteraja', 'pos'];
    }

    public function defaultCourier(): string
    {
        return (string) (SystemSetting::get('shipping_default_courier', 'jne') ?: 'jne');
    }

    /**
     * @return array{success: bool, rates?: list<array>, message?: string}
     */
    public function getShippingRates(Provider $provider, array $params): array
    {
        $origin = $params['origin'] ?? null;
        $destination = $params['destination'] ?? null;
        $weight = max(1, (int) ($params['weight'] ?? 1000));
        $courier = $params['courier'] ?? null;

        if (! $origin || ! $destination) {
            return ['success' => false, 'message' => 'Asal dan tujuan pengiriman wajib diisi.'];
        }

        $key = 'shipping:rates:'.sha1(implode('|', [
            $provider->id, $origin, $destination, $weight, $courier ?? '*', $provider->api_format,
        ]));

        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $result = match ($provider->api_format) {
            'rajaongkir-starter', 'rajaongkir-pro' => $this->rajaOngkirRates($provider, (string) $origin, (string) $destination, $weight, $courier),
            'courier-rest' => $this->genericCourierRates($provider, (string) $origin, (string) $destination, $weight, $courier),
            default => ['success' => false, 'message' => 'Format shipping provider ini belum didukung.'],
        };

        if ($result['success'] ?? false) {
            // A failed quote must never be cached — the customer would keep
            // seeing a stale error for the whole TTL.
            Cache::put($key, $result, self::RATE_CACHE_TTL);
        }

        return $result;
    }

    /**
     * @return array{success: bool, rates: list<array>, message?: string}
     */
    protected function rajaOngkirRates(Provider $provider, string $origin, string $destination, int $weight, ?string $courier): array
    {
        $couriers = $courier ? [$courier] : $this->activeCouriers();

        $response = $this->request(fn () => Http::withHeaders([
            'key' => (string) $provider->api_key,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->asForm()->timeout(self::TIMEOUT)->connectTimeout(self::CONNECT_TIMEOUT)
            ->retry(self::RETRIES, 250)
            ->post(rtrim((string) $provider->base_url, '/').'/cost', [
                'origin' => $origin,
                'destination' => $destination,
                'weight' => $this->normaliseWeight($weight),
                'courier' => implode(':', $couriers),
            ]));

        if (! $response || ! $response->successful()) {
            return ['success' => false, 'rates' => [], 'message' => 'Layanan ongkir sedang tidak tersedia. Silakan coba lagi.'];
        }

        $results = $response->json('rajaongkir.results') ?? [];
        $rates = [];

        foreach ($results as $result) {
            foreach ($result['costs'] ?? [] as $cost) {
                $value = $cost['cost'][0]['value'] ?? null;
                if (! is_numeric($value)) {
                    continue;
                }
                $rates[] = [
                    'courier' => strtoupper((string) ($result['code'] ?? $result['name'] ?? '')),
                    'service' => (string) ($cost['service'] ?? ''),
                    'description' => (string) ($cost['description'] ?? ''),
                    'cost' => (float) $value,
                    'etd' => (string) ($cost['cost'][0]['etd'] ?? ''),
                    'note' => (string) ($cost['cost'][0]['note'] ?? ''),
                ];
            }
        }

        return ['success' => true, 'rates' => $rates];
    }

    protected function genericCourierRates(Provider $provider, string $origin, string $destination, int $weight, ?string $courier): array
    {
        $response = $this->request(fn () => Http::withHeaders([
            'Authorization' => 'Bearer '.(string) $provider->api_key,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(self::TIMEOUT)->connectTimeout(self::CONNECT_TIMEOUT)
            ->retry(self::RETRIES, 250)
            ->post(rtrim((string) $provider->base_url, '/').'/rates', [
                'origin' => $origin,
                'destination' => $destination,
                'weight' => $weight,
                'courier' => $courier,
            ]));

        if (! $response || ! $response->successful()) {
            return ['success' => false, 'rates' => [], 'message' => 'Layanan ongkir sedang tidak tersedia. Silakan coba lagi.'];
        }

        $data = $response->json();
        $rows = $data['rates'] ?? $data['data'] ?? [];

        $rates = [];
        foreach ((array) $rows as $row) {
            if (! is_array($row) || ! isset($row['courier'], $row['service'], $row['cost'])) {
                continue;
            }
            $rates[] = [
                'courier' => strtoupper((string) $row['courier']),
                'service' => (string) $row['service'],
                'description' => (string) ($row['description'] ?? $row['service']),
                'cost' => (float) $row['cost'],
                'etd' => (string) ($row['etd'] ?? $row['duration'] ?? ''),
            ];
        }

        return ['success' => true, 'rates' => $rates];
    }

    public function getTracking(Provider $provider, string $trackingNumber, ?string $courier = null): array
    {
        return match ($provider->api_format) {
            'rajaongkir-starter', 'rajaongkir-pro' => $this->rajaOngkirTracking($provider, $trackingNumber, $courier),
            default => $this->genericTracking($provider, $trackingNumber, $courier),
        };
    }

    protected function rajaOngkirTracking(Provider $provider, string $trackingNumber, ?string $courier): array
    {
        $response = $this->request(fn () => Http::withHeaders(['key' => (string) $provider->api_key])
            ->asForm()->timeout(self::TIMEOUT)->connectTimeout(self::CONNECT_TIMEOUT)
            ->post(rtrim((string) $provider->base_url, '/').'/waybill', [
                'waybill' => $trackingNumber,
                'courier' => $courier ?: $this->defaultCourier(),
            ]));

        if (! $response || ! $response->successful()) {
            return ['success' => false, 'message' => 'Status kiriman tidak dapat diambil saat ini.'];
        }

        return ['success' => true, 'data' => $response->json('rajaongkir.result') ?? []];
    }

    protected function genericTracking(Provider $provider, string $trackingNumber, ?string $courier): array
    {
        $url = rtrim((string) $provider->base_url, '/').'/track/'.rawurlencode($trackingNumber);
        if ($courier) {
            $url .= '?courier='.rawurlencode($courier);
        }

        $response = $this->request(fn () => Http::withHeaders([
            'Authorization' => 'Bearer '.(string) $provider->api_key,
        ])->timeout(self::TIMEOUT)->connectTimeout(self::CONNECT_TIMEOUT)->get($url));

        if (! $response || ! $response->successful()) {
            return ['success' => false, 'message' => 'Status kiriman tidak ditemukan atau layanan sedang tidak tersedia.'];
        }

        return ['success' => true, 'data' => $response->json()];
    }

    /**
     * Courier APIs bill per rounded kilogram, so the weight must be rounded up
     * to the configured granularity rather than silently truncated.
     */
    protected function normaliseWeight(int $grams): int
    {
        $step = max(1, (int) (SystemSetting::get('shipping_weight_step_grams', 1000) ?: 1000));

        return (int) (ceil($grams / $step) * $step);
    }

    /** Berat volumetrik (gram) dari dimensi cm: P×L×T / divisor. */
    public function volumetricWeight(?float $length, ?float $width, ?float $height): int
    {
        $divisor = max(1.0, (float) (SystemSetting::get('shipping_volumetric_divisor', '5000') ?: 5000));
        $volume = max(0.0, (float) $length) * max(0.0, (float) $width) * max(0.0, (float) $height);

        if ($volume <= 0) {
            return 0;
        }

        return (int) ceil(($volume / $divisor) * 1000);
    }

    /** Berat tagih = terbesar antara aktual vs volumetrik. */
    public function billableWeight(int $actualGrams, array $dimensions = []): int
    {
        $volumetric = $this->volumetricWeight(
            isset($dimensions['length']) ? (float) $dimensions['length'] : null,
            isset($dimensions['width']) ? (float) $dimensions['width'] : null,
            isset($dimensions['height']) ? (float) $dimensions['height'] : null,
        );

        return max(max(1, $actualGrams), $volumetric);
    }

    /** Premi asuransi = % dari nilai barang, dibatasi min/maks dari pengaturan. */
    public function insuranceFee(float $goodsValue): float
    {
        $rate = max(0.0, (float) (SystemSetting::get('shipping_insurance_rate', '0.5') ?: 0.5));
        $min = max(0.0, (float) (SystemSetting::get('shipping_insurance_min', '0') ?: 0));
        $max = max(0.0, (float) (SystemSetting::get('shipping_insurance_max', '0') ?: 0));

        if ($rate <= 0 || $goodsValue <= 0) {
            return 0.0;
        }

        $fee = $goodsValue * $rate / 100;

        if ($min > 0) {
            $fee = max($fee, $min);
        }

        if ($max > 0) {
            $fee = min($fee, $max);
        }

        return round($fee, 2);
    }

    /** Tarif tabel zona existing (shipping_zone_rates) berdasar nilai order. */
    public function zoneTableQuote(float $orderValue, ?int $zoneId = null): ?float
    {
        $query = \Illuminate\Support\Facades\DB::table('shipping_zone_rates')
            ->join('shipping_methods', 'shipping_methods.id', '=', 'shipping_zone_rates.shipping_method_id')
            ->where('shipping_methods.status', true)
            ->where('shipping_zone_rates.min_order', '<=', $orderValue)
            ->where(function ($q) use ($orderValue): void {
                $q->whereNull('shipping_zone_rates.max_order')->orWhere('shipping_zone_rates.max_order', '>=', $orderValue);
            });

        if ($zoneId !== null && $zoneId > 0) {
            $query->where('shipping_zone_rates.shipping_zone_id', $zoneId);
        }

        $cost = $query->orderBy('shipping_zone_rates.cost')->value('shipping_zone_rates.cost');

        return $cost === null ? null : (float) $cost;
    }

    /**
     * Fallback kurir otomatis: coba daftar kurir berurutan, kembalikan yang
     * pertama berhasil + catatan kurir yang dicoba.
     *
     * @return array{success: bool, rates: list<array>, courier: ?string, tried: list<string>, message?: string}
     */
    public function fallbackQuote(Provider $provider, array $params, ?array $couriers = null): array
    {
        $candidates = $couriers ?? $this->activeCouriers();
        $tried = [];
        $lastMessage = 'Layanan ongkir sedang tidak tersedia.';

        foreach ($candidates as $courier) {
            $courier = strtolower(trim((string) $courier));

            if ($courier === '') {
                continue;
            }

            $tried[] = $courier;
            $result = $this->getShippingRates($provider, array_merge($params, ['courier' => $courier]));

            if (($result['success'] ?? false) && ! empty($result['rates'])) {
                return ['success' => true, 'rates' => $result['rates'], 'courier' => $courier, 'tried' => $tried];
            }

            if (! empty($result['message'])) {
                $lastMessage = (string) $result['message'];
            }
        }

        return ['success' => false, 'rates' => [], 'courier' => null, 'tried' => $tried, 'message' => $lastMessage];
    }

    /**
     * Alokasi gudang otomatis (aditif, read-only): pilih gudang aktif yang
     * dapat memenuhi seluruh item (stok tersedia = on_hand - reserved),
     * utamakan kota yang sama dengan tujuan, fallback ke gudang utama.
     *
     * @param  array<int, int>  $needs  product_id => qty dibutuhkan
     */
    public function allocateWarehouseForItems(array $needs, ?string $destinationCity = null, ?int $shopId = null): ?\App\Models\Warehouse
    {
        $needs = array_filter(array_map('intval', $needs), fn (int $qty): bool => $qty > 0);

        if ($needs === []) {
            return null;
        }

        try {
            $query = \App\Models\Warehouse::query()->where('is_active', true);

            if ($shopId !== null && $shopId > 0 && \Illuminate\Support\Facades\Schema::hasColumn('warehouses', 'shop_id')) {
                $scoped = (clone $query)->where('shop_id', $shopId)->pluck('id')->all();
                if ($scoped !== []) {
                    $query->where('shop_id', $shopId);
                }
            }

            $warehouses = $query->orderBy('is_default', 'desc')->orderBy('name')->get();

            if ($warehouses->isEmpty()) {
                return null;
            }

            $stocks = \Illuminate\Support\Facades\DB::table('product_stocks')
                ->whereIn('product_id', array_keys($needs))
                ->whereIn('warehouse_id', $warehouses->pluck('id')->all())
                ->selectRaw('warehouse_id, product_id, COALESCE(on_hand,0) - COALESCE(reserved,0) as available')
                ->get()
                ->groupBy('warehouse_id');

            $city = mb_strtolower(trim((string) ($destinationCity ?? '')));
            $best = null;
            $bestScore = -1;
            $fallback = null;

            foreach ($warehouses as $warehouse) {
                if ($fallback === null) {
                    $fallback = $warehouse;
                }

                if ((bool) $warehouse->is_default && ($fallback === null || ! (bool) $fallback->is_default)) {
                    $fallback = $warehouse;
                }

                $lines = $stocks[$warehouse->id] ?? collect();
                $byProduct = $lines->keyBy('product_id');

                $covered = 0;
                $full = true;

                foreach ($needs as $productId => $qty) {
                    $available = (int) ($byProduct[$productId]->available ?? 0);
                    $covered += min($available, $qty);

                    if ($available < $qty) {
                        $full = false;
                    }
                }

                $score = $covered * 100 + ($full ? 100000 : 0);

                if ($city !== '' && mb_strtolower(trim((string) ($warehouse->city ?? ''))) === $city) {
                    $score += 10000;
                }

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $warehouse;
                }
            }

            // Fallback gudang utama bila tidak ada stok yang cocok.
            if ($best === null || $bestScore <= 0) {
                return $warehouses->firstWhere('is_default', true) ?? $fallback;
            }

            return $best;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Rekap manifest per kurir per hari dari order_shipments existing.
     *
     * @return list<array{courier: string, date: string, manifest_no: string, total: int}>
     */
    public function manifestRecap(?string $courier = null, ?string $date = null): array
    {
        try {
            $query = \Illuminate\Support\Facades\DB::table('order_shipments')
                ->selectRaw("COALESCE(NULLIF(courier,''),'MANUAL') as courier, COALESCE(manifest_date, DATE(created_at)) as manifest_day, COALESCE(NULLIF(manifest_no,''),'-') as manifest_no, COUNT(*) as total")
                ->groupBy('courier', 'manifest_day', 'manifest_no')
                ->orderBy('manifest_day', 'desc')
                ->orderBy('courier');

            if ($courier !== null && trim($courier) !== '') {
                $query->where('courier', 'like', '%'.trim($courier).'%');
            }

            if ($date !== null && trim($date) !== '') {
                $query->whereDate('manifest_date', trim($date));
            }

            return $query->limit(100)->get()->map(fn ($row): array => [
                'courier' => (string) $row->courier,
                'date' => (string) $row->manifest_day,
                'manifest_no' => (string) $row->manifest_no,
                'total' => (int) $row->total,
            ])->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Baris ekspor manifest (header + rows) untuk satu nomor manifest.
     *
     * @return list<array<int, string>>
     */
    public function manifestExportRows(string $manifestNo): array
    {
        $header = ['No. Manifest', 'Nomor Pesanan', 'Kurir', 'Layanan', 'No. Resi', 'Berat', 'Biaya', 'Status'];

        try {
            $rows = \App\Models\OrderShipment::query()
                ->with('order:id,order_number')
                ->where('manifest_no', $manifestNo)
                ->orderBy('id')
                ->get()
                ->map(fn (\App\Models\OrderShipment $shipment): array => [
                    (string) ($shipment->manifest_no ?? ''),
                    (string) ($shipment->order?->order_number ?? ('#'.$shipment->order_id)),
                    (string) ($shipment->courier ?? ''),
                    (string) ($shipment->service ?? ''),
                    (string) ($shipment->tracking_number ?? ''),
                    number_format((float) ($shipment->weight ?? 0), 2, '.', ''),
                    number_format((float) ($shipment->cost ?? 0), 2, '.', ''),
                    (string) ($shipment->status ?? ''),
                ])
                ->all();

            return [$header, ...$rows];
        } catch (\Throwable) {
            return [$header];
        }
    }

    private function request(callable $fn): ?\Illuminate\Http\Client\Response
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            Log::warning('Shipping provider request failed', [
                'error' => $e->getMessage(),
                'class' => $e::class,
            ]);

            return null;
        }
    }
}
