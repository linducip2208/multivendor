<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Indonesian-aware text normalisation for search, slugs and PSEO content.
 *
 * Indonesian is not space-delimited in the same way as English: "sepatubola"
 * and "sepatu bola" are the same word, and plural/prefix morphology is rich
 * (sepatu -> sepatu/sepatunya). The normaliser therefore does light stemming
 * plus an equivalence map instead of trying to be a full stemmer.
 */
final class TextNormalizer
{
    /** @var array<string, string> */
    private const EQUIVALENTS = [
        'sepatubola' => 'sepatu bola',
        'sepatu bola' => 'sepatubola',
        'baju' => 'kemeja',
        'kaos' => 'kaos',
        'handphone' => 'hp',
        'ponsel' => 'hp',
        'telepon genggam' => 'hp',
        'laptop' => 'laptop',
        'komputer' => 'laptop',
        'sandal' => 'sandal',
        'sneakers' => 'sneaker',
        'sendal' => 'sandal',
        'mukena' => 'hijab',
        'kerudung' => 'hijab',
        'sebel' => 'sabun',
        'pecel' => 'keripik',
        'kripik' => 'keripik',
        'chitato' => 'keripik',
        'oreo' => 'biskuit',
        'gula-gula' => 'gula',
        'gula reductase' => 'gula',
    ];

    /** @var list<string> */
    private const SUFFIXES = ['nya', 'lah', 'kah', 'pun', 'ku', 'mu'];

    /** @var list<string> */
    private const PREFIXES = ['ber', 'me', 'pe', 'di', 'ke', 'se'];

    /**
     * Lowercases, strips diacritics, removes punctuation and collapses whitespace.
     */
    public static function normalize(string $value): string
    {
        $value = self::transliterate($value);
        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    /**
     * Tokenises a search phrase into normalised search tokens.
     *
     * @return list<string>
     */
    public static function tokenize(string $value, bool $stem = true): array
    {
        $normalized = self::normalize($value);
        if ($normalized === '') {
            return [];
        }

        $tokens = [];
        foreach (preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            $token = self::EQUIVALENTS[$token] ?? $token;
            if ($stem) {
                $token = self::stem($token);
            }
            if ($token !== '') {
                $tokens[] = $token;
            }
        }

        return array_values(array_unique($tokens));
    }

    /**
     * Very light Indonesian stemmer: strips common clitics and reduplicative
     * prefixes. Deliberately conservative — over-stemming hurts recall more than
     * a missed prefix hurts precision here.
     */
    public static function stem(string $token): string
    {
        if (mb_strlen($token, 'UTF-8') <= 4) {
            return $token;
        }

        foreach (self::SUFFIXES as $suffix) {
            if (str_ends_with($token, $suffix) && mb_strlen($token, 'UTF-8') - mb_strlen($suffix, 'UTF-8') >= 4) {
                $token = mb_substr($token, 0, mb_strlen($token, 'UTF-8') - mb_strlen($suffix, 'UTF-8'), 'UTF-8');
                break;
            }
        }

        foreach (self::PREFIXES as $prefix) {
            if (str_starts_with($token, $prefix) && mb_strlen($token, 'UTF-8') - mb_strlen($prefix, 'UTF-8') >= 4) {
                $token = mb_substr($token, mb_strlen($prefix, 'UTF-8'), null, 'UTF-8');
                break;
            }
        }

        return $token;
    }

    /**
     * Returns true when every token of the query appears in the haystack
     * (used for in-memory relevance ranking of candidate rows).
     *
     * @param list<string> $tokens
     */
    public static function matches(array $tokens, string $haystack): bool
    {
        if ($tokens === []) {
            return true;
        }

        $haystack = self::normalize($haystack);

        foreach ($tokens as $token) {
            if ($token !== '' && ! str_contains($haystack, $token)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Levenshtein-based typo tolerance. Returns a similarity ratio 0..1.
     */
    public static function similarity(string $a, string $b): float
    {
        $a = self::normalize($a);
        $b = self::normalize($b);
        if ($a === $b) {
            return 1.0;
        }
        if ($a === '' || $b === '') {
            return 0.0;
        }

        $distance = levenshtein($a, $b);
        $longest = max(mb_strlen($a, 'UTF-8'), mb_strlen($b, 'UTF-8'));

        return max(0.0, 1.0 - ($distance / $longest));
    }

    public static function transliterate(string $value): string
    {
        $map = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'ā' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ø' => 'o', 'ō' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u',
            'ç' => 'c', 'ñ' => 'n', 'ß' => 's', 'ÿ' => 'y', 'š' => 's', 'ž' => 'z', 'đ' => 'd',
        ];

        return strtr(mb_strtolower($value, 'UTF-8'), $map);
    }

    /**
     * URL-safe slug that keeps Indonesian characters meaningful.
     */
    public static function slug(string $value, string $separator = '-'): string
    {
        $value = preg_replace('/[^\p{L}\p{N}]+/u', $separator, self::transliterate($value)) ?? '';
        $value = preg_replace('/'.preg_quote($separator, '/').'{2,}/', $separator, $value) ?? '';

        return trim($separator === '-' ? preg_replace('/-+/', '-', $value) ?? '' : $value, $separator);
    }
}
