<?php

declare(strict_types=1);

namespace App\Search;

use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SpellCorrector
{
    private static ?array $vocabulary = null;

    public static function flush(): void
    {
        self::$vocabulary = null;
    }

    public static function vocabulary(): array
    {
        if (self::$vocabulary !== null) {
            return self::$vocabulary;
        }

        $ttl = (int) config('search.suggest.vocabulary_ttl', 900);
        $size = (int) config('search.suggest.vocabulary_size', 400);

        return self::$vocabulary = cache()->remember('search:vocabulary:v1', $ttl, static function () use ($size): array {
            $terms = [];

            foreach (self::entityTerms(['categories', 'brands', 'shops'], $size) as $term) {
                $terms[$term] = true;
            }

            if (self::tableExists('product_tags')) {
                try {
                    DB::table('product_tags')
                        ->orderByDesc('id')
                        ->limit($size)
                        ->pluck('name')
                        ->each(static function ($name) use (&$terms): void {
                            $normalised = TextNormalizer::normalize((string) $name);
                            if ($normalised !== '') {
                                $terms[$normalised] = true;
                            }
                        });
                } catch (Throwable) {
                }
            }

            if (self::tableExists('search_queries')) {
                try {
                    DB::table('search_queries')
                        ->where('source', 'search')
                        ->where('has_term', true)
                        ->where('zero_result', false)
                        ->where('created_at', '>=', now()->subDays(90))
                        ->groupBy('normalized_query')
                        ->orderByDesc(DB::raw('count(*)'))
                        ->limit($size)
                        ->pluck('normalized_query')
                        ->each(static function ($query) use (&$terms): void {
                            $normalised = (string) $query;
                            if (mb_strlen($normalised) >= 3) {
                                $terms[$normalised] = true;
                            }
                        });
                } catch (Throwable) {
                }
            }

            $list = array_map(static fn ($term): string => (string) $term, array_keys($terms));
            $list = array_values(array_unique(array_filter($list, static fn (string $term): bool => $term !== '')));
            sort($list);

            return array_slice($list, 0, max(1, $size * 3));
        });
    }

    public static function suggest(string $term, int $limit = 3): array
    {
        $normalised = TextNormalizer::normalize($term);
        if ($normalised === '' || mb_strlen($normalised) < 3) {
            return [];
        }

        $threshold = (float) config('search.typo.similarity_threshold', 0.74);
        $vocabulary = self::vocabulary();
        if ($vocabulary === []) {
            return [];
        }

        $candidates = [];

        foreach (self::tokenVariants($normalised) as $token) {
            if (mb_strlen($token) < 3) {
                continue;
            }

        $best = null;
        $bestScore = $threshold;

        foreach ($vocabulary as $candidate) {
            $candidate = (string) $candidate;
            $score = TextNormalizer::similarity($token, $candidate);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $candidate;
                    if ($score >= 0.95) {
                        break;
                    }
                }
            }

            if ($best !== null) {
                $candidates[$best] = $bestScore;
            }
        }

        arsort($candidates);

        $out = [];
        foreach (array_slice(array_keys($candidates), 0, $limit) as $candidate) {
            $out[] = ['term' => $candidate, 'score' => round($candidates[$candidate], 3)];
        }

        return $out;
    }

    public static function isMisspelled(string $term, string $haystack): bool
    {
        $normalised = TextNormalizer::normalize($term);
        if ($normalised === '' || $haystack === '') {
            return false;
        }

        return ! TextNormalizer::matches(preg_split('/\s+/u', $normalised) ?: [], $haystack);
    }

    private static function tokenVariants(string $normalised): array
    {
        $variants = preg_split('/\s+/u', $normalised) ?: [];
        $extra = [];
        foreach ($variants as $token) {
            $extra[] = TextNormalizer::stem($token);
            foreach (SynonymRepository::expand($token) as $synonym) {
                $extra[] = $synonym;
            }
        }

        return array_values(array_unique(array_filter(array_merge($variants, $extra))));
    }

    private static function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    private static function entityTerms(array $tables, int $limit): array
    {
        $terms = [];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            try {
                $rows = DB::table($table)
                    ->orderByDesc('id')
                    ->limit($limit)
                    ->pluck('name');

                foreach ($rows as $name) {
                    $normalised = TextNormalizer::normalize((string) $name);
                    if ($normalised === '') {
                        continue;
                    }
                    $terms[$normalised] = true;
                    foreach (preg_split('/\s+/u', $normalised) ?: [] as $token) {
                        if (mb_strlen($token) >= 3) {
                            $terms[$token] = true;
                        }
                    }
                }
            } catch (Throwable) {
            }
        }

        return array_keys($terms);
    }
}
