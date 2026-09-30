<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Renderer menu CMS storefront.
 *
 * Sumber: SystemSetting key `menu_{key}` (flat atau nested 1 level children).
 * - Cache 1 jam per key.
 * - Escape label/URL, tandai item aktif, dukung dropdown 1 level.
 * - Aman: URL javascript:/data: ditolak dan diganti '#'.
 */
class MenuRenderer
{
    public const CACHE_TTL = 3600;

    /** Alias penamaan lama -> key setting aktual. */
    public const ALIASES = [
        'header' => 'main',
        'footer' => 'footer',
        'main' => 'main',
        'sidebar' => 'sidebar',
    ];

    /**
     * Ambil item menu ternormalisasi (cache 1 jam).
     *
     * Catatan: flag `active` dihitung segar per request (tidak ikut cache)
     * agar tepat di semua halaman.
     *
     * @return list<array{label: string, url: string, target: string, active: bool, children: list<array{label: string, url: string, target: string, active: bool}>}>
     */
    public static function items(string $key): array
    {
        $resolved = self::resolveKey($key);

        $cached = Cache::remember('cms_menu_'.$resolved, self::CACHE_TTL, function () use ($resolved): array {
            $raw = SystemSetting::get('menu_'.$resolved);
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : (is_array($raw) ? $raw : []);

            return self::normalize(is_array($decoded) ? $decoded : []);
        });

        return array_map(function (array $item): array {
            $item['active'] = self::isActive($item['url']);
            $item['children'] = array_map(function (array $child): array {
                $child['active'] = self::isActive($child['url']);

                return $child;
            }, $item['children'] ?? []);

            return $item;
        }, $cached);
    }

    /**
     * Render nested <ul> aman. Kembalikan '' bila menu kosong agar
     * pemanggil bisa memakai fallback tautan statis existing.
     *
     * @param  array{ul_class?: string, li_class?: string, link_class?: string, active_class?: string, dropdown_class?: string, submenu_class?: string}  $options
     */
    public static function render(string $key, array $options = []): string
    {
        $items = self::items($key);

        if ($items === []) {
            return '';
        }

        $ulClass = (string) ($options['ul_class'] ?? 'sf-menu');
        $liClass = (string) ($options['li_class'] ?? 'sf-menu__item');
        $linkClass = (string) ($options['link_class'] ?? 'sf-menu__link');
        $activeClass = (string) ($options['active_class'] ?? 'is-active');
        $dropdownClass = (string) ($options['dropdown_class'] ?? 'has-children');
        $submenuClass = (string) ($options['submenu_class'] ?? 'sf-menu__submenu');

        $html = '<ul class="'.e($ulClass).'">';

        foreach ($items as $item) {
            $hasChildren = $item['children'] !== [];
            $liClasses = trim($liClass.($hasChildren ? ' '.$dropdownClass : ''));
            $html .= '<li class="'.e($liClasses).'">'
                .self::link($item, $linkClass, $activeClass);

            if ($hasChildren) {
                $html .= '<ul class="'.e($submenuClass).'">';
                foreach ($item['children'] as $child) {
                    $html .= '<li class="'.e($liClass).'">'.self::link($child, $linkClass, $activeClass).'</li>';
                }
                $html .= '</ul>';
            }

            $html .= '</li>';
        }

        return $html.'</ul>';
    }

    public static function flush(string $key): void
    {
        Cache::forget('cms_menu_'.self::resolveKey($key));
    }

    public static function flushAll(): void
    {
        foreach (array_unique(array_values(self::ALIASES)) as $key) {
            Cache::forget('cms_menu_'.$key);
        }
    }

    public static function resolveKey(string $key): string
    {
        return self::ALIASES[$key] ?? $key;
    }

    /**
     * Normalisasi flat/nested -> struktur 1 level (tanpa flag aktif;
     * flag aktif dihitung segar di items() agar tidak ikut tercache).
     *
     * @param  array  $decoded
     * @return list<array{label: string, url: string, target: string, children: array}>
     */
    public static function normalize(array $decoded): array
    {
        $out = [];

        foreach (array_values($decoded) as $entry) {
            if (! is_array($entry)) {
                $entry = ['label' => (string) $entry, 'url' => ''];
            }

            $label = trim((string) ($entry['label'] ?? ''));
            $url = trim((string) ($entry['url'] ?? ''));

            if ($label === '' || $url === '' || ! self::safeUrl($url)) {
                continue;
            }

            $children = [];
            if (isset($entry['children']) && is_array($entry['children'])) {
                foreach (array_values($entry['children']) as $childEntry) {
                    if (! is_array($childEntry)) {
                        continue;
                    }
                    $childLabel = trim((string) ($childEntry['label'] ?? ''));
                    $childUrl = trim((string) ($childEntry['url'] ?? ''));
                    if ($childLabel === '' || $childUrl === '' || ! self::safeUrl($childUrl)) {
                        continue;
                    }
                    $children[] = [
                        'label' => mb_substr($childLabel, 0, 80),
                        'url' => $childUrl,
                        'target' => ($childEntry['target'] ?? '_self') === '_blank' ? '_blank' : '_self',
                    ];
                }
            }

            $out[] = [
                'label' => mb_substr($label, 0, 80),
                'url' => $url,
                'target' => ($entry['target'] ?? '_self') === '_blank' ? '_blank' : '_self',
                'children' => $children,
            ];
        }

        return $out;
    }

    /** URL valid: relatif (/, #, path) atau http(s) absolut. Tolak javascript:/data:/vbscript:. */
    public static function safeUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '' || preg_match('/^\s*(javascript|data|vbscript|file)\s*:/i', $url)) {
            return false;
        }

        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }

        if (preg_match('#^https?://#i', $url)) {
            return filter_var($url, FILTER_VALIDATE_URL) !== false;
        }

        // Path relatif tanpa skema (tentang-kami, produk/a).
        return (bool) preg_match('#^[A-Za-z0-9_\-./?=&%+#~:]+$#', $url);
    }

    public static function isActive(string $url): bool
    {
        try {
            $path = parse_url($url, PHP_URL_PATH);
            if (! is_string($path) || $path === '' || $path === '/') {
                return request()->is('/');
            }

            return request()->is(ltrim($path, '/').'*') || request()->fullUrlIs($url);
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param array{label: string, url: string, target: string, active: bool} $item */
    private static function link(array $item, string $linkClass, string $activeClass): string
    {
        $classes = trim($linkClass.($item['active'] ? ' '.$activeClass : ''));
        $target = $item['target'] === '_blank'
            ? ' target="_blank" rel="noopener noreferrer"'
            : '';

        return '<a class="'.e($classes).'" href="'.e($item['url']).'"'
            .($item['active'] ? ' aria-current="page"' : '')
            .$target.'>'.e($item['label']).'</a>';
    }
}
