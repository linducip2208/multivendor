<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\Brand;
use App\Models\Category;
use App\Models\PseoPage;
use App\Models\Product;
use App\Models\Shop;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\File;

/**
 * Sitemap health.
 *
 * The report describes what a sitemap *should* contain and whether the
 * published files on disk are still current, without ever writing them from a
 * GET request.
 */
final class SitemapStatusService
{
    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $counts = $this->counts();
        $total = array_sum(array_column($counts, 'count'));

        $files = $this->sitemapFiles();
        $indexPath = public_path('sitemap.xml');

        return [
            'enabled' => (string) SystemSetting::get('sitemap_enabled', '1') === '1',
            'total_urls' => $total,
            'counts' => $counts,
            'index_exists' => File::exists($indexPath),
            'index_bytes' => File::exists($indexPath) ? (int) File::size($indexPath) : 0,
            'index_modified' => File::exists($indexPath) ? (string) date('Y-m-d H:i:s', (int) File::lastModified($indexPath)) : null,
            'files' => $files,
            'published_at' => (string) (SystemSetting::get('sitemap_published_at') ?? ''),
            'stale' => $files === [] || $this->isStale(),
        ];
    }

    /**
     * @return list<array{name: string, bytes: int, modified: string|null}>
     */
    public function list(): array
    {
        return $this->sitemapFiles();
    }

    /**
     * @return list<array{name: string, bytes: int, modified: string|null}>
     */
    private function sitemapFiles(): array
    {
        $out = [];

        foreach (File::glob(public_path('sitemap*.xml')) as $path) {
            $out[] = [
                'name' => basename((string) $path),
                'bytes' => (int) File::size((string) $path),
                'modified' => (string) date('Y-m-d H:i:s', (int) File::lastModified((string) $path)),
            ];
        }

        usort($out, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $out;
    }

    /**
     * @return array<string, int|array{count: int}>
     */
    private function counts(): array
    {
        return [
            'products' => ['count' => (int) Product::query()->where('status', 'approved')->where('published', true)->count()],
            'categories' => ['count' => (int) Category::query()->where('status', true)->count()],
            'brands' => ['count' => (int) Brand::query()->where('status', true)->count()],
            'shops' => ['count' => (int) Shop::query()->where('status', 'active')->count()],
            'pseo' => ['count' => (int) PseoPage::query()->where('state', 'published')->where('indexability', 'index')->count()],
            'pages' => ['count' => $this->countStaticPages()],
        ];
    }

    private function countStaticPages(): int
    {
        $keys = ['about', 'terms', 'privacy', 'return', 'faq'];

        return count(array_filter(
            $keys,
            fn (string $key): bool => (string) (SystemSetting::get('page_'.$key) ?? '') !== '',
        ));
    }

    private function isStale(): bool
    {
        $indexPath = public_path('sitemap.xml');

        if (! File::exists($indexPath)) {
            return true;
        }

        $published = (int) File::lastModified($indexPath);

        $newest = (int) Product::query()
            ->where('status', 'approved')
            ->max('updated_at');

        if ($newest > $published) {
            return true;
        }

        $newestPage = (int) PseoPage::query()
            ->where('state', 'published')
            ->max('published_at');

        return $newestPage > $published;
    }

    /* ── ADITIF deepening: sitemap per locale + hreflang audit ── */

    /** @return list<string> locale aktif untuk sitemap (fallback id/en). */
    public function locales(): array
    {
        try {
            $codes = app(\App\Services\Localization\LanguageService::class)->activeCodes();
            $codes = array_values(array_filter(array_map(fn ($c): string => strtolower(trim((string) $c)), $codes)));

            return $codes !== [] ? array_slice($codes, 0, 10) : ['id', 'en'];
        } catch (\Throwable) {
            return ['id', 'en'];
        }
    }

    /**
     * URL sitemap per locale (aditif; struktur file tetap, hanya loc
     * berawalan /{locale}/ bila locale non-default).
     *
     * @return array<string, list<string>> locale => daftar file sitemap
     */
    public function localizedIndexes(): array
    {
        $default = $this->defaultLocale();
        $out = [];
        $files = ['sitemap-main.xml', 'sitemap-products.xml', 'sitemap-categories.xml', 'sitemap-blog.xml'];
        foreach ($this->locales() as $locale) {
            $prefix = $locale === $default ? '' : $locale.'/';
            $out[$locale] = array_map(fn (string $f): string => url($prefix.$f), $files);
        }

        return $out;
    }

    /**
     * Audit hreflang: pasangan URL kanonik per locale + masalah umum.
     *
     * @return array{default: string, locales: list<string>, samples: list<array{canonical: string, alternates: array<string, string>}>, issues: list<string>}
     */
    public function hreflangAudit(int $samples = 5): array
    {
        $locales = $this->locales();
        $default = $this->defaultLocale();
        $issues = [];
        $paths = ['/', '/products', '/blog'];

        try {
            $product = Product::query()->where('status', 'approved')->where('published', true)->orderByDesc('id')->first();
            if ($product) {
                $paths[] = '/products/'.ltrim((string) $product->slug, '/');
            }
        } catch (\Throwable) {
        }
        try {
            $post = \App\Models\BlogPost::query()->where('is_published', true)->orderByDesc('id')->first();
            if ($post) {
                $paths[] = '/blog/'.ltrim((string) $post->slug, '/');
            }
        } catch (\Throwable) {
        }

        $samplesOut = [];
        foreach (array_slice($paths, 0, max(1, min(10, $samples))) as $path) {
            $alternates = [];
            foreach ($locales as $locale) {
                $alternates[$locale] = url(($locale === $default ? '' : $locale.'/').ltrim($path, '/'));
            }
            // x-default selalu ke default.
            $alternates['x-default'] = $alternates[$default] ?? url(ltrim($path, '/'));
            $samplesOut[] = ['canonical' => $alternates[$default], 'alternates' => $alternates];
        }

        if (count($locales) < 2) {
            $issues[] = 'Hanya 1 locale aktif — hreflang belum bermakna. Tambahkan locale kedua di manajemen bahasa.';
        }
        try {
            $missingEn = app(\App\Services\Localization\MissingDetector::class)->missingKeys('en');
            if (count($missingEn) > 0) {
                $issues[] = count($missingEn).' kunci EN hilang — fallback hreflang EN berisiko menampilkan string mentah.';
            }
        } catch (\Throwable) {
        }
        if (! \Illuminate\Support\Facades\File::exists(public_path('sitemap.xml'))) {
            $issues[] = 'sitemap.xml belum terbit — jalankan php artisan sitemap:generate.';
        }

        return ['default' => $default, 'locales' => $locales, 'samples' => $samplesOut, 'issues' => $issues];
    }

    private function defaultLocale(): string
    {
        try {
            return app(\App\Services\Localization\LanguageService::class)->defaultCode();
        } catch (\Throwable) {
            return 'id';
        }
    }

    /**
     * @return array<string, string>
     */
    public function robotsPreview(): array
    {
        $path = public_path('robots.txt');

        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /checkout',
            'Disallow: /cart',
            (string) SystemSetting::get('seo_robots', 'index,follow') === 'noindex,nofollow' ? 'Disallow: /' : 'Allow: /',
            '',
            'Sitemap: '.rtrim((string) config('app.url'), '/').'/sitemap.xml',
        ];

        return [
            'path' => $path,
            'exists' => File::exists($path),
            'content' => File::exists($path) ? (string) File::get($path) : implode("\n", $lines)."\n",
        ];
    }
}
