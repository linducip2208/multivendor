<?php

declare(strict_types=1);

namespace App\Search;

use App\Support\TextNormalizer;

final class TypoWindow
{
    private readonly int $minLength;

    private readonly int $doubleEditLength;

    private readonly int $maxPatternsPerToken;

    public function __construct(int $minLength = 5, int $doubleEditLength = 9, int $maxPatternsPerToken = 18)
    {
        $this->minLength = max(2, $minLength);
        $this->doubleEditLength = max($this->minLength + 1, $doubleEditLength);
        $this->maxPatternsPerToken = max(4, $maxPatternsPerToken);
    }

    public static function fromConfig(): self
    {
        return new self(
            (int) config('search.typo.min_length', 5),
            (int) config('search.typo.double_edit_length', 9),
            (int) config('search.typo.max_patterns_per_token', 18),
            (int) config('search.typo.max_total_patterns', 60),
        );
    }

    public function edits(string $token): int
    {
        $length = mb_strlen($token);

        if ($length >= $this->doubleEditLength) {
            return 2;
        }

        if ($length >= $this->minLength) {
            return 1;
        }

        return 0;
    }

    public function isEligible(string $token): bool
    {
        return $this->edits($token) > 0;
    }

    public function patterns(string $token): array
    {
        return $this->substitutionPatterns($token);
    }

    public function anchors(string $token): array
    {
        $length = mb_strlen($token);
        if ($length < 3) {
            return [];
        }

        $anchors = [
            mb_substr($token, 0, 3, 'UTF-8'),
            mb_substr($token, (int) floor(($length - 3) / 2), 3, 'UTF-8'),
            mb_substr($token, -3, 3, 'UTF-8'),
        ];

        return array_values(array_unique($anchors));
    }

    public function anchorThreshold(string $token): int
    {
        return $this->edits($token) >= 2 ? 2 : 1;
    }

    public static function verify(string $token, string $haystack, float $threshold): bool
    {
        $haystack = TextNormalizer::normalize($haystack);
        if ($haystack === '') {
            return false;
        }

        if (str_contains($haystack, $token)) {
            return true;
        }

        $best = 0.0;
        foreach (preg_split('/\s+/u', $haystack) ?: [] as $word) {
            if ($word === '') {
                continue;
            }
            $best = max($best, self::ratio($token, $word));
            if ($best >= $threshold) {
                return true;
            }
        }

        return $best >= $threshold;
    }

    public static function score(string $token, string $haystack): float
    {
        $haystack = TextNormalizer::normalize($haystack);
        if ($haystack === '') {
            return 0.0;
        }

        if (str_contains($haystack, $token)) {
            return 1.0;
        }

        $best = 0.0;
        foreach (preg_split('/\s+/u', $haystack) ?: [] as $word) {
            if ($word === '') {
                continue;
            }
            $best = max($best, self::ratio($token, $word));
        }

        return $best;
    }

    /**
     * Typo-tolerant similarity: an adjacent transposition counts as one edit,
     * which is how humans actually mistype ("nordface" for "northface").
     */
    public static function ratio(string $a, string $b): float
    {
        $a = TextNormalizer::normalize($a);
        $b = TextNormalizer::normalize($b);

        if ($a === $b) {
            return 1.0;
        }

        if ($a === '' || $b === '') {
            return 0.0;
        }

        $longest = max(mb_strlen($a, 'UTF-8'), mb_strlen($b, 'UTF-8'));
        $distance = self::damerau($a, $b);

        return max(0.0, 1.0 - ($distance / $longest));
    }

    private static function damerau(string $a, string $b): int
    {
        $lengthA = mb_strlen($a, 'UTF-8');
        $lengthB = mb_strlen($b, 'UTF-8');

        if ($lengthA === 0) {
            return $lengthB;
        }

        if ($lengthB === 0) {
            return $lengthA;
        }

        $d = [];
        for ($i = 0; $i <= $lengthA; $i++) {
            $d[$i] = [$i];
        }

        for ($j = 0; $j <= $lengthB; $j++) {
            $d[0][$j] = $j;
        }

        for ($i = 1; $i <= $lengthA; $i++) {
            for ($j = 1; $j <= $lengthB; $j++) {
                $cost = mb_substr($a, $i - 1, 1, 'UTF-8') === mb_substr($b, $j - 1, 1, 'UTF-8') ? 0 : 1;

                $value = min(
                    $d[$i - 1][$j] + 1,
                    $d[$i][$j - 1] + 1,
                    $d[$i - 1][$j - 1] + $cost,
                );

                if ($i > 1 && $j > 1
                    && mb_substr($a, $i - 1, 1, 'UTF-8') === mb_substr($b, $j - 2, 1, 'UTF-8')
                    && mb_substr($a, $i - 2, 1, 'UTF-8') === mb_substr($b, $j - 1, 1, 'UTF-8')) {
                    $value = min($value, $d[$i - 2][$j - 2] + 1);
                }

                $d[$i][$j] = $value;
            }
        }

        return $d[$lengthA][$lengthB];
    }

    private function substitutionPatterns(string $token): array
    {
        $length = mb_strlen($token);
        if ($length < $this->minLength) {
            return [];
        }

        $max = $length >= $this->doubleEditLength
            ? (int) floor($this->maxPatternsPerToken / 2)
            : $this->maxPatternsPerToken;

        $patterns = [];
        for ($i = 0; $i < $length && count($patterns) < $max; $i++) {
            $patterns[] = mb_substr($token, 0, $i, 'UTF-8').'%'.mb_substr($token, $i + 1, null, 'UTF-8');
        }

        for ($i = 0; $i < $length - 1 && count($patterns) < $max; $i++) {
            $left = mb_substr($token, 0, $i, 'UTF-8');
            $a = mb_substr($token, $i, 1, 'UTF-8');
            $b = mb_substr($token, $i + 1, 1, 'UTF-8');
            $right = mb_substr($token, $i + 2, null, 'UTF-8');
            if ($a === $b) {
                continue;
            }
            $patterns[] = $left.$b.$a.$right;
        }

        return array_values(array_unique($patterns));
    }
}
