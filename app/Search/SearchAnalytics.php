<?php

declare(strict_types=1);

namespace App\Search;

use App\Support\TextNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SearchAnalytics
{
    private static int $tableState = 0;

    public static function record(SearchQuery $query, SearchResult $result, ?Request $request = null, string $source = 'search'): void
    {
        if (! (bool) config('search.analytics.enabled', true) || ! self::tableReady()) {
            return;
        }

        $parsed = QueryParser::parse($query->term);
        $normalised = TextNormalizer::normalize($query->term);
        $minLength = (int) config('search.analytics.min_term_length', 2);
        $hasTerm = $normalised !== '' && mb_strlen($normalised) >= $minLength;

        if (! $hasTerm && ! $query->hasFilters()) {
            return;
        }

        if ($query->term !== '' && ! $hasTerm) {
            return;
        }


        $maxLength = (int) config('search.analytics.max_term_length', 255);
        $term = mb_substr(trim($query->term), 0, $maxLength);

        $row = [
            'query' => $term,
            'normalized_query' => mb_substr($normalised, 0, $maxLength),
            'query_hash' => hash('sha256', $normalised),
            'token_count' => count($parsed->rawTokens),
            'result_count' => $result->total,
            'results_shown' => $result->items->count(),
            'has_term' => $hasTerm,
            'zero_result' => $hasTerm && $result->total === 0,
            'used_typo_tolerance' => (bool) ($result->meta['typo_applied'] ?? false),
            'source' => $source,
            'driver' => (string) ($result->meta['driver'] ?? 'unknown'),
            'took_ms' => min(65535, max(0, (int) round($result->tookMs))),
            'filters' => json_encode([
                'sort' => $query->sorts[0] ?? 'relevance',
                'category' => $query->categoryIds,
                'brand' => $query->brandIds,
                'shop' => $query->shopIds,
                'attributes' => $query->attributeFilters,
                'operators' => $parsed->brandTerms !== [] || $parsed->shopTerms !== []
                    || $parsed->categoryTerms !== [] || $parsed->minPrice !== null
                    || $parsed->inStock !== null || $parsed->mustNot !== [],
            ], JSON_UNESCAPED_UNICODE),
            'ip_hash' => self::hash($request?->ip()),
            'visitor_hash' => self::visitorHash($request),
            'locale' => $request === null ? null : mb_substr((string) $request->getPreferredLanguage(['id', 'en']), 0, 10),
            'device' => self::device($request),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        try {
            DB::table('search_queries')->insert($row);
        } catch (Throwable) {
        }
    }

    public static function recordSuggestion(string $term, int $resultCount, ?Request $request = null): void
    {
        if (! (bool) config('search.analytics.log_suggestions', true) || ! self::tableReady()) {
            return;
        }

        $normalised = TextNormalizer::normalize($term);
        if (mb_strlen($normalised) < 3) {
            return;
        }

        $window = max(1, (int) config('search.analytics.suggest_dedupe_seconds', 300));
        $hash = hash('sha256', $normalised);

        try {
            $recent = DB::table('search_queries')
                ->where('source', 'suggest')
                ->where('query_hash', $hash)
                ->where('created_at', '>=', now()->subSeconds($window))
                ->limit(1)
                ->exists();

            if ($recent) {
                return;
            }
        } catch (Throwable) {
        }

        $maxLength = (int) config('search.analytics.max_term_length', 255);

        try {
            DB::table('search_queries')->insert([
                'query' => mb_substr(trim($term), 0, $maxLength),
                'normalized_query' => mb_substr($normalised, 0, $maxLength),
                'query_hash' => $hash,
                'token_count' => count(preg_split('/\s+/u', $normalised) ?: []),
                'result_count' => $resultCount,
                'results_shown' => 0,
                'has_term' => true,
                'zero_result' => $resultCount === 0,
                'used_typo_tolerance' => false,
                'source' => 'suggest',
                'driver' => 'unknown',
                'took_ms' => 0,
                'filters' => null,
                'ip_hash' => self::hash($request?->ip()),
                'visitor_hash' => self::visitorHash($request),
                'locale' => null,
                'device' => self::device($request),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable) {
        }
    }

    public static function zeroResultReport(int $days = 30, int $limit = 100): array
    {
        if (! self::tableReady()) {
            return [];
        }

        try {
            return DB::table('search_queries')
                ->where('source', 'search')
                ->where('zero_result', true)
                ->where('created_at', '>=', now()->subDays(max(1, $days)))
                ->groupBy('normalized_query')
                ->orderByDesc(DB::raw('count(*)'))
                ->limit($limit)
                ->get(['normalized_query', DB::raw('count(*) as occurrences'), DB::raw('max(created_at) as last_seen')])
                ->map(fn ($row) => [
                    'term' => (string) $row->normalized_query,
                    'occurrences' => (int) $row->occurrences,
                    'last_seen' => (string) $row->last_seen,
                ])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    public static function topQueries(int $days = 30, int $limit = 100): array
    {
        if (! self::tableReady()) {
            return [];
        }

        try {
            return DB::table('search_queries')
                ->where('source', 'search')
                ->where('has_term', true)
                ->where('created_at', '>=', now()->subDays(max(1, $days)))
                ->groupBy('normalized_query')
                ->orderByDesc(DB::raw('count(*)'))
                ->limit($limit)
                ->get(['normalized_query', DB::raw('count(*) as occurrences'), DB::raw('sum(result_count) as hits')])
                ->map(fn ($row) => [
                    'term' => (string) $row->normalized_query,
                    'occurrences' => (int) $row->occurrences,
                    'hits' => (int) $row->hits,
                ])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Saran otomatis saat pencarian tidak menghasilkan apa-apa: cari istilah
     * populer dari analitik yang mirip dengan kata kunci gagal, lalu lengkapi
     * dengan istilah zero-result yang paling sering dicari pelanggan lain.
     *
     * @return list<array{term: string, url: string|null, occurrences: int}>
     */
    public static function saranUntukNolHasil(string $term, int $batas = 5): array
    {
        $normal = TextNormalizer::normalize($term);

        if ($normal === '' || ! self::tableReady()) {
            return [];
        }

        $batas = max(1, min(10, $batas));
        $populer = self::topQueries(30, 100);

        if ($populer === []) {
            return [];
        }

        $kataKunci = array_values(array_filter(preg_split('/\s+/u', $normal) ?: []));
        $skor = [];

        foreach ($populer as $row) {
            $calon = (string) ($row['term'] ?? '');

            if ($calon === '' || $calon === $normal) {
                continue;
            }

            $nilai = 0.0;

            if (str_contains($calon, $normal) || str_contains($normal, $calon)) {
                $nilai = 90.0;
            } else {
                foreach ($kataKunci as $kata) {
                    if (mb_strlen($kata) >= 3 && str_contains($calon, $kata)) {
                        $nilai += 25.0;
                    }
                }

                similar_text($normal, $calon, $persen);
                $nilai += (float) $persen / 4;
            }

            // Istilah yang memang menghasilkan produk diprioritaskan.
            if ((int) ($row['hits'] ?? 0) > 0) {
                $nilai += 15.0;
            }

            $skor[] = ['term' => $calon, 'occurrences' => (int) ($row['occurrences'] ?? 0), 'skor' => $nilai];
        }

        usort($skor, static fn (array $a, array $b): int => $b['skor'] <=> $a['skor'] ?: $b['occurrences'] <=> $a['occurrences']);

        $out = [];
        foreach (array_slice($skor, 0, $batas) as $item) {
            if ($item['skor'] <= 1.0 && $out !== []) {
                continue;
            }

            try {
                $url = route('search', ['q' => $item['term']]);
            } catch (Throwable) {
                $url = null;
            }

            $out[] = ['term' => $item['term'], 'url' => $url, 'occurrences' => $item['occurrences']];
        }

        return $out;
    }

    public static function hash(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! (bool) config('search.analytics.hash_ip', true)) {
            return null;
        }

        return hash_hmac('sha256', $value, self::salt());
    }

    private static function visitorHash(?Request $request): ?string
    {
        if ($request === null) {
            return null;
        }

        return hash_hmac('sha256', ($request->ip() ?? '').'|'.mb_substr((string) $request->userAgent(), 0, 200), self::salt());
    }

    private static function device(?Request $request): ?string
    {
        $agent = mb_strtolower((string) $request?->userAgent());

        if ($agent === '') {
            return null;
        }

        if (str_contains($agent, 'ipad') || str_contains($agent, 'tablet')) {
            return 'tablet';
        }
        if (str_contains($agent, 'mobile') || str_contains($agent, 'android') || str_contains($agent, 'iphone')) {
            return 'mobile';
        }

        return 'desktop';
    }

    private static function salt(): string
    {
        $key = (string) config('app.key');
        if ($key !== '') {
            return $key;
        }

        return 'search-analytics-static-salt';
    }

    private static function tableReady(): bool
    {
        if (self::$tableState === 1) {
            return true;
        }

        if (self::$tableState === 2) {
            return false;
        }

        try {
            self::$tableState = Schema::hasTable('search_queries') ? 1 : 2;
        } catch (Throwable) {
            self::$tableState = 2;
        }

        return self::$tableState === 1;
    }
}
