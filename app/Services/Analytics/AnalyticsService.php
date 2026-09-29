<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Shared query vocabulary for every analytics report.
 *
 * Controllers and Blade templates never build a money expression themselves:
 * they ask one of the report services, which owns the definitions of what
 * counts as a sale, a refund and a commission. Results are memoised for a short
 * window so opening a dashboard with eight tiles costs one query, not eight.
 */
abstract class AnalyticsService
{
    public const CACHE_TTL = 300;

    /**
     * Order states that represent real commerce. A cancelled or failed order is
     * excluded from every revenue figure.
     *
     * @return list<string>
     */
    public static function revenueOrderStatuses(): array
    {
        return [
            OrderStatus::fromStored('confirmed')->stored(),
            OrderStatus::Processing->stored(),
            OrderStatus::Packed->stored(),
            OrderStatus::Shipped->stored(),
            OrderStatus::Delivered->stored(),
            OrderStatus::Completed->stored(),
            OrderStatus::ReturnRequested->stored(),
            OrderStatus::Returned->stored(),
            OrderStatus::RefundPending->stored(),
            OrderStatus::Refunded->stored(),
        ];
    }

    /** @return list<string> */
    public static function paidPaymentStatuses(): array
    {
        return [
            PaymentStatus::Paid->value,
            PaymentStatus::Partial->value,
            PaymentStatus::Refunded->value,
        ];
    }

    /** @return list<string> */
    public static function failedOrderStatuses(): array
    {
        return [
            OrderStatus::Cancelled->stored(),
            OrderStatus::Failed->stored(),
        ];
    }

    /** @return list<string> */
    public static function successfulTransactionStatuses(): array
    {
        return ['success', 'refunded'];
    }

    protected function orders(DateRange $range): Builder
    {
        return \App\Models\Order::query()
            ->whereBetween('orders.created_at', [$range->from, $range->to]);
    }

    protected function revenueOrders(DateRange $range): Builder
    {
        return $this->orders($range)
            ->whereIn('order_status', self::revenueOrderStatuses())
            ->whereIn('payment_status', self::paidPaymentStatuses());
    }

    /**
     * @template TValue
     *
     * @param  callable():TValue  $resolver
     * @return TValue
     */
    protected function remember(string $prefix, DateRange $range, callable $resolver, array $extra = []): mixed
    {
        try {
            return Cache::remember($range->key($prefix, $extra), self::CACHE_TTL, $resolver);
        } catch (\Throwable) {
            return $resolver();
        }
    }

    /**
     * Percentage change, guarding the "previous period was zero" division.
     *
     * @return array{value: float, direction: string, previous: float, current: float}
     */
    protected function delta(float $current, float $previous): array
    {
        if ($previous == 0.0) {
            $direction = $current > 0 ? 'up' : ($current < 0 ? 'down' : 'flat');
            $value = $current > 0 ? 100.0 : 0.0;
        } else {
            $value = (($current - $previous) / abs($previous)) * 100;
            $direction = $value > 0.05 ? 'up' : ($value < -0.05 ? 'down' : 'flat');
        }

        return [
            'value' => round($value, 1),
            'direction' => $direction,
            'current' => round($current, 2),
            'previous' => round($previous, 2),
        ];
    }

    protected function has(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Turn a database DATE() bucket map into a dense, chart-ready series.
     *
     * @param  array<string, float|int>  $buckets
     * @return list<float>
     */
    protected function densify(array $labels, array $buckets, string $key = 'total'): array
    {
        $values = [];

        foreach ($labels as $label) {
            $value = $buckets[$label] ?? 0;
            $values[] = is_array($value) ? (float) ($value[$key] ?? 0) : (float) $value;
        }

        return $values;
    }

    // ── Geo & pin peta (aditif, read-only) ──

    /** Jarak garis lurus (haversine) dalam kilometer. */
    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $arc = asin(min(1.0, sqrt(
            pow(sin($dLat / 2), 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * pow(sin($dLng / 2), 2)
        )));

        return round($earth * 2 * $arc, 3);
    }

    /**
     * Validasi pin lat/lng + radius layanan (bila didukung pemasok radius).
     *
     * @return array{valid: bool, within_radius: bool|null, distance_km: float|null, message: string}
     */
    public static function validatePinRadius(
        ?float $latitude,
        ?float $longitude,
        ?float $centerLatitude = null,
        ?float $centerLongitude = null,
        ?int $radiusKm = null,
    ): array {
        if ($latitude === null || $longitude === null) {
            return ['valid' => false, 'within_radius' => null, 'distance_km' => null, 'message' => 'Pin peta belum dipasang (lat/lng kosong).'];
        }

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return ['valid' => false, 'within_radius' => null, 'distance_km' => null, 'message' => 'Koordinat di luar rentang yang sah.'];
        }

        if ($centerLatitude === null || $centerLongitude === null || $radiusKm === null) {
            return ['valid' => true, 'within_radius' => null, 'distance_km' => null, 'message' => 'Pin sah. Validasi radius tidak didukung (radius kosong).'];
        }

        $distance = self::haversineKm($centerLatitude, $centerLongitude, $latitude, $longitude);
        $within = $distance <= (float) $radiusKm;

        return [
            'valid' => true,
            'within_radius' => $within,
            'distance_km' => $distance,
            'message' => $within
                ? "Pin dalam radius layanan ({$distance} km dari {$radiusKm} km)."
                : "Pin di luar radius layanan ({$distance} km dari {$radiusKm} km).",
        ];
    }

    /**
     * URL embed peta OpenStreetMap tanpa API key (iframe-ready).
     */
    public static function mapEmbedUrl(?float $latitude, ?float $longitude, int $zoom = 15): ?string
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        $delta = max(0.002, 0.06 / max(1, $zoom / 5));
        $bbox = implode(',', [
            number_format($longitude - $delta, 7, '.', ''),
            number_format($latitude - $delta, 7, '.', ''),
            number_format($longitude + $delta, 7, '.', ''),
            number_format($latitude + $delta, 7, '.', ''),
        ]);

        return "https://www.openstreetmap.org/export/embed.html?bbox={$bbox}&layer=mapnik&marker="
            .number_format($latitude, 7, '.', '').','.number_format($longitude, 7, '.', '');
    }

    /**
     * Toko terdekat berdasar kota/provinsi pelanggan.
     * Urutan: kota sama → provinsi sama → fallback existing (rating, nama).
     *
     * @return list<array{id: int, name: string, slug: string, city: string|null, province: string|null, rating: float, score: float, reason: string, latitude: float|null, longitude: float|null, map_url: string|null, shop: \App\Models\Shop}>
     */
    public function nearestShops(?string $city, ?string $province, int $limit = 12): array
    {
        $city = trim((string) ($city ?? ''));
        $province = trim((string) ($province ?? ''));
        $limit = max(1, min(100, $limit));

        try {
            $shops = \App\Models\Shop::query()
                ->where('status', 'active')
                ->orderByDesc('rating_average')
                ->orderBy('name')
                ->limit(max(200, $limit * 10))
                ->get();
        } catch (\Throwable) {
            return [];
        }

        $ranked = $shops->map(function (\App\Models\Shop $shop) use ($city, $province): array {
            $shopCity = trim((string) ($shop->getAttribute('city') ?? ''));
            $shopProvince = trim((string) ($shop->getAttribute('province') ?? ''));
            $score = 0.0;
            $reason = 'Lainnya';

            if ($city !== '' && $shopCity !== '' && mb_strtolower($shopCity) === mb_strtolower($city)) {
                $score += 100.0;
                $reason = 'Sekota dengan Anda';
            } elseif ($province !== '' && $shopProvince !== '' && mb_strtolower($shopProvince) === mb_strtolower($province)) {
                $score += 50.0;
                $reason = 'Seprovinsi dengan Anda';
            }

            $score += min(20.0, (float) ($shop->getAttribute('rating_average') ?? 0) * 4);

            if ($shop->getAttribute('latitude') !== null && $shop->getAttribute('longitude') !== null) {
                $score += 5.0;
            }

            $latitude = $shop->getAttribute('latitude') !== null ? (float) $shop->getAttribute('latitude') : null;
            $longitude = $shop->getAttribute('longitude') !== null ? (float) $shop->getAttribute('longitude') : null;

            return [
                'id' => (int) $shop->getKey(),
                'name' => (string) $shop->name,
                'slug' => (string) $shop->slug,
                'city' => $shopCity !== '' ? $shopCity : null,
                'province' => $shopProvince !== '' ? $shopProvince : null,
                'rating' => (float) ($shop->getAttribute('rating_average') ?? 0),
                'score' => round($score, 2),
                'reason' => $reason,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'map_url' => self::mapEmbedUrl($latitude, $longitude),
                'shop' => $shop,
            ];
        })->sortBy([fn (array $a, array $b): int => $b['score'] <=> $a['score'] ?: strcmp($a['name'], $b['name'])])
            ->values()
            ->take($limit)
            ->all();

        return $ranked;
    }
}
