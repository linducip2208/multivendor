<?php

declare(strict_types=1);

namespace App\Search;

use App\Support\TextNormalizer;

final class QueryParser
{
    private const OPERATORS = [
        'brand' => 'brand',
        'merek' => 'brand',
        'shop' => 'shop',
        'toko' => 'shop',
        'store' => 'shop',
        'category' => 'category',
        'kategori' => 'category',
        'cat' => 'category',
        'price' => 'price',
        'harga' => 'price',
        'rating' => 'rating',
        'in' => 'in',
        'attr' => 'attribute',
        'attribute' => 'attribute',
    ];

    private const TRUTHY = ['stock', 'tersedia', 'in_stock', 'instock', 'ada', 'true', '1', 'yes', 'y', 'available', 'tersedia_stok'];
    private const FALSY = ['out', 'outofstock', 'habis', 'kosong', 'false', '0', 'no', 'n', 'unavailable', 'stok_habis'];

    /**
     * Kamus koreksi ejaan Bahasa Indonesia (typo umum marketplace).
     * Aditif: tidak mengubah perilaku parse() yang sudah ada.
     *
     * @var array<string, string>
     */
    public const TYPO_MAP = [
        'seaptu' => 'sepatu', 'sepatuu' => 'sepatu', 'spatu' => 'sepatu', 'spattu' => 'sepatu',
        'sapatu' => 'sepatu', 'sepattu' => 'sepatu',
        'sandle' => 'sandal', 'sandall' => 'sandal', 'sandal' => 'sandal',
        'snakers' => 'sneaker', 'sneakerz' => 'sneaker', 'sneakers' => 'sneaker',
        'handpone' => 'handphone', 'hanphone' => 'handphone', 'handhpone' => 'handphone',
        'handpon' => 'handphone', 'hp' => 'hp',
        'poncel' => 'ponsel', 'posel' => 'ponsel', 'phonsel' => 'ponsel',
        'bju' => 'baju', 'bajuu' => 'baju', 'bajju' => 'baju',
        'koas' => 'kaos', 'kaoss' => 'kaos',
        'kameja' => 'kemeja', 'kemej' => 'kemeja', 'kemaja' => 'kemeja',
        'clana' => 'celana', 'celna' => 'celana', 'celanna' => 'celana',
        'jakket' => 'jaket', 'jakett' => 'jaket',
        'tasp' => 'tas', 'tass' => 'tas',
        'ransell' => 'ransel', 'ransle' => 'ransel', 'ramsel' => 'ransel',
        'laptob' => 'laptop', 'leptop' => 'laptop', 'laptap' => 'laptop',
        'krudung' => 'kerudung', 'kerudng' => 'kerudung', 'kerudungg' => 'kerudung',
        'mukenah' => 'mukena', 'mukena' => 'mukena',
        'kripik' => 'keripik', 'kripikk' => 'keripik', 'keripikk' => 'keripik',
        'biskut' => 'biskuit', 'biskuat' => 'biskuit',
        'kacamta' => 'kacamata', 'kacamatta' => 'kacamata',
        'kosmetick' => 'kosmetik', 'kosmetikk' => 'kosmetik',
        'cemilan' => 'camilan', 'cemiilan' => 'camilan',
    ];

    /**
     * Kosakata acuan Bahasa Indonesia untuk koreksi jarak-edit.
     * Aditif: hanya dipakai metode koreksi baru.
     *
     * @var list<string>
     */
    public const VOCABULARY = [
        'sepatu', 'sandal', 'sneaker', 'baju', 'kaos', 'kemeja', 'celana', 'jaket',
        'tas', 'ransel', 'dompet', 'topi', 'handphone', 'ponsel', 'laptop', 'charger',
        'powerbank', 'headset', 'earphone', 'speaker', 'kamera', 'jam', 'arloji',
        'kacamata', 'kosmetik', 'sabun', 'sampo', 'susu', 'kopi', 'teh', 'gula',
        'beras', 'minyak', 'keripik', 'kerupuk', 'biskuit', 'camilan', 'cokelat',
        'mainan', 'boneka', 'buku', 'lampu', 'kabel', 'hijab', 'kerudung', 'mukena',
        'jilbab', 'mukena', 'motor', 'mobil', 'helm', 'kulkas', 'kipas', 'kompor',
        'panci', 'wajan', 'pisau', 'sendok', 'garpu', 'piring', 'gelas', 'botol',
        'tisu', 'popok', 'deterjen', 'masker', 'vitamin', 'madu', 'merah', 'biru',
        'hijau', 'hitam', 'putih', 'besar', 'kecil', 'murah', 'original', 'premium',
        'pria', 'wanita', 'anak', 'bayi', 'bola', 'raket', 'kasur', 'bantal',
    ];

    public static function parse(string $term): ParsedQuery
    {
        $raw = trim($term);
        if ($raw === '') {
            return new ParsedQuery();
        }

        $phrases = self::extractPhrases($raw);
        $working = self::stripPhrases($raw);

        $operators = [];
        $working = self::extractOperators($working, $operators);

        $excluded = self::extractExclusions($working);
        $working = self::stripExclusions($working);

        $text = trim(preg_replace('/\s+/u', ' ', $working) ?? '');

        $rawTokens = TextNormalizer::tokenize($text, false);
        $tokens = TextNormalizer::tokenize($text, true);
        $maxTokens = (int) config('search.ranking.max_tokens', 12);
        $tokens = array_slice($tokens, 0, $maxTokens);

        $excludedTokens = [];
        foreach ($excluded as $word) {
            foreach (TextNormalizer::tokenize($word, true) as $token) {
                $excludedTokens[] = $token;
            }
        }

        $minPrice = null;
        $maxPrice = null;
        $minRating = null;
        $maxRating = null;
        $inStock = null;
        $brands = [];
        $shops = [];
        $categories = [];
        $attributes = [];

        foreach ($operators as $value) {
            [$field, $operand] = $value;

            if ($field === 'brand') {
                $brands[] = $operand;
            } elseif ($field === 'shop') {
                $shops[] = $operand;
            } elseif ($field === 'category') {
                $categories[] = $operand;
            } elseif ($field === 'price') {
                [$lo, $hi] = self::range($operand);
                $minPrice = $lo ?? $minPrice;
                $maxPrice = $hi ?? $maxPrice;
            } elseif ($field === 'rating') {
                [$lo, $hi] = self::ratingRange($operand);
                $minRating = $lo ?? $minRating;
                $maxRating = $hi ?? $maxRating;
            } elseif ($field === 'in') {
                $inStock = self::boolean($operand);
            } elseif ($field === 'attribute') {
                $parts = explode('=', $operand, 2);
                if (count($parts) === 2) {
                    $name = TextNormalizer::normalize($parts[0]);
                    $value = TextNormalizer::normalize($parts[1]);
                    if ($name !== '' && $value !== '') {
                        $attributes[$name] = $value;
                    }
                }
            }
        }

        return new ParsedQuery(
            raw: $raw,
            text: $text,
            phrases: $phrases,
            rawTokens: $rawTokens,
            mustNot: array_values(array_unique($excludedTokens)),
            brandTerms: array_values(array_unique($brands)),
            shopTerms: array_values(array_unique($shops)),
            categoryTerms: array_values(array_unique($categories)),
            minPrice: $minPrice,
            maxPrice: $maxPrice,
            minRating: $minRating,
            maxRating: $maxRating,
            inStock: $inStock,
            attributes: array_filter($attributes, static fn ($v) => $v !== ''),
        );
    }

    public static function normaliseTerm(string $term): string
    {
        return TextNormalizer::normalize($term);
    }

    /**
     * Koreksi ejaan Bahasa Indonesia per kata (aditif, tidak mengubah parse()).
     *
     * Urutan: kamus typo eksplisit dulu, lalu jarak-edit terhadap VOCABULARY
     * untuk kata dengan panjang >= 5 dan kemiripan >= 0,86.
     */
    public static function koreksiEjaan(string $term): string
    {
        $normal = TextNormalizer::normalize($term);

        if ($normal === '') {
            return '';
        }

        $keluar = [];

        foreach (preg_split('/\s+/u', $normal, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $kata) {
            if (isset(self::TYPO_MAP[$kata])) {
                $keluar[] = self::TYPO_MAP[$kata];
                continue;
            }

            if (mb_strlen($kata) >= 5 && ! in_array($kata, self::VOCABULARY, true)) {
                $terbaik = null;
                $skorTerbaik = 0.86;

                foreach (self::VOCABULARY as $rujukan) {
                    $skor = TextNormalizer::similarity($kata, $rujukan);

                    if ($skor > $skorTerbaik) {
                        $skorTerbaik = $skor;
                        $terbaik = $rujukan;

                        if ($skor >= 0.95) {
                            break;
                        }
                    }
                }

                if ($terbaik !== null) {
                    $keluar[] = $terbaik;
                    continue;
                }
            }

            $keluar[] = $kata;
        }

        return implode(' ', $keluar);
    }

    /**
     * Parse cerdas (aditif): koreksi ejaan + token + ekspansi sinonim.
     *
     * @return array{parsed: ParsedQuery, corrected: string, koreksi: list<array{dari: string, ke: string}>, tokens: list<string>, expanded: list<string>}
     */
    public static function parseSmart(string $term): array
    {
        $normal = TextNormalizer::normalize($term);
        $corrected = self::koreksiEjaan($term);

        $koreksi = [];
        $sebelum = $normal !== '' ? preg_split('/\s+/u', $normal, -1, PREG_SPLIT_NO_EMPTY) ?: [] : [];
        $sesudah = $corrected !== '' ? preg_split('/\s+/u', $corrected, -1, PREG_SPLIT_NO_EMPTY) ?: [] : [];

        foreach ($sebelum as $indeks => $kata) {
            $hasil = $sesudah[$indeks] ?? $kata;

            if ($hasil !== $kata) {
                $koreksi[] = ['dari' => $kata, 'ke' => $hasil];
            }
        }

        $basis = $corrected !== '' ? $corrected : $term;
        $parsed = self::parse($basis);
        $tokens = TextNormalizer::tokenize($basis);
        $mentah = TextNormalizer::tokenize($basis, false);

        $expanded = array_values(array_unique(array_merge($tokens, $mentah)));

        foreach (array_merge($tokens, $mentah) as $token) {
            foreach (SynonymRepository::expandSmart($token) as $padanan) {
                if (! in_array($padanan, $expanded, true)) {
                    $expanded[] = $padanan;
                }
            }

            $batang = TextNormalizer::stem($token);

            if ($batang !== '' && $batang !== $token && ! in_array($batang, $expanded, true)) {
                $expanded[] = $batang;
            }
        }

        return [
            'parsed' => $parsed,
            'corrected' => $corrected,
            'koreksi' => $koreksi,
            'tokens' => array_values($tokens),
            'expanded' => array_values($expanded),
        ];
    }

    private static function extractPhrases(string $term): array
    {
        $phrases = [];
        if (preg_match_all('/"([^"]{2,80})"|\'([^\']{2,80})\'/u', $term, $matches) > 0) {
            foreach ($matches[0] as $index => $match) {
                $phrase = TextNormalizer::normalize($matches[1][$index] !== '' ? $matches[1][$index] : ($matches[2][$index] ?? ''));
                if ($phrase !== '') {
                    $phrases[] = $phrase;
                }
            }
        }

        return array_values(array_unique($phrases));
    }

    private static function stripPhrases(string $term): string
    {
        $stripped = preg_replace('/"[^"]{0,80}"|\'[^\']{0,80}\'/u', ' ', $term) ?? $term;

        return trim(preg_replace('/\s+/u', ' ', $stripped) ?? '');
    }

    private static function extractOperators(string $term, array &$operators): string
    {
        $keys = implode('|', array_keys(self::OPERATORS));
        $pattern = '/(^|\s)('.$keys.'):("[^"]+"|\'[^\']+\'|\S+)/ui';

        $stripped = preg_replace_callback($pattern, static function (array $matches) use (&$operators): string {
            $field = self::OPERATORS[strtolower($matches[2])] ?? $matches[2];
            $operand = trim($matches[3], "\"'");
            if ($operand !== '') {
                $operators[] = [$field, $operand];
            }

            return ' ';
        }, $term) ?? $term;

        return trim(preg_replace('/\s+/u', ' ', $stripped) ?? '');
    }

    private static function extractExclusions(string $term): array
    {
        $excluded = [];
        if (preg_match_all('/(^|\s)-(?![A-Za-z_]+:)(?:"([^"]+)"|\'([^\']+)\'|(\S+))/u', $term, $matches) > 0) {
            foreach ($matches[0] as $index => $match) {
                $word = $matches[2][$index] ?? '';
                $word = $word !== '' ? $word : ($matches[3][$index] ?? '');
                $word = $word !== '' ? $word : ($matches[4][$index] ?? '');
                $normalised = TextNormalizer::normalize($word);
                if ($normalised !== '') {
                    $excluded[] = $normalised;
                }
            }
        }

        return array_values(array_unique($excluded));
    }

    private static function stripExclusions(string $term): string
    {
        $stripped = preg_replace('/(^|\s)-(?![A-Za-z_]+:)(?:"[^"]+"|\'[^\']+\'|\S+)/u', ' ', $term) ?? $term;

        return trim(preg_replace('/\s+/u', ' ', $stripped) ?? '');
    }

    private static function range(string $value): array
    {
        $value = str_replace([' ', '.'], ['', ''], $value);

        if (preg_match('/^(\d+(?:\d+)?)-(\d+(?:\d+)?)$/', $value, $m) === 1) {
            return [(float) $m[1], (float) $m[2]];
        }

        if (preg_match('/^>\s*(\d+(?:\d+)?)$/', $value, $m) === 1) {
            return [(float) $m[1], null];
        }

        if (preg_match('/^>=\s*(\d+(?:\d+)?)$/', $value, $m) === 1) {
            return [(float) $m[1], null];
        }

        if (preg_match('/^<\s*(\d+(?:\d+)?)$/', $value, $m) === 1) {
            return [null, (float) $m[1]];
        }

        if (preg_match('/^<=\s*(\d+(?:\d+)?)$/', $value, $m) === 1) {
            return [null, (float) $m[1]];
        }

        if (is_numeric($value)) {
            return [(float) $value, (float) $value];
        }

        return [null, null];
    }

    private static function ratingRange(string $value): array
    {
        $value = trim($value);

        if (str_ends_with($value, '+') && is_numeric(substr($value, 0, -1))) {
            return [(float) substr($value, 0, -1), null];
        }

        if (str_contains($value, '-') && ! str_starts_with($value, '-')) {
            [$lo, $hi] = array_pad(explode('-', $value, 2), 2, '');
            if (is_numeric($lo) && is_numeric($hi)) {
                return [(float) $lo, (float) $hi];
            }
        }

        if (is_numeric($value)) {
            return [(float) $value, null];
        }

        return [null, null];
    }

    private static function boolean(string $value): ?bool
    {
        $value = mb_strtolower(trim($value));

        if (in_array($value, self::TRUTHY, true)) {
            return true;
        }

        if (in_array($value, self::FALSY, true)) {
            return false;
        }

        return null;
    }
}
