<?php

declare(strict_types=1);

namespace App\Services\Cms;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Popup builder storefront (tanpa model baru — query builder + cache).
 *
 * Pola cache mengikuti MenuRenderer: Cache::remember + flush() eksplisit.
 * Statistik memakai kolom counter views_count/clicks_count (ringan,
 * tanpa tabel popup_stats) — increment atomik, guarded hasColumn agar
 * backward-compatible saat migrasi belum jalan.
 */
final class PopupService
{
    public const CACHE_KEY = 'cms_popup_active';

    public const CACHE_TTL = 300;

    public const TARGETINGS = ['all', 'home', 'product', 'cart', 'checkout'];

    /** Tag HTML yang diizinkan pada isi popup (copy BI aman). */
    public const ALLOWED_TAGS = '<p><br><strong><em><u><a><ul><ol><li><span><div><h3><h4><img>';

    /**
     * Popup aktif untuk satu halaman (jadwal + targeting). Null bila tidak ada.
     *
     * @return array<string, mixed>|null
     */
    public static function activeForPage(string $page): ?array
    {
        $page = in_array($page, self::TARGETINGS, true) ? $page : 'all';

        try {
            if (! Schema::hasTable('popups')) {
                return null;
            }

            return Cache::remember(
                self::CACHE_KEY.'_'.$page,
                self::CACHE_TTL,
                function () use ($page): ?array {
                    $now = now()->toDateTimeString();

                    $row = DB::table('popups')
                        ->where('is_active', true)
                        ->where(function ($q) use ($page): void {
                            $q->where('targeting', 'all')->orWhere('targeting', $page);
                        })
                        ->where(function ($q) use ($now): void {
                            $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                        })
                        ->where(function ($q) use ($now): void {
                            $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                        })
                        ->orderByDesc('id')
                        ->first();

                    if ($row === null) {
                        return null;
                    }

                    $popup = (array) $row;
                    $popup['body_html'] = self::sanitize((string) ($popup['body_html'] ?? ''));

                    return $popup;
                },
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Sanitasi isi HTML: buang script/style/iframe, event handler (on*),
     * dan href javascript:/data:.
     */
    public static function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $clean = (string) preg_replace(
            '#<\s*(script|style|iframe|object|embed|form|input|button)[^>]*>.*?<\s*/\s*\1\s*>#is',
            '',
            $html,
        );
        $clean = strip_tags($clean, self::ALLOWED_TAGS);
        // Buang event handler on*=... ("..." atau '...' atau tanpa kutip).
        $clean = (string) preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean);
        // Netralkan href javascript:/data:/vbscript:.
        $clean = (string) preg_replace_callback(
            '/href\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i',
            static function (array $m): string {
                $raw = trim($m[1], "\"'");
                if (preg_match('/^\s*(javascript|data|vbscript|file)\s*:/i', $raw)) {
                    return 'href="#"';
                }

                return $m[0];
            },
            $clean,
        );

        return trim(mb_substr($clean, 0, 10000));
    }

    public static function recordView(int $popupId): void
    {
        self::bump($popupId, 'views_count');
    }

    public static function recordClick(int $popupId): void
    {
        self::bump($popupId, 'clicks_count');
    }

    private static function bump(int $popupId, string $column): void
    {
        try {
            if ($popupId <= 0 || ! Schema::hasTable('popups') || ! Schema::hasColumn('popups', $column)) {
                return;
            }

            DB::table('popups')->where('id', $popupId)->increment($column);
        } catch (\Throwable) {
        }
    }

    public static function flush(?string $page = null): void
    {
        if ($page !== null) {
            Cache::forget(self::CACHE_KEY.'_'.$page);

            return;
        }

        foreach (self::TARGETINGS as $target) {
            Cache::forget(self::CACHE_KEY.'_'.$target);
        }
    }
}
