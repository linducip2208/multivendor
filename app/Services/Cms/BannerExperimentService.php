<?php

declare(strict_types=1);

namespace App\Services\Cms;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A/B banner: varian dalam grup eksperimen (bobot tampil), CTR per varian,
 * dan pemenang otomatis (CTR tertinggi, min. sampel impresi).
 *
 * Tanpa model baru: memakai tabel banners langsung (kolom experiment_key,
 * weight, impressions, clicks dari migrasi 2026_09_30_090000_cms_conversion).
 * Semua tulis guarded hasColumn agar backward-compatible.
 */
final class BannerExperimentService
{
    /** Sampel minimum impresi agar varian layak jadi pemenang. */
    public const MIN_SAMPLE = 100;

    /**
     * Pilih satu varian dari grup eksperimen secara terbobot (weight).
     *
     * @return array<string, mixed>|null
     */
    public static function pick(string $experimentKey): ?array
    {
        try {
            if (trim($experimentKey) === '' || ! Schema::hasTable('banners')) {
                return null;
            }

            $query = DB::table('banners')->where('experiment_key', $experimentKey);

            if (Schema::hasColumn('banners', 'status')) {
                $query->where('status', true);
            }

            $variants = $query->orderBy('id')->get();
            if ($variants->isEmpty()) {
                return null;
            }

            $hasWeight = Schema::hasColumn('banners', 'weight');
            $total = 0;
            foreach ($variants as $variant) {
                $total += max(0, (int) ($hasWeight ? ($variant->weight ?? 100) : 100));
            }

            if ($total <= 0) {
                return (array) $variants->random();
            }

            $roll = random_int(1, $total);
            foreach ($variants as $variant) {
                $roll -= max(0, (int) ($hasWeight ? ($variant->weight ?? 100) : 100));
                if ($roll <= 0) {
                    return (array) $variant;
                }
            }

            return (array) $variants->last();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function recordImpression(int $bannerId): void
    {
        self::bump($bannerId, 'impressions');
    }

    public static function recordClick(int $bannerId): void
    {
        self::bump($bannerId, 'clicks');
    }

    public static function ctr(int $impressions, int $clicks): float
    {
        if ($impressions <= 0 || $clicks <= 0) {
            return 0.0;
        }

        return round(($clicks / $impressions) * 100, 2);
    }

    /**
     * Laporan CTR per varian + pemenang otomatis.
     *
     * @return array{experiment: string, min_sample: int, variants: list<array{id: int, title: string, impressions: int, clicks: int, ctr: float}>, winner_id: int|null}
     */
    public static function report(string $experimentKey, int $minSample = self::MIN_SAMPLE): array
    {
        $empty = ['experiment' => $experimentKey, 'min_sample' => $minSample, 'variants' => [], 'winner_id' => null];

        try {
            if (trim($experimentKey) === '' || ! Schema::hasTable('banners')) {
                return $empty;
            }

            $rows = DB::table('banners')
                ->where('experiment_key', $experimentKey)
                ->orderBy('id')
                ->get(['id', 'title', 'impressions', 'clicks']);

            $variants = [];
            foreach ($rows as $row) {
                $impressions = (int) ($row->impressions ?? 0);
                $clicks = (int) ($row->clicks ?? 0);
                $variants[] = [
                    'id' => (int) $row->id,
                    'title' => (string) ($row->title ?? '#'.(int) $row->id),
                    'impressions' => $impressions,
                    'clicks' => $clicks,
                    'ctr' => self::ctr($impressions, $clicks),
                ];
            }

            $winnerId = null;
            $bestCtr = -1.0;
            foreach ($variants as $variant) {
                if ($variant['impressions'] >= $minSample && $variant['ctr'] > $bestCtr) {
                    $bestCtr = $variant['ctr'];
                    $winnerId = $variant['id'];
                }
            }

            return [
                'experiment' => $experimentKey,
                'min_sample' => $minSample,
                'variants' => $variants,
                'winner_id' => $winnerId,
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }

    private static function bump(int $bannerId, string $column): void
    {
        try {
            if ($bannerId <= 0 || ! Schema::hasTable('banners') || ! Schema::hasColumn('banners', $column)) {
                return;
            }

            DB::table('banners')->where('id', $bannerId)->increment($column);
        } catch (\Throwable) {
        }
    }
}
