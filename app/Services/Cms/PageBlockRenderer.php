<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Models\Product;
use App\Models\SystemSetting;
use App\Services\HtmlSanitizer;
use App\Support\Currency;
use Illuminate\Support\Str;

/**
 * Page builder: normalisasi JSON blok + render storefront yang aman.
 *
 * Penyimpanan: SystemSetting key `page_blocks_{key}` berisi JSON array blok.
 * Tipe: text, hero, products, product-carousel, gallery, faq,
 * faq-accordion, cta, testimonials, countdown, newsletter, map, pricing.
 *
 * Keamanan: semua string di-escape kecuali HTML teks yang lewat
 * HtmlSanitizer (allowlist tag admin). Produk hanya yang tayang
 * (status=approved + published=1).
 */
class PageBlockRenderer
{
    public const TYPES = [
        'text', 'hero', 'products', 'product-carousel', 'gallery', 'faq',
        'faq-accordion', 'cta', 'testimonials', 'countdown', 'newsletter', 'map', 'pricing',
    ];

    /** Alias tipe lama -> kanonis (faq == faq-accordion). */
    public const TYPE_ALIASES = ['faq-accordion' => 'faq'];

    public const MAX_BLOCKS = 30;

    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    /**
     * Ambil blok tersimpan untuk satu halaman.
     *
     * @return list<array<string, mixed>>
     */
    public static function blocksFor(string $key): array
    {
        try {
            $raw = SystemSetting::get('page_blocks_'.$key, '');
            if (! is_string($raw) || trim($raw) === '') {
                return [];
            }
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? self::normalize($decoded) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Validasi + normalisasi array blok mentah menjadi bentuk kanonis.
     *
     * @param  mixed  $blocks
     * @return list<array<string, mixed>>
     */
    public static function normalize(mixed $blocks): array
    {
        if (is_string($blocks)) {
            $decoded = json_decode($blocks, true);
            $blocks = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($blocks)) {
            return [];
        }

        $out = [];
        foreach (array_values($blocks) as $block) {
            if (! is_array($block)) {
                continue;
            }
            $type = (string) ($block['type'] ?? '');
            // Kanonik: faq-accordion disimpan sebagai faq agar renderer lama tetap jalan.
            if (($aliased = self::TYPE_ALIASES[$type] ?? null) !== null) {
                $block['type'] = $aliased;
                $type = $aliased;
            }
            if (! in_array($type, self::TYPES, true)) {
                continue;
            }
            $normalized = self::normalizeOne($type, $block);
            if ($normalized !== null) {
                $out[] = $normalized;
            }
            if (count($out) >= self::MAX_BLOCKS) {
                break;
            }
        }

        return $out;
    }

    /** @param  array<string, mixed>  $block */
    private static function normalizeOne(string $type, array $block): ?array
    {
        return match ($type) {
            'text' => [
                'type' => 'text',
                // HTML disimpan mentah, disanitasi saat render.
                'html' => mb_substr((string) ($block['html'] ?? ''), 0, 50000),
            ],
            'hero' => [
                'type' => 'hero',
                'title' => mb_substr(trim((string) ($block['title'] ?? '')), 0, 160),
                'subtitle' => mb_substr(trim((string) ($block['subtitle'] ?? '')), 0, 500),
                'image' => mb_substr(trim((string) ($block['image'] ?? '')), 0, 1000),
                'button_label' => mb_substr(trim((string) ($block['button_label'] ?? '')), 0, 60),
                'button_url' => mb_substr(trim((string) ($block['button_url'] ?? '')), 0, 1000),
            ],
            'products' => [
                'type' => 'products',
                'title' => mb_substr(trim((string) ($block['title'] ?? '')), 0, 160),
                'category_id' => ($block['category_id'] ?? null) !== null && ($block['category_id'] ?? '') !== ''
                    ? max(0, (int) $block['category_id']) : null,
                'product_ids' => array_values(array_unique(array_map(
                    'intval',
                    array_filter((array) ($block['product_ids'] ?? []), fn ($v): bool => (int) $v > 0)
                ))),
                'limit' => min(12, max(1, (int) ($block['limit'] ?? 4))),
            ],
            'gallery' => [
                'type' => 'gallery',
                'title' => mb_substr(trim((string) ($block['title'] ?? '')), 0, 160),
                'images' => array_values(array_filter(array_map(
                    fn ($v): string => mb_substr(trim((string) (is_array($v) ? ($v['src'] ?? $v['url'] ?? '') : $v)), 0, 1000),
                    array_slice((array) ($block['images'] ?? []), 0, 12)
                ), fn (string $v): bool => $v !== '')),
            ],
            'faq' => [
                'type' => 'faq',
                'title' => mb_substr(trim((string) ($block['title'] ?? '')), 0, 160),
                'items' => array_values(array_filter(array_map(
                    fn ($item): ?array => is_array($item) && trim((string) ($item['q'] ?? '')) !== '' ? [
                        'q' => mb_substr(trim((string) $item['q']), 0, 300),
                        'a' => mb_substr(trim((string) ($item['a'] ?? '')), 0, 2000),
                    ] : null,
                    array_slice((array) ($block['items'] ?? []), 0, 20)
                ))),
            ],
            'cta' => [
                'type' => 'cta',
                'title' => mb_substr(trim((string) ($block['title'] ?? '')), 0, 160),
                'subtitle' => mb_substr(trim((string) ($block['subtitle'] ?? '')), 0, 500),
                'button_label' => mb_substr(trim((string) ($block['button_label'] ?? '')), 0, 60),
                'button_url' => mb_substr(trim((string) ($block['button_url'] ?? '')), 0, 1000),
            ],
            'product-carousel' => [
                'type' => 'product-carousel',
                'title' => mb_substr(trim((string) ($block['title'] ?? '')), 0, 160),
                'category_id' => ($block['category_id'] ?? null) !== null && ($block['category_id'] ?? '') !== ''
                    ? max(0, (int) $block['category_id']) : null,
                'product_ids' => array_values(array_unique(array_map(
                    'intval',
                    array_filter((array) ($block['product_ids'] ?? []), fn ($v): bool => (int) $v > 0)
                ))),
                'limit' => min(12, max(1, (int) ($block['limit'] ?? 8))),
                'autoplay' => ! empty($block['autoplay']),
            ],
            'testimonials' => [
                'type' => 'testimonials',
                'title' => mb_substr(trim((string) ($block['title'] ?? '')), 0, 160),
                'items' => array_values(array_filter(array_map(
                    fn ($item): ?array => is_array($item) && trim((string) ($item['text'] ?? $item['quote'] ?? '')) !== '' ? [
                        'name' => mb_substr(trim((string) ($item['name'] ?? 'Pelanggan')), 0, 120),
                        'text' => mb_substr(trim((string) ($item['text'] ?? $item['quote'] ?? '')), 0, 1000),
                        'rating' => min(5, max(1, (int) ($item['rating'] ?? 5))),
                        'avatar' => mb_substr(trim((string) ($item['avatar'] ?? '')), 0, 1000),
                    ] : null,
                    array_slice((array) ($block['items'] ?? []), 0, 12)
                ))),
            ],
            'countdown' => [
                'type' => 'countdown',
                'title' => mb_substr(trim((string) ($block['title'] ?? '')), 0, 160),
                'subtitle' => mb_substr(trim((string) ($block['subtitle'] ?? '')), 0, 500),
                'ends_at' => mb_substr(trim((string) ($block['ends_at'] ?? '')), 0, 40),
                'button_label' => mb_substr(trim((string) ($block['button_label'] ?? '')), 0, 60),
                'button_url' => mb_substr(trim((string) ($block['button_url'] ?? '')), 0, 1000),
            ],
            'newsletter' => [
                'type' => 'newsletter',
                'title' => mb_substr(trim((string) ($block['title'] ?? 'Dapatkan promo terbaru')), 0, 160),
                'subtitle' => mb_substr(trim((string) ($block['subtitle'] ?? '')), 0, 500),
                'placeholder' => mb_substr(trim((string) ($block['placeholder'] ?? 'Alamat email')), 0, 80),
                'button_label' => mb_substr(trim((string) ($block['button_label'] ?? 'Berlangganan')), 0, 60),
            ],
            'map' => [
                'type' => 'map',
                'title' => mb_substr(trim((string) ($block['title'] ?? '')), 0, 160),
                'address' => mb_substr(trim((string) ($block['address'] ?? '')), 0, 500),
                'embed_url' => mb_substr(trim((string) ($block['embed_url'] ?? $block['image'] ?? '')), 0, 2000),
                'stores' => array_values(array_filter(array_map(
                    fn ($s): ?array => is_array($s) && trim((string) ($s['name'] ?? '')) !== '' ? [
                        'name' => mb_substr(trim((string) $s['name']), 0, 160),
                        'address' => mb_substr(trim((string) ($s['address'] ?? '')), 0, 500),
                        'phone' => mb_substr(trim((string) ($s['phone'] ?? '')), 0, 40),
                    ] : null,
                    array_slice((array) ($block['stores'] ?? []), 0, 20)
                ))),
            ],
            'pricing' => [
                'type' => 'pricing',
                'title' => mb_substr(trim((string) ($block['title'] ?? '')), 0, 160),
                'plans' => array_values(array_filter(array_map(
                    fn ($p): ?array => is_array($p) && trim((string) ($p['name'] ?? '')) !== '' ? [
                        'name' => mb_substr(trim((string) $p['name']), 0, 120),
                        'price' => mb_substr(trim((string) ($p['price'] ?? '')), 0, 60),
                        'cta_label' => mb_substr(trim((string) ($p['cta_label'] ?? $p['button_label'] ?? '')), 0, 60),
                        'cta_url' => mb_substr(trim((string) ($p['cta_url'] ?? $p['button_url'] ?? '')), 0, 1000),
                        'featured' => ! empty($p['featured']),
                        'features' => array_values(array_filter(array_map(
                            fn ($f): string => mb_substr(trim((string) $f), 0, 200),
                            array_slice((array) ($p['features'] ?? []), 0, 15)
                        ), fn (string $f): bool => $f !== '')),
                    ] : null,
                    array_slice((array) ($block['plans'] ?? []), 0, 6)
                ))),
            ],
            default => null,
        };
    }

    /**
     * Render blok menjadi HTML aman. Terima key halaman atau array blok.
     *
     * @param  string|array  $keyOrBlocks
     */
    public function render(string|array $keyOrBlocks): string
    {
        $blocks = is_string($keyOrBlocks) ? self::blocksFor($keyOrBlocks) : self::normalize($keyOrBlocks);
        if ($blocks === []) {
            return '';
        }

        $html = '';
        foreach ($blocks as $block) {
            $html .= match ((string) ($block['type'] ?? '')) {
                'text' => $this->renderText($block),
                'hero' => $this->renderHero($block),
                'products' => $this->renderProducts($block),
                'product-carousel' => $this->renderProductCarousel($block),
                'gallery' => $this->renderGallery($block),
                'faq' => $this->renderFaq($block),
                'cta' => $this->renderCta($block),
                'testimonials' => $this->renderTestimonials($block),
                'countdown' => $this->renderCountdown($block),
                'newsletter' => $this->renderNewsletter($block),
                'map' => $this->renderMap($block),
                'pricing' => $this->renderPricing($block),
                default => '',
            };
        }

        return $html;
    }

    /**
     * AEO: JSON-LD FAQPage untuk blok faq. Dipakai storefront di blok relevan.
     *
     * @param  array<string, mixed>  $block
     */
    public static function faqSchema(array $block): string
    {
        $items = array_values(array_filter(
            (array) ($block['items'] ?? []),
            fn ($item): bool => is_array($item) && trim((string) ($item['q'] ?? '')) !== ''
        ));
        if ($items === []) {
            return '';
        }
        $entities = [];
        foreach (array_slice($items, 0, 20) as $item) {
            $entities[] = [
                '@type' => 'Question',
                'name' => mb_substr(trim((string) $item['q']), 0, 300),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => mb_substr(trim((string) ($item['a'] ?? '')), 0, 2000),
                ],
            ];
        }

        return '<script type="application/ld+json">'
            .json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $entities], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            .'</script>';
    }

    /**
     * AEO: JSON-LD HowTo untuk blok pricing (langkah memilih paket).
     * Dibatasi: hanya bila ada >= 2 paket agar tidak menyesatkan crawler.
     */
    public static function howToSchema(array $block): string
    {
        $plans = array_values(array_filter(
            (array) ($block['plans'] ?? []),
            fn ($p): bool => is_array($p) && trim((string) ($p['name'] ?? '')) !== ''
        ));
        if (count($plans) < 2) {
            return '';
        }
        $steps = [];
        foreach (array_slice($plans, 0, 6) as $i => $plan) {
            $steps[] = [
                '@type' => 'HowToStep',
                'position' => $i + 1,
                'name' => mb_substr(trim((string) $plan['name']), 0, 120),
                'text' => mb_substr(trim((string) ($plan['price'] ?? '')) !== ''
                    ? (string) $plan['name'].' — '.(string) $plan['price']
                    : (string) $plan['name'], 0, 300),
            ];
        }

        return '<script type="application/ld+json">'
            .json_encode(['@context' => 'https://schema.org', '@type' => 'HowTo',
                'name' => mb_substr(trim((string) ($block['title'] ?? 'Panduan memilih paket')), 0, 160),
                'step' => $steps], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            .'</script>';
    }

    /** Kumpulkan semua JSON-LD AEO untuk satu set blok (faq + pricing). */
    public static function aeoSchemas(string|array $keyOrBlocks): string
    {
        $blocks = is_string($keyOrBlocks) ? self::blocksFor($keyOrBlocks) : self::normalize($keyOrBlocks);
        $out = '';
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') === 'faq') {
                $out .= self::faqSchema($block);
            } elseif (($block['type'] ?? '') === 'pricing') {
                $out .= self::howToSchema($block);
            }
        }

        return $out;
    }

    /** @param  array<string, mixed>  $block */
    private function renderText(array $block): string
    {
        $clean = (string) ($this->sanitizer->clean((string) ($block['html'] ?? '')) ?? '');
        if (trim(strip_tags($clean)) === '' && trim($clean) === '') {
            return '';
        }

        return '<div class="sf-prose sf-pageblock sf-pageblock--text">'.$clean.'</div>';
    }

    /** @param  array<string, mixed>  $block */
    private function renderHero(array $block): string
    {
        $title = (string) ($block['title'] ?? '');
        $subtitle = (string) ($block['subtitle'] ?? '');
        $image = $this->safeUrl((string) ($block['image'] ?? ''));
        $label = (string) ($block['button_label'] ?? '');
        $url = $this->safeUrl((string) ($block['button_url'] ?? ''));
        if ($title === '' && $subtitle === '' && $image === null) {
            return '';
        }

        $html = '<section class="sf-pageblock sf-pageblock--hero"><div class="sf-hero">';
        if ($image !== null) {
            $html .= '<img class="sf-hero__img" src="'.e($image).'" alt="'.e($title !== '' ? $title : 'Hero').'" loading="lazy">';
        }
        $html .= '<div class="sf-hero__body">';
        if ($title !== '') {
            $html .= '<h2 class="sf-hero__title">'.e($title).'</h2>';
        }
        if ($subtitle !== '') {
            $html .= '<p class="sf-hero__sub">'.e($subtitle).'</p>';
        }
        if ($label !== '' && $url !== null) {
            $html .= '<a class="sf-btn sf-btn--primary" href="'.e($url).'">'.e($label).'</a>';
        }
        $html .= '</div></div></section>';

        return $html;
    }

    /** @param  array<string, mixed>  $block */
    private function renderProducts(array $block): string
    {
        $products = $this->resolveProducts($block);
        if ($products === []) {
            return '';
        }

        $html = '<section class="sf-pageblock sf-pageblock--products">';
        if (trim((string) ($block['title'] ?? '')) !== '') {
            $html .= '<h2 class="sf-pageblock__title">'.e((string) $block['title']).'</h2>';
        }
        $html .= '<div class="sf-row sf-row--wrap" style="gap:12px">';
        foreach ($products as $product) {
            $url = (string) ($product['url'] ?? '#');
            $html .= '<article class="sf-card" style="flex:1 1 200px;max-width:260px;padding:12px">'
                .'<a href="'.e($url).'" style="font-weight:600">'.e((string) $product['name']).'</a>'
                .'<div class="text-secondary small">'.e((string) ($product['price_formatted'] ?? '')).'</div>'
                .'</article>';
        }
        $html .= '</div></section>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return list<array<string, string>>
     */
    private function resolveProducts(array $block): array
    {
        try {
            $limit = min(12, max(1, (int) ($block['limit'] ?? 4)));
            $query = Product::query()->where('status', 'approved')->where('published', true);

            $ids = array_values(array_filter(array_map('intval', (array) ($block['product_ids'] ?? []))));
            if ($ids !== []) {
                $query->whereIn('id', array_slice($ids, 0, 12));
            } elseif (! empty($block['category_id'])) {
                $query->where('category_id', (int) $block['category_id']);
            }
            $rows = $query->orderByDesc('id')->limit($limit)->get(['id', 'name', 'slug', 'price']);

            return $rows->map(fn (Product $p): array => [
                'name' => (string) $p->name,
                'price_formatted' => Currency::format((float) $p->price),
                'url' => $this->productUrl($p),
            ])->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function productUrl(Product $product): string
    {
        try {
            return (string) $product->url;
        } catch (\Throwable) {
            return '/products/'.ltrim((string) $product->slug, '/');
        }
    }

    /** @param  array<string, mixed>  $block */
    private function renderGallery(array $block): string
    {
        $images = [];
        foreach ((array) ($block['images'] ?? []) as $src) {
            $safe = $this->safeUrl((string) $src);
            if ($safe !== null) {
                $images[] = $safe;
            }
        }
        if ($images === []) {
            return '';
        }

        $html = '<section class="sf-pageblock sf-pageblock--gallery">';
        if (trim((string) ($block['title'] ?? '')) !== '') {
            $html .= '<h2 class="sf-pageblock__title">'.e((string) $block['title']).'</h2>';
        }
        $html .= '<div class="sf-row sf-row--wrap" style="gap:10px">';
        foreach ($images as $i => $src) {
            $html .= '<img src="'.e($src).'" alt="'.e('Galeri '.($i + 1)).'" loading="lazy" style="width:160px;height:120px;object-fit:cover;border-radius:8px">';
        }
        $html .= '</div></section>';

        return $html;
    }

    /** @param  array<string, mixed>  $block */
    private function renderFaq(array $block): string
    {
        $items = array_values(array_filter(
            (array) ($block['items'] ?? []),
            fn ($item): bool => is_array($item) && trim((string) ($item['q'] ?? '')) !== ''
        ));
        if ($items === []) {
            return '';
        }

        $html = '<section class="sf-pageblock sf-pageblock--faq">';
        if (trim((string) ($block['title'] ?? '')) !== '') {
            $html .= '<h2 class="sf-pageblock__title">'.e((string) $block['title']).'</h2>';
        }
        foreach (array_slice($items, 0, 20) as $item) {
            $html .= '<details class="sf-faq"><summary class="sf-faq__q">'.e((string) $item['q']).'</summary>'
                .'<div class="sf-faq__a sf-prose">'.e((string) ($item['a'] ?? '')).'</div></details>';
        }
        $html .= '</section>';

        return $html;
    }

    /** @param  array<string, mixed>  $block */
    private function renderCta(array $block): string
    {
        $title = (string) ($block['title'] ?? '');
        $subtitle = (string) ($block['subtitle'] ?? '');
        $label = (string) ($block['button_label'] ?? '');
        $url = $this->safeUrl((string) ($block['button_url'] ?? ''));
        if ($title === '' && $label === '') {
            return '';
        }

        $html = '<section class="sf-pageblock sf-pageblock--cta"><div class="sf-cta" style="padding:24px;border-radius:12px;background:#f1f5f9;text-align:center">';
        if ($title !== '') {
            $html .= '<h2 class="sf-cta__title">'.e($title).'</h2>';
        }
        if ($subtitle !== '') {
            $html .= '<p class="text-secondary">'.e($subtitle).'</p>';
        }
        if ($label !== '' && $url !== null) {
            $html .= '<a class="sf-btn sf-btn--primary" href="'.e($url).'">'.e($label).'</a>';
        }
        $html .= '</div></section>';

        return $html;
    }

    /** @param  array<string, mixed>  $block */
    private function renderProductCarousel(array $block): string
    {
        $products = $this->resolveProducts($block);
        if ($products === []) {
            return '';
        }
        $autoplay = ! empty($block['autoplay']) ? ' data-autoplay="1"' : '';
        $html = '<section class="sf-pageblock sf-pageblock--carousel"'.$autoplay.'>';
        if (trim((string) ($block['title'] ?? '')) !== '') {
            $html .= '<h2 class="sf-pageblock__title">'.e((string) $block['title']).'</h2>';
        }
        $html .= '<div class="sf-carousel" style="display:flex;gap:12px;overflow-x:auto;scroll-snap-type:x mandatory;padding-bottom:8px">';
        foreach ($products as $product) {
            $url = (string) ($product['url'] ?? '#');
            $html .= '<article class="sf-card" style="flex:0 0 220px;scroll-snap-align:start;padding:12px">'
                .'<a href="'.e($url).'" style="font-weight:600">'.e((string) $product['name']).'</a>'
                .'<div class="text-secondary small">'.e((string) ($product['price_formatted'] ?? '')).'</div>'
                .'</article>';
        }

        return $html.'</div></section>';
    }

    /** @param  array<string, mixed>  $block */
    private function renderTestimonials(array $block): string
    {
        $items = array_values(array_filter(
            (array) ($block['items'] ?? []),
            fn ($i): bool => is_array($i) && trim((string) ($i['text'] ?? '')) !== ''
        ));
        if ($items === []) {
            return '';
        }
        $html = '<section class="sf-pageblock sf-pageblock--testimonials">';
        if (trim((string) ($block['title'] ?? '')) !== '') {
            $html .= '<h2 class="sf-pageblock__title">'.e((string) $block['title']).'</h2>';
        }
        $html .= '<div class="sf-row sf-row--wrap" style="gap:12px">';
        foreach (array_slice($items, 0, 12) as $item) {
            $avatar = $this->safeUrl((string) ($item['avatar'] ?? ''));
            $html .= '<figure class="sf-card" style="flex:1 1 220px;padding:14px;margin:0">';
            if ($avatar !== null) {
                $html .= '<img src="'.e($avatar).'" alt="'.e((string) ($item['name'] ?? '')).'" loading="lazy" style="width:40px;height:40px;border-radius:50%;object-fit:cover">';
            }
            $html .= '<blockquote style="margin:8px 0">'.e((string) $item['text']).'</blockquote>'
                .'<figcaption class="small text-secondary">'.e((string) ($item['name'] ?? 'Pelanggan'))
                .' · '.str_repeat('★', min(5, max(1, (int) ($item['rating'] ?? 5)))).'</figcaption>'
                .'</figure>';
        }

        return $html.'</div></section>';
    }

    /** @param  array<string, mixed>  $block */
    private function renderCountdown(array $block): string
    {
        $endsAt = trim((string) ($block['ends_at'] ?? ''));
        $ts = $endsAt !== '' ? strtotime($endsAt) : false;
        if ($ts === false) {
            return '';
        }
        $iso = date('c', (int) $ts);
        $label = (string) ($block['button_label'] ?? '');
        $url = $this->safeUrl((string) ($block['button_url'] ?? ''));
        $html = '<section class="sf-pageblock sf-pageblock--countdown"><div class="sf-cta" style="padding:24px;border-radius:12px;background:#0f172a;color:#fff;text-align:center" data-countdown="'.e($iso).'">';
        if (trim((string) ($block['title'] ?? '')) !== '') {
            $html .= '<h2 class="sf-cta__title" style="color:#fff">'.e((string) $block['title']).'</h2>';
        }
        if (trim((string) ($block['subtitle'] ?? '')) !== '') {
            $html .= '<p style="opacity:.8">'.e((string) $block['subtitle']).'</p>';
        }
        $html .= '<div class="sf-countdown" style="font-variant-numeric:tabular-nums;font-size:1.25rem" data-countdown-label>Berakhir '.e($iso).'</div>';
        if ($label !== '' && $url !== null) {
            $html .= '<p style="margin-top:12px"><a class="sf-btn sf-btn--primary" href="'.e($url).'">'.e($label).'</a></p>';
        }

        return $html.'</div></section>';
    }

    /** @param  array<string, mixed>  $block */
    private function renderNewsletter(array $block): string
    {
        $html = '<section class="sf-pageblock sf-pageblock--newsletter"><div style="padding:24px;border-radius:12px;background:#f8fafc;text-align:center">';
        $html .= '<h2 class="sf-pageblock__title">'.e((string) ($block['title'] ?? 'Dapatkan promo terbaru')).'</h2>';
        if (trim((string) ($block['subtitle'] ?? '')) !== '') {
            $html .= '<p class="text-secondary">'.e((string) $block['subtitle']).'</p>';
        }
        // Aksi diserahkan ke form builder NewsLetter bila integrator wiring;
        // fallback POST bawaan ke endpoint cms-forms bila ada.
        $html .= '<form method="POST" action="/cms-forms/newsletter" data-newsletter-form style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;margin-top:12px">'
            .'<label class="visually-hidden" for="nl-'.e(mb_substr(md5((string) ($block['title'] ?? 'nl')), 0, 8)).'">Email</label>'
            .'<input type="email" required name="email" maxlength="160" placeholder="'.e((string) ($block['placeholder'] ?? 'Alamat email')).'" style="padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;min-width:min(320px,80vw)">'
            .'<button type="submit" class="sf-btn sf-btn--primary">'.e((string) ($block['button_label'] ?? 'Berlangganan')).'</button>'
            .'</form>';

        return $html.'</div></section>';
    }

    /** @param  array<string, mixed>  $block */
    private function renderMap(array $block): string
    {
        $stores = array_values(array_filter(
            (array) ($block['stores'] ?? []),
            fn ($s): bool => is_array($s) && trim((string) ($s['name'] ?? '')) !== ''
        ));
        $embed = $this->safeUrl((string) ($block['embed_url'] ?? ''));
        // Hanya izinkan embed https (google/maps) agar iframe aman.
        if ($embed !== null && ! str_starts_with($embed, 'https://')) {
            $embed = null;
        }
        if ($embed === null && $stores === [] && trim((string) ($block['address'] ?? '')) === '' && trim((string) ($block['title'] ?? '')) === '') {
            return '';
        }
        $html = '<section class="sf-pageblock sf-pageblock--map">';
        if (trim((string) ($block['title'] ?? '')) !== '') {
            $html .= '<h2 class="sf-pageblock__title">'.e((string) $block['title']).'</h2>';
        }
        if (trim((string) ($block['address'] ?? '')) !== '') {
            $html .= '<p class="text-secondary">'.e((string) $block['address']).'</p>';
        }
        if ($embed !== null) {
            $html .= '<iframe src="'.e($embed).'" loading="lazy" style="width:100%;height:320px;border:0;border-radius:12px" referrerpolicy="no-referrer-when-downgrade" title="'.e(trim((string) ($block['title'] ?? 'Peta')) !== '' ? (string) $block['title'] : 'Peta').'"></iframe>';
        }
        foreach (array_slice($stores, 0, 20) as $store) {
            $html .= '<div class="sf-card" style="padding:12px;margin-top:8px"><strong>'.e((string) $store['name']).'</strong>'
                .(trim((string) ($store['address'] ?? '')) !== '' ? '<div class="small text-secondary">'.e((string) $store['address']).'</div>' : '')
                .(trim((string) ($store['phone'] ?? '')) !== '' ? '<div class="small">'.e((string) $store['phone']).'</div>' : '')
                .'</div>';
        }

        return $html.'</section>';
    }

    /** @param  array<string, mixed>  $block */
    private function renderPricing(array $block): string
    {
        $plans = array_values(array_filter(
            (array) ($block['plans'] ?? []),
            fn ($p): bool => is_array($p) && trim((string) ($p['name'] ?? '')) !== ''
        ));
        if ($plans === []) {
            return '';
        }
        $html = '<section class="sf-pageblock sf-pageblock--pricing">';
        if (trim((string) ($block['title'] ?? '')) !== '') {
            $html .= '<h2 class="sf-pageblock__title" style="text-align:center">'.e((string) $block['title']).'</h2>';
        }
        $html .= '<div class="sf-row sf-row--wrap" style="gap:12px;justify-content:center">';
        foreach (array_slice($plans, 0, 6) as $plan) {
            $ctaLabel = (string) ($plan['cta_label'] ?? '');
            $ctaUrl = $this->safeUrl((string) ($plan['cta_url'] ?? ''));
            $featured = ! empty($plan['featured']);
            $html .= '<div class="sf-card" style="flex:1 1 220px;max-width:300px;padding:18px'.($featured ? ';border:2px solid #4f46e5' : '').'">'
                .($featured ? '<span class="badge bg-primary mb-2">Populer</span>' : '')
                .'<h3 style="margin:0 0 4px">'.e((string) $plan['name']).'</h3>'
                .'<div style="font-size:1.4rem;font-weight:700">'.e((string) ($plan['price'] ?? '')).'</div>';
            foreach (array_slice((array) ($plan['features'] ?? []), 0, 15) as $feature) {
                $html .= '<div class="small" style="padding:4px 0;border-top:1px solid #f1f5f9">✓ '.e((string) $feature).'</div>';
            }
            if ($ctaLabel !== '' && $ctaUrl !== null) {
                $html .= '<p style="margin:12px 0 0"><a class="sf-btn '.($featured ? 'sf-btn--primary' : '').'" href="'.e($ctaUrl).'">'.e($ctaLabel).'</a></p>';
            }
            $html .= '</div>';
        }

        return $html.'</div></section>';
    }

    private function safeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        // Tolak skema berbahaya (javascript:, data:, vbscript:, file:).
        if (preg_match('~^\s*(javascript|data|vbscript|file)\s*:~i', $url)) {
            return null;
        }
        if (preg_match('~^(https?://|/|#)~i', $url)) {
            return Str::limit($url, 1000);
        }

        return null;
    }
}
