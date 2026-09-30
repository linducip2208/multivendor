<?php

declare(strict_types=1);

namespace App\Services\Cms;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Landing kampanye + UTM: tangkap dari query, agregat tayang→order→omzet.
 *
 * Penyimpanan sesi dilakukan middleware CaptureUtm; service ini murni
 * logika agregat + pencatatan tayang (tanpa model baru, guarded
 * hasTable/hasColumn agar backward-compatible).
 */
final class LandingTrackingService
{
    /** Param UTM yang dilacak (disimpan ke session + order). */
    public const PARAMS = ['utm_source', 'utm_medium', 'utm_campaign'];

    /**
     * Normalisasi param UTM dari query (hanya yang ada & tak kosong).
     *
     * @param  array<string, mixed>  $query
     * @return array{utm_source?: string, utm_medium?: string, utm_campaign?: string}
     */
    public static function extract(array $query): array
    {
        $out = [];

        foreach (self::PARAMS as $param) {
            $value = $query[$param] ?? null;
            if (! is_string($value)) {
                continue;
            }
            $value = trim($value);
            if ($value === '') {
                continue;
            }
            $out[$param] = mb_substr($value, 0, 120);
        }

        return $out;
    }

    /** Catat satu tayang landing (diabaikan diam-diam bila tabel belum ada). */
    public static function recordView(?int $campaignId, array $utm, ?string $sessionId = null, ?string $url = null): void
    {
        try {
            if (! Schema::hasTable('landing_views')) {
                return;
            }

            DB::table('landing_views')->insert([
                'campaign_id' => $campaignId,
                'utm_source' => $utm['utm_source'] ?? null,
                'utm_medium' => $utm['utm_medium'] ?? null,
                'utm_campaign' => $utm['utm_campaign'] ?? null,
                'session_id' => $sessionId !== null ? mb_substr($sessionId, 0, 120) : null,
                'url' => $url !== null ? mb_substr($url, 0, 500) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable) {
        }
    }

    /**
     * Agregat per campaign/utm: tayang (landing_views) → order + omzet (orders).
     *
     * @return list<array{campaign: string, source: string, views: int, orders: int, revenue: float, conversion_rate: float}>
     */
    public static function aggregate(int $limit = 50): array
    {
        try {
            $viewsByCampaign = [];
            if (Schema::hasTable('landing_views')) {
                $rows = DB::table('landing_views')
                    ->selectRaw('COALESCE(utm_campaign, ?) as campaign, COALESCE(utm_source, ?) as source, COUNT(*) as aggregate', ['(langsung)', '(langsung)'])
                    ->groupBy('campaign', 'source')
                    ->get();
                foreach ($rows as $row) {
                    $viewsByCampaign[(string) $row->campaign."\0".(string) $row->source] = (int) $row->aggregate;
                }
            }

            $ordersByCampaign = [];
            if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'utm_campaign')) {
                $totalCol = Schema::hasColumn('orders', 'total') ? 'total' : 'grand_total';
                $hasTotal = Schema::hasColumn('orders', $totalCol);
                $rows = DB::table('orders')
                    ->selectRaw(
                        'COALESCE(utm_campaign, ?) as campaign, COALESCE(utm_source, ?) as source, COUNT(*) as orders_count'.($hasTotal ? ', COALESCE(SUM('.$totalCol.'), 0) as revenue' : ''),
                        ['(langsung)', '(langsung)'],
                    )
                    ->groupBy('campaign', 'source')
                    ->get();
                foreach ($rows as $row) {
                    $ordersByCampaign[(string) $row->campaign."\0".(string) $row->source] = [
                        'orders' => (int) $row->orders_count,
                        'revenue' => (float) ($row->revenue ?? 0),
                    ];
                }
            }

            $keys = array_unique(array_merge(array_keys($viewsByCampaign), array_keys($ordersByCampaign)));

            $out = [];
            foreach ($keys as $key) {
                [$campaign, $source] = explode("\0", $key) + [1 => '(langsung)'];
                $views = $viewsByCampaign[$key] ?? 0;
                $orders = $ordersByCampaign[$key]['orders'] ?? 0;
                $revenue = round((float) ($ordersByCampaign[$key]['revenue'] ?? 0), 2);
                $out[] = [
                    'campaign' => $campaign,
                    'source' => $source,
                    'views' => $views,
                    'orders' => $orders,
                    'revenue' => $revenue,
                    'conversion_rate' => $views > 0 ? round(($orders / $views) * 100, 2) : 0.0,
                ];
            }

            usort($out, static fn (array $a, array $b): int => [$b['orders'], $b['views']] <=> [$a['orders'], $a['views']]);

            return array_slice($out, 0, max(1, min(200, $limit)));
        } catch (\Throwable) {
            return [];
        }
    }
}
