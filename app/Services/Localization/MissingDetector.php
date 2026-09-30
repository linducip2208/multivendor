<?php

declare(strict_types=1);

namespace App\Services\Localization;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Detektor kunci yang hilang + laporan cakupan.
 */
class MissingDetector
{
    public const CACHE_KEY = 'i18n:missing';

    /**
     * @var list<array{locale: string, group: string, key: string, at: string}>
     */
    private static array $runtimeMisses = [];

    public function logMiss(string $locale, string $group, string $key): void
    {
        self::$runtimeMisses[] = [
            'locale' => $locale,
            'group' => strtolower($group),
            'key' => $key,
            'at' => now()->toDateTimeString(),
        ];

        try {
            $logged = Cache::get(self::CACHE_KEY, []);
            $signature = $locale.'|'.strtolower($group).'|'.$key;
            $logged[$signature] = end(self::$runtimeMisses);
            Cache::put(self::CACHE_KEY, $logged, 86400);
        } catch (\Throwable) {
        }
    }

    /**
     * @return list<array{locale: string, group: string, key: string, at: string}>
     */
    public function runtimeMisses(): array
    {
        return self::$runtimeMisses;
    }

    public static function flushRuntime(): void
    {
        self::$runtimeMisses = [];
    }

    /**
     * Kunci yang ada di referensi tapi belum ada / kosong di locale target.
     *
     * @return list<array{namespace: string, key: string, full: string}>
     */
    public function missingKeys(string $locale, string $reference = 'en'): array
    {
        $repo = app(TranslationRepository::class);
        $locale = $repo->normalizeLocale($locale);
        $reference = $repo->normalizeLocale($reference);

        $refRows = DB::table('translations')
            ->where('locale', $reference)
            ->whereNotNull('value')->where('value', '!=', '')
            ->get(['group', 'key']);

        if ($refRows->isEmpty()) {
            return [];
        }

        $target = DB::table('translations')
            ->where('locale', $locale)
            ->whereNotNull('value')->where('value', '!=', '')
            ->get(['group', 'key'])
            ->map(fn ($r) => $r->group."\0".$r->key)
            ->flip();

        $missing = [];
        foreach ($refRows as $row) {
            if (! isset($target[$row->group."\0".$row->key])) {
                $missing[] = [
                    'namespace' => (string) $row->group,
                    'key' => (string) $row->key,
                    'full' => $row->group.'.'.$row->key,
                ];
            }
        }

        return $missing;
    }

    /**
     * @return array{locale: string, reference: string, missing_count: int, missing: list<array{namespace: string, key: string, full: string}>, coverage: array<string, mixed>}
     */
    public function report(string $locale, string $reference = 'en'): array
    {
        $missing = $this->missingKeys($locale, $reference);
        $coverage = app(TranslationRepository::class)->coverage($locale, $reference);

        return [
            'locale' => $locale,
            'reference' => $reference,
            'missing_count' => count($missing),
            'missing' => $missing,
            'coverage' => $coverage,
        ];
    }

    public function clear(): void
    {
        self::flushRuntime();
        Cache::forget(self::CACHE_KEY);
    }
}
