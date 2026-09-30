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
 * Tipe: text, hero, products, gallery, faq, cta.
 *
 * Keamanan: semua string di-escape kecuali HTML teks yang lewat
 * HtmlSanitizer (allowlist tag admin). Produk hanya yang tayang
 * (status=approved + published=1).
 */
class PageBlockRenderer
{
    public const TYPES = ['text', 'hero', 'products', 'gallery', 'faq', 'cta'];

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
                'gallery' => $this->renderGallery($block),
                'faq' => $this->renderFaq($block),
                'cta' => $this->renderCta($block),
                default => '',
            };
        }

        return $html;
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
