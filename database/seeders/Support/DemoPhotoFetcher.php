<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Facades\Http;

/**
 * Foto produk ASLI untuk data demo via Wikimedia Commons API
 * (gratis, tanpa API key, berlisensi bebas).
 *
 * Alur: kata kunci Indonesia → Inggris → cari di Commons → unduh 800px →
 * simpan lokal storage/app/public/products/photo/. Hasil per kata kunci
 * di-cache di memori proses agar 3000 produk hanya mengunduh ±120 berkas.
 * Atribusi dicatat di attribution.json. Gagal → null (fallback SVG).
 */
final class DemoPhotoFetcher
{
    private const ENDPOINT = 'https://commons.wikimedia.org/w/api.php';

    /** Kata kunci Indonesia → Inggris (urutan spesifik dulu). */
    private const KEYWORDS = [
        'sepatu' => 'sneakers shoes', 'sandal' => 'sandals', 'sneakers' => 'sneakers',
        'smartphone' => 'smartphone', 'hp' => 'smartphone', 'handphone' => 'smartphone',
        'ponsel' => 'mobile phone', 'tablet' => 'tablet computer',
        'laptop' => 'laptop', 'notebook' => 'laptop', 'komputer' => 'desktop computer',
        'kamera' => 'camera', 'canon' => 'canon camera', 'nikon' => 'nikon camera',
        'headset' => 'headphones', 'earphone' => 'earphones', 'tws' => 'wireless earbuds',
        'kaos' => 'tshirt', 'kemeja' => 'shirt', 'baju' => 'clothes', 'jaket' => 'jacket',
        'celana' => 'jeans', 'dress' => 'dress', 'gaun' => 'dress', 'rok' => 'skirt',
        'topi' => 'hat', 'kacamata' => 'sunglasses',
        'tas' => 'handbag', 'ransel' => 'backpack', 'koper' => 'suitcase', 'dompet' => 'wallet',
        'jam' => 'wristwatch',
        'kosmetik' => 'cosmetics', 'lipstik' => 'lipstick', 'skincare' => 'skincare',
        'parfum' => 'perfume', 'obat' => 'medicine', 'vitamin' => 'vitamins',
        'buku' => 'book', 'novel' => 'novel book', 'komik' => 'comics',
        'bola' => 'soccer ball', 'futsal' => 'futsal', 'badminton' => 'badminton racket',
        'fitness' => 'dumbbell', 'gym' => 'gym equipment',
        'helm' => 'motorcycle helmet', 'motor' => 'motorcycle', 'mobil' => 'car',
        'mainan' => 'toys', 'boneka' => 'teddy bear',
        'kopi' => 'coffee', 'teh' => 'tea', 'mie' => 'noodles', 'coklat' => 'chocolate',
        'susu' => 'milk', 'minum' => 'soft drink',
        'panci' => 'cooking pot', 'dapur' => 'kitchen', 'masak' => 'cooking',
        'lampu' => 'lamp', 'kursi' => 'chair', 'meja' => 'table', 'sofa' => 'sofa',
        'rumah' => 'house',
        'kucing' => 'cat', 'anjing' => 'dog', 'hewan' => 'pets', 'bayi' => 'baby',
        'sepeda' => 'bicycle',
    ];

    private const CATEGORY_FALLBACK = [
        'smartphone' => 'smartphone', 'laptop' => 'laptop', 'elektronik' => 'electronics gadget',
        'fashion-pria' => 'mens clothing', 'fashion-wanita' => 'dress',
        'fashion' => 'clothing', 'rumah-tangga' => 'home appliances',
        'olahraga' => 'sports equipment', 'buku' => 'books',
    ];

    /** Kata yang bila muncul di judul file → hasil ditolak (bukan foto produk). */
    private const TITLE_BLOCKLIST = [
        'portrait', 'person', 'people', 'diagram', 'map', 'logo', 'flag',
        'painting', 'sculpture', 'building', 'church', 'castle',
    ];

    /** @var array<string, list<string>> */
    private static array $memory = [];

    public static function keywordFor(string $text, string $categorySlug, string $categoryName): string
    {
        $lower = strtolower($text.' '.$categorySlug.' '.$categoryName);

        foreach (self::KEYWORDS as $keyword => $english) {
            if (str_contains($lower, $keyword)) {
                return $english;
            }
        }

        foreach (self::CATEGORY_FALLBACK as $slug => $english) {
            if (str_contains($lower, $slug)) {
                return $english;
            }
        }

        return 'product';
    }

    /**
     * @return list<string> path relatif storage (maks $count), kosong bila gagal.
     */
    public static function fetch(string $keyword, int $count = 3): array
    {
        $key = strtolower(trim($keyword));
        if (isset(self::$memory[$key])) {
            return array_slice(self::$memory[$key], 0, $count);
        }

        $paths = [];
        try {
            $res = Http::withHeaders(['User-Agent' => 'MultivendorDemo/1.0 (demo seeding; contact admin)'])
                ->timeout(25)
                ->get(self::ENDPOINT, [
                    'action' => 'query', 'format' => 'json',
                    'generator' => 'search', 'gsrsearch' => $key,
                    'gsrnamespace' => 6, 'gsrlimit' => 12,
                    'prop' => 'imageinfo', 'iiprop' => 'url|size|user',
                    'iiurlwidth' => 800,
                ]);

            if (! $res->successful()) {
                return [];
            }

            $pages = (array) ($res->json('query.pages') ?? []);

            // Relevansi judul dulu (kata kunci muncul di nama file),
            // buang potret/diagram/peta — lalu urutan bawaan Commons.
            $words = array_filter(explode(' ', strtolower($key)));
            usort($pages, function ($a, $b) use ($words): int {
                $ta = strtolower((string) ($a['title'] ?? ''));
                $tb = strtolower((string) ($b['title'] ?? ''));
                $badA = self::titleBlocked($ta) ? 1 : 0;
                $badB = self::titleBlocked($tb) ? 1 : 0;
                if ($badA !== $badB) {
                    return $badA <=> $badB;
                }
                $hitA = self::titleHits($ta, $words);
                $hitB = self::titleHits($tb, $words);
                if ($hitA !== $hitB) {
                    return $hitB <=> $hitA;
                }

                return (($a['index'] ?? 99) <=> ($b['index'] ?? 99));
            });

            foreach ($pages as $page) {
                if (count($paths) >= $count) {
                    break;
                }
                $info = $page['imageinfo'][0] ?? null;
                if (! is_array($info)) {
                    continue;
                }
                $url = (string) ($info['thumburl'] ?? $info['url'] ?? '');
                $url = strtok($url, '?');
                if ($url === '' || $url === false) {
                    continue;
                }
                if ((int) ($info['thumbwidth'] ?? $info['width'] ?? 0) < 400) {
                    continue;
                }
                $saved = self::download($key, $url, count($paths));
                if ($saved !== null) {
                    $paths[] = $saved;
                    self::attribute($saved, $page, $info);
                }
            }
        } catch (\Throwable) {
            return $paths;
        }

        self::$memory[$key] = $paths;

        return $paths;
    }

    private static function titleBlocked(string $title): bool
    {
        foreach (self::TITLE_BLOCKLIST as $bad) {
            if (str_contains($title, $bad)) {
                return true;
            }
        }

        return false;
    }

    private static function titleHits(string $title, array $words): int
    {
        $hits = 0;
        foreach ($words as $word) {
            if (strlen($word) > 2 && str_contains($title, $word)) {
                $hits++;
            }
        }

        return $hits;
    }

    private static function download(string $keyword, string $url, int $index): ?string
    {
        try {
            $ext = strtolower((string) pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $ext = 'jpg';
            }

            $dir = storage_path('app/public/products/photo/'.preg_replace('/[^a-z0-9-]+/', '-', $keyword));
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $file = $dir.'/'.substr(sha1($url), 0, 12).'-'.$index.'.'.$ext;
            if (is_file($file) && filesize($file) > 10240) {
                return self::relative($file);
            }

            $res = Http::withHeaders(['User-Agent' => 'MultivendorDemo/1.0 (demo seeding)'])
                ->timeout(40)->get($url);

            if (! $res->successful()) {
                return null;
            }
            $body = (string) $res->body();
            if (strlen($body) < 10240) {
                return null;
            }
            $type = (string) $res->header('Content-Type');
            if ($type !== '' && ! str_starts_with($type, 'image/')) {
                return null;
            }

            file_put_contents($file, $body);

            return self::relative($file);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function relative(string $file): string
    {
        return ltrim(str_replace('\\', '/', substr($file, strlen((string) storage_path('app/public/')))), '/');
    }

    private static function attribute(string $saved, array $page, array $info): void
    {
        try {
            $manifest = storage_path('app/public/products/photo/attribution.json');
            $data = is_file($manifest) ? (json_decode((string) file_get_contents($manifest), true) ?: []) : [];
            $data[$saved] = [
                'title' => $page['title'] ?? null,
                'author' => $info['user'] ?? null,
                'source' => $info['descriptionurl'] ?? null,
            ];
            file_put_contents($manifest, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (\Throwable) {
        }
    }
}
