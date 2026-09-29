<?php

declare(strict_types=1);

namespace App\Search;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SynonymRepository
{
    private static ?array $resolved = null;

    private static int $tableState = 0;

    public static function flush(): void
    {
        self::$resolved = null;
        cache()->forget((string) config('search.synonyms.cache_key', 'search:synonyms:v1'));
    }

    public static function expand(string $token): array
    {
        if (! (bool) config('search.synonyms.enabled', true)) {
            return [];
        }

        $map = self::map();
        if ($map === []) {
            return [];
        }

        $group = $map[$token] ?? null;

        return $group === null ? [] : array_values(array_diff($group, [$token]));
    }

    public static function all(): array
    {
        return self::map();
    }

    public static function phrase(string $token): ?string
    {
        $map = self::map();
        $group = $map[$token] ?? null;
        if ($group === null || count($group) < 2) {
            return null;
        }

        $best = null;
        $bestScore = -1.0;
        foreach ($group as $candidate) {
            $score = mb_strlen($candidate) > mb_strlen($token) ? 1.0 : 0.5;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        return $best;
    }

    public static function map(): array
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        $ttl = (int) config('search.synonyms.cache_ttl', 3600);
        $key = (string) config('search.synonyms.cache_key', 'search:synonyms:v1');

        $rows = [];
        try {
            $rows = self::tableReady()
                ? cache()->remember($key, $ttl, static function (): array {
                    $max = (int) config('search.synonyms.max_terms', 500);

                    return DB::table('search_synonyms')
                        ->where('is_active', true)
                        ->orderByDesc('weight')
                        ->orderBy('term')
                        ->limit($max)
                        ->get(['term', 'synonyms'])
                        ->map(static fn ($row) => [
                            'term' => mb_strtolower(trim((string) $row->term)),
                            'synonyms' => json_decode((string) $row->synonyms, true) ?: [],
                        ])
                        ->all();
                })
                : [];
        } catch (Throwable) {
            $rows = [];
        }

        return self::$resolved = self::toMap($rows);
    }

    private static function toMap(array $rows): array
    {
        $parent = [];
        $known = [];

        $find = static function (string $item) use (&$parent, &$find): string {
            if (! isset($parent[$item])) {
                $parent[$item] = $item;
            }
            if ($parent[$item] === $item) {
                return $item;
            }

            return $parent[$item] = $find($parent[$item]);
        };

        $union = static function (string $a, string $b) use (&$parent, &$find): void {
            $ra = $find($a);
            $rb = $find($b);
            if ($ra !== $rb) {
                $parent[$rb] = $ra;
            }
        };

        $members = static function (string $value) use (&$known): array {
            $tokens = [];
            foreach (preg_split('/\s+/u', $value) ?: [] as $token) {
                $token = (string) $token;
                if ($token !== '') {
                    $tokens[$token] = true;
                }
            }

            return array_keys($tokens);
        };

        foreach ($rows as $row) {
            $term = mb_strtolower(trim((string) ($row['term'] ?? '')));
            if ($term === '') {
                continue;
            }

            $known[$term] = true;
            $union($term, $term);

            foreach ((array) ($row['synonyms'] ?? []) as $synonym) {
                $synonym = mb_strtolower(trim((string) $synonym));
                if ($synonym === '') {
                    continue;
                }

                $known[$synonym] = true;
                $union($term, $synonym);

                foreach ($members($synonym) as $token) {
                    $known[$token] = true;
                    $union($term, $token);
                }
            }
        }

        $buckets = [];
        foreach (array_keys($known) as $term) {
            $buckets[$find((string) $term)][(string) $term] = true;
        }

        $map = [];
        foreach ($buckets as $group) {
            $membersList = array_keys($group);
            if (count($membersList) < 2) {
                continue;
            }
            foreach ($membersList as $member) {
                $map[$member] = $membersList;
            }
        }

        return $map;
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
            self::$tableState = Schema::hasTable('search_synonyms') ? 1 : 2;
        } catch (Throwable) {
            self::$tableState = 2;
        }

        return self::$tableState === 1;
    }
}
