<?php

namespace Database\Seeders\Support;

/**
 * Ilustrasi SVG prosedural untuk data demo.
 *
 * Setiap produk mendapat gambar UNIK yang relevan dengan kategorinya:
 * latar gradien per kategori + ikon vektor kategori + nama produk/brand.
 * Sepenuhnya offline, ringan (±3KB), dan deterministik (seed dari slug).
 *
 * Berkas ditulis ke storage/app/public/products/demo/ (git-ignored) dan
 * disajikan lewat route img.serve (/img/...).
 */
final class DemoProductImage
{
    /** Kata kunci nama → ikon. Urutan penting (spesifik dulu). */
    private const KEYWORDS = [
        'sepatu' => 'shoe', 'sandal' => 'shoe', 'sneakers' => 'shoe',
        'smartphone' => 'phone', 'hp' => 'phone', 'handphone' => 'phone',
        'ponsel' => 'phone', 'tablet' => 'phone',
        'laptop' => 'laptop', 'notebook' => 'laptop', 'komputer' => 'laptop',
        'kamera' => 'camera', 'canon' => 'camera', 'nikon' => 'camera', 'sony' => 'camera',
        'headset' => 'headphones', 'earphone' => 'headphones', 'tws' => 'headphones',
        'kaos' => 'shirt', 'kemeja' => 'shirt', 'baju' => 'shirt', 'jaket' => 'shirt',
        'dress' => 'dress', 'gaun' => 'dress', 'rok' => 'dress',
        'tas' => 'bag', 'ransel' => 'bag', 'koper' => 'bag', 'dompet' => 'bag',
        'jam' => 'watch', 'rolex' => 'watch', 'casio' => 'watch', 'seiko' => 'watch',
        'kosmetik' => 'lipstick', 'lipstik' => 'lipstick', 'skincare' => 'lipstick',
        'parfum' => 'bottle',
        'obat' => 'pill', 'vitamin' => 'pill', 'suplemen' => 'pill',
        'buku' => 'book', 'novel' => 'book', 'komik' => 'book',
        'bola' => 'ball', 'futsal' => 'ball', 'badminton' => 'ball', 'yonex' => 'ball',
        'fitness' => 'ball', 'gym' => 'ball',
        'helm' => 'helmet', 'otomotif' => 'helmet', 'motor' => 'helmet', 'mobil' => 'helmet',
        'mainan' => 'toy', 'lego' => 'toy', 'boneka' => 'toy',
        'kopi' => 'cup', 'teh' => 'cup', 'minum' => 'cup', 'susu' => 'cup', 'botol' => 'cup',
        'makan' => 'snack', 'snack' => 'snack', 'mie' => 'snack', 'coklat' => 'snack', 'kue' => 'snack',
        'susu' => 'cup',
        'panci' => 'pot', 'dapur' => 'pot', 'masak' => 'pot', 'kompor' => 'pot',
        'lampu' => 'lamp',
        'kursi' => 'chair', 'meja' => 'chair', 'furniture' => 'chair', 'sofa' => 'chair',
        'rumah' => 'home',
        'kucing' => 'paw', 'anjing' => 'paw', 'hewan' => 'paw', 'pet' => 'paw',
        'bayi' => 'bottle', 'popok' => 'bottle',
    ];

    /** Slug/nama kategori → ikon + hue dasar. */
    private const CATEGORIES = [
        'smartphone' => ['phone', 222], 'laptop' => ['laptop', 210],
        'elektronik' => ['phone', 222],
        'fashion-pria' => ['shirt', 200], 'fashion-wanita' => ['dress', 330],
        'fashion' => ['shirt', 265],
        'rumah-tangga' => ['home', 25],
        'olahraga' => ['ball', 145],
        'buku' => ['book', 35],
    ];

    /**
     * @return string path relatif storage (mis. products/demo/sepatu/abc-0.svg)
     */
    public static function forProduct(string $productName, string $brand, string $categorySlug, string $categoryName, string $seed, int $variant = 0): string
    {
        [$icon, $hue] = self::resolve($productName.' '.$brand, $categorySlug, $categoryName);

        $dir = storage_path('app/public/products/demo/'.$categorySlug);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = preg_replace('/[^a-z0-9-]+/', '-', strtolower($seed)).'-'.$variant.'.svg';
        $html = self::svg($icon, $hue, $variant, $productName, $brand, $categoryName);
        file_put_contents($dir.'/'.$file, $html);

        return 'products/demo/'.$categorySlug.'/'.$file;
    }

    /** @return array{0:string,1:int} */
    public static function resolve(string $text, string $categorySlug, string $categoryName): array
    {
        $lower = strtolower($text.' '.$categorySlug.' '.$categoryName);

        foreach (self::KEYWORDS as $keyword => $icon) {
            if (str_contains($lower, $keyword)) {
                return [$icon, self::hueFor($icon)];
            }
        }

        foreach (self::CATEGORIES as $slug => [$icon, $hue]) {
            if (str_contains($lower, $slug)) {
                return [$icon, $hue];
            }
        }

        return ['box', self::hueFor('box')];
    }

    private static function hueFor(string $icon): int
    {
        return abs(crc32($icon)) % 360;
    }

    private static function svg(string $icon, int $hue, int $variant, string $name, string $brand, string $category): string
    {
        $h2 = ($hue + 40) % 360;
        $shapes = self::shapes($icon);
        $lines = self::wrap($name, 20);
        $brandLine = mb_strimwidth($brand.' • '.$category, 0, 34);

        $title = htmlspecialchars($lines[0] ?? $name, ENT_QUOTES, 'UTF-8');
        $subtitle = htmlspecialchars($lines[1] ?? '', ENT_QUOTES, 'UTF-8');
        $brandEsc = htmlspecialchars($brandLine, ENT_QUOTES, 'UTF-8');

        // Variasi komposisi per varian galeri (geser ikon + aksen).
        $dx = [-40, 40, 0][$variant % 3];
        $dy = [20, -10, 40][$variant % 3];

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800">
<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="hsl({$hue},65%,42%)"/><stop offset="1" stop-color="hsl({$h2},70%,26%)"/></linearGradient></defs>
<rect width="800" height="800" fill="url(#g)"/>
<circle cx="680" cy="120" r="180" fill="#ffffff" opacity="0.08"/>
<circle cx="120" cy="660" r="140" fill="#ffffff" opacity="0.07"/>
<circle cx="640" cy="620" r="60" fill="#ffffff" opacity="0.10"/>
<g transform="translate({$dx},{$dy})"><g transform="translate(400,300)" stroke="#ffffff" stroke-width="14" stroke-linecap="round" stroke-linejoin="round" fill="none" opacity="0.95">{$shapes}</g></g>
<rect y="560" width="800" height="240" fill="#000000" opacity="0.30"/>
<text x="60" y="645" font-family="Verdana,sans-serif" font-size="44" font-weight="bold" fill="#ffffff">{$title}</text>
<text x="60" y="695" font-family="Verdana,sans-serif" font-size="34" fill="#ffffff" opacity="0.85">{$subtitle}</text>
<text x="60" y="745" font-family="Verdana,sans-serif" font-size="26" fill="#ffd166">{$brandEsc}</text>
</svg>
SVG;
    }

    /** Pecah nama jadi maks 2 baris seimbang. */
    private static function wrap(string $name, int $perLine): array
    {
        $words = preg_split('/\s+/u', trim($name)) ?: [];
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            if (mb_strlen($current.' '.$word) > $perLine && $current !== '') {
                $lines[] = trim($current);
                $current = $word;
            } else {
                $current = trim($current.' '.$word);
            }
            if (count($lines) === 1 && mb_strlen($current) > $perLine) {
                break;
            }
        }
        if ($current !== '' && count($lines) < 2) {
            $lines[] = $current;
        }

        return [mb_strimwidth($lines[0] ?? $name, 0, $perLine), isset($lines[1]) ? mb_strimwidth($lines[1], 0, $perLine) : ''];
    }

    /** Bentuk ikon dalam koordinat -100..100 (digambar terpusat). */
    private static function shapes(string $icon): string
    {
        return match ($icon) {
            'shoe' => '<path d="M-70 30 C-70 10 -50 5 -30 5 L-18 5 L-10-25 C-8-32 -2-31 0-24 L6 0 L55 12 C65 14 70 20 68 30 L66 36 L-70 36 Z"/><path d="M-70 22 L66 22"/>',
            'shirt' => '<path d="M-30-50 L-60-35 L-80 0 L-60 8 L-50-5 L-50 55 L50 55 L50-5 L60 8 L80 0 L60-35 L30-50 C25-40 12-35 0-35 C-12-35 -25-40 -30-50 Z"/>',
            'dress' => '<path d="M-20-55 L20-55 L32-20 L60 45 L-60 45 L-32-20 Z"/><path d="M-20-55 C-10-40 10-40 20-55"/>',
            'phone' => '<rect x="-35" y="-60" width="70" height="120" rx="12"/><path d="M-12-45 L12-45"/>',
            'laptop' => '<rect x="-55" y="-45" width="110" height="70" rx="8"/><path d="M-70 40 L70 40"/>',
            'bag' => '<rect x="-55" y="-15" width="110" height="75" rx="10"/><path d="M-30-15 L-30-35 C-30-50 30-50 30-35 L30-15"/>',
            'watch' => '<circle cx="0" cy="0" r="32"/><path d="M0-32 L0-55 M0 32 L0 55 M-14-55 L14-55 M-14 55 L14 55 M0 0 L0-16 M0 0 L12 6"/>',
            'camera' => '<rect x="-60" y="-25" width="120" height="70" rx="10"/><circle cx="0" cy="10" r="20"/><path d="M-30-25 L-20-45 L20-45 L30-25"/>',
            'headphones' => '<path d="M-55 20 C-55-35 55-35 55 20"/><rect x="-65" y="10" width="22" height="40" rx="8"/><rect x="43" y="10" width="22" height="40" rx="8"/>',
            'home' => '<path d="M-60 0 L0-50 L60 0"/><path d="M-40-10 L-40 45 L40 45 L40-10"/>',
            'pot' => '<path d="M-55-10 L55-10 L48 50 L-48 50 Z"/><path d="M-55-10 L-70-10 M55-10 L70-10 M-30-30 L30-30"/>',
            'ball' => '<circle cx="0" cy="0" r="45"/><path d="M0-45 L0-15 M0 45 L0 15 M-45 0 L-15 0 M45 0 L15 0"/>',
            'book' => '<rect x="-45" y="-55" width="90" height="110" rx="6"/><path d="M-25-55 L-25 55"/>',
            'cup' => '<path d="M-40-45 L40-45 L30 55 L-30 55 Z"/><path d="M10-45 L25-70"/>',
            'bottle' => '<rect x="-20" y="-20" width="40" height="80" rx="8"/><path d="M-12-20 L-12-40 L12-40 L12-20 M-5-40 L-5-52 L5-52 L5-40"/>',
            'pill' => '<rect x="-45" y="-20" width="90" height="40" rx="20" transform="rotate(-30)"/><path d="M-12-28 L12 28"/>',
            'snack' => '<rect x="-50" y="-30" width="100" height="80" rx="8"/><path d="M-50-30 L-35-50 L35-50 L50-30 M-25 0 L25 0"/>',
            'toy' => '<rect x="-55" y="5" width="50" height="50" rx="6"/><rect x="5" y="-45" width="50" height="50" rx="6"/><path d="M-30 5 L-30-20 L-5-20"/>',
            'helmet' => '<path d="M-55 25 C-55-25 45-35 60-5 L62 25 L-55 25 Z"/><path d="M20 0 L60 0"/>',
            'lipstick' => '<rect x="-18" y="0" width="36" height="55" rx="6"/><path d="M-12 0 L-12-25 L12-32 L12 0"/>',
            'chair' => '<path d="M-30-55 L-30 10 L30 10 L30 55 M-30 10 L-50 55 M30 10 L50 55"/>',
            'lamp' => '<path d="M-35-30 L35-30 L20 5 L-20 5 Z"/><path d="M0 5 L0 55 M-25 55 L25 55"/>',
            'paw' => '<ellipse cx="0" cy="15" rx="22" ry="18"/><circle cx="-30" cy="-20" r="10"/><circle cx="0" cy="-30" r="10"/><circle cx="30" cy="-20" r="10"/>',
            default => '<rect x="-45" y="-35" width="90" height="70" rx="8"/><path d="M-45-15 L0 5 L45-15 M0 5 L0 35"/>',
        };
    }
}
