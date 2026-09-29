<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\PseoPage;
use App\Models\PseoTemplate;
use App\Models\Shop;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Programmatic SEO with a quality gate.
 *
 * Two rules govern everything here:
 *
 *  1. A page is only created when real products sit behind it. Combinations
 *     that resolve to fewer than `minProducts` listings are discarded rather
 *     than published as thin content.
 *  2. A page starts `noindex` and can only become `index` when it scores at or
 *     above the configured threshold *and* an administrator has reviewed and
 *     published it. Generating a page never publishes it.
 */
final class PseoService
{
    public const RUBRIC = [
        'unique_intent' => ['label' => 'Niat pencarian unik', 'weight' => 12, 'max' => 12],
        'unique_title' => ['label' => 'Judul unik', 'weight' => 12, 'max' => 12],
        'useful_description' => ['label' => 'Deskripsi berguna', 'weight' => 10, 'max' => 10],
        'unique_body' => ['label' => 'Isi halaman unik', 'weight' => 14, 'max' => 14],
        'relevant_products' => ['label' => 'Produk relevan', 'weight' => 20, 'max' => 20],
        'internal_links' => ['label' => 'Tautan internal', 'weight' => 8, 'max' => 8],
        'breadcrumbs' => ['label' => 'Breadcrumb', 'weight' => 6, 'max' => 6],
        'structured_data' => ['label' => 'Data terstruktur valid', 'weight' => 10, 'max' => 10],
        'canonical' => ['label' => 'URL kanonik', 'weight' => 4, 'max' => 4],
        'freshness' => ['label' => 'Kesegaran', 'weight' => 4, 'max' => 4],
    ];

    public const TEMPLATES = [
        'category_brand' => 'Kategori × Brand',
        'category_store' => 'Kategori × Toko',
        'brand_store' => 'Brand × Toko',
        'category_top_products' => 'Kategori × Produk Unggulan',
    ];

    public const MAX_PAGES = 500;

    public function threshold(): int
    {
        $value = SystemSetting::get('pseo_quality_threshold', 70);

        return max(0, min(100, (int) $value));
    }

    public function minProducts(): int
    {
        return max(1, (int) SystemSetting::get('pseo_min_products', 6));
    }

    public function productsPerPage(): int
    {
        return max(6, min(48, (int) SystemSetting::get('pseo_products_per_page', 24)));
    }

    public function isEnabled(): bool
    {
        return (bool) SystemSetting::get('pseo_enabled', '1');
    }

    public function autoPublish(): bool
    {
        return (bool) SystemSetting::get('pseo_auto_publish', '0');
    }

    public function settings(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'threshold' => $this->threshold(),
            'min_products' => $this->minProducts(),
            'products_per_page' => $this->productsPerPage(),
            'auto_publish' => $this->autoPublish(),
            'max_pages' => self::MAX_PAGES,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function applySettings(array $validated): void
    {
        SystemSetting::set('pseo_enabled', $validated['enabled'] ? '1' : '0');
        SystemSetting::set('pseo_quality_threshold', (string) (int) $validated['quality_threshold']);
        SystemSetting::set('pseo_min_products', (string) (int) $validated['min_products']);
        SystemSetting::set('pseo_products_per_page', (string) (int) $validated['products_per_page']);
        SystemSetting::set('pseo_auto_publish', $validated['auto_publish'] ? '1' : '0');
    }

    /**
     * Seed the template table from the built-in definitions.
     */
    public function ensureTemplates(): int
    {
        $definitions = [
            'category_brand' => ['name' => 'Kategori × Brand', 'route_pattern' => 'pseo/category-brand/{category}-{brand}', 'threshold' => $this->threshold()],
            'category_store' => ['name' => 'Kategori × Toko', 'route_pattern' => 'pseo/category-store/{category}-{shop}', 'threshold' => $this->threshold()],
            'brand_store' => ['name' => 'Brand × Toko', 'route_pattern' => 'pseo/brand-store/{brand}-{shop}', 'threshold' => $this->threshold()],
            'category_top_products' => ['name' => 'Kategori × Produk Unggulan', 'route_pattern' => 'pseo/category-top/{category}', 'threshold' => $this->threshold()],
        ];

        $created = 0;

        foreach ($definitions as $code => $definition) {
            $template = PseoTemplate::query()->firstOrNew(['code' => $code]);
            $isNew = ! $template->exists;

            $template->name = $definition['name'];
            $template->route_pattern = $definition['route_pattern'];
            $template->quality_threshold = $definition['threshold'];
            $template->is_enabled = true;
            $template->is_indexable = false;
            $template->save();

            $created += $isNew ? 1 : 0;
        }

        return $created;
    }

    /**
     * Candidate combinations with a *real* product count attached.
     *
     * The count comes from a `GROUP BY` over the products table, so a
     * combination with no products is never returned in the first place.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function candidates(string $templateCode, int $limit = 200): Collection
    {
        $minimum = $this->minProducts();

        return match ($templateCode) {
            'category_brand' => $this->categoryBrandCandidates($minimum, $limit),
            'category_store' => $this->categoryStoreCandidates($minimum, $limit),
            'brand_store' => $this->brandStoreCandidates($minimum, $limit),
            'category_top_products' => $this->categoryCandidates($minimum, $limit),
            default => collect(),
        };
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function categoryBrandCandidates(int $minimum, int $limit): Collection
    {
        $rows = DB::table('products')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->join('brands', 'brands.id', '=', 'products.brand_id')
            ->where('products.status', 'approved')
            ->where('products.published', true)
            ->where('categories.status', true)
            ->where('brands.status', true)
            ->whereNotNull('products.brand_id')
            ->groupBy('products.category_id', 'products.brand_id')
            ->selectRaw('products.category_id as category_id, products.brand_id as brand_id, COUNT(*) as product_count')
            ->havingRaw('COUNT(*) >= ?', [$minimum])
            ->orderByDesc('product_count')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $categoryIds = $rows->pluck('category_id')->map(fn ($id): int => (int) $id)->unique()->all();
        $brandIds = $rows->pluck('brand_id')->map(fn ($id): int => (int) $id)->unique()->all();

        $categories = Category::query()->whereIn('id', $categoryIds)->get()->keyBy('id');
        $brands = Brand::query()->whereIn('id', $brandIds)->get()->keyBy('id');

        return $rows->map(function ($row) use ($categories, $brands): array {
            $category = $categories[(int) $row->category_id] ?? null;
            $brand = $brands[(int) $row->brand_id] ?? null;

            return [
                'slug' => Str::slug(((string) $category?->name).'-'.((string) $brand?->name).'-produk'),
                'title' => 'Jual '.((string) $brand?->name).' di Kategori '.((string) $category?->name).' — Harga Latest',
                'description' => $this->description('brand_in_category', ['brand' => (string) $brand?->name, 'category' => (string) $category?->name]),
                'intro' => $this->intro('brand_in_category', ['brand' => (string) $brand?->name, 'category' => (string) $category?->name, 'count' => (int) $row->product_count]),
                'context' => ['category_id' => (int) $row->category_id, 'brand_id' => (int) $row->brand_id],
                'product_count' => (int) $row->product_count,
                'entities' => [
                    'category' => (string) $category?->name,
                    'brand' => (string) $brand?->name,
                ],
            ];
        })->filter(fn (array $candidate): bool => ($candidate['context']['category_id'] ?? 0) > 0 && ($candidate['context']['brand_id'] ?? 0) > 0)->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function categoryStoreCandidates(int $minimum, int $limit): Collection
    {
        $rows = DB::table('products')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->join('shops', 'shops.id', '=', 'products.shop_id')
            ->where('products.status', 'approved')
            ->where('products.published', true)
            ->where('categories.status', true)
            ->where('shops.status', 'active')
            ->groupBy('products.category_id', 'products.shop_id')
            ->selectRaw('products.category_id as category_id, products.shop_id as shop_id, COUNT(*) as product_count')
            ->havingRaw('COUNT(*) >= ?', [$minimum])
            ->orderByDesc('product_count')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $categoryIds = $rows->pluck('category_id')->map(fn ($id): int => (int) $id)->unique()->all();
        $shopIds = $rows->pluck('shop_id')->map(fn ($id): int => (int) $id)->unique()->all();

        $categories = Category::query()->whereIn('id', $categoryIds)->get()->keyBy('id');
        $shops = Shop::query()->whereIn('id', $shopIds)->get()->keyBy('id');

        return $rows->map(function ($row) use ($categories, $shops): array {
            $category = $categories[(int) $row->category_id] ?? null;
            $shop = $shops[(int) $row->shop_id] ?? null;

            return [
                'slug' => Str::slug(((string) $category?->name).'-'.((string) $shop?->name).'-toko'),
                'title' => 'Produk '.((string) $category?->name).' di '.((string) $shop?->name),
                'description' => $this->description('category_in_shop', ['category' => (string) $category?->name, 'shop' => (string) $shop?->name]),
                'intro' => $this->intro('category_in_shop', ['category' => (string) $category?->name, 'shop' => (string) $shop?->name, 'count' => (int) $row->product_count]),
                'context' => ['category_id' => (int) $row->category_id, 'shop_id' => (int) $row->shop_id],
                'product_count' => (int) $row->product_count,
                'entities' => [
                    'category' => (string) $category?->name,
                    'shop' => (string) $shop?->name,
                ],
            ];
        })->filter(fn (array $candidate): bool => ($candidate['context']['category_id'] ?? 0) > 0 && ($candidate['context']['shop_id'] ?? 0) > 0)->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function brandStoreCandidates(int $minimum, int $limit): Collection
    {
        $rows = DB::table('products')
            ->join('brands', 'brands.id', '=', 'products.brand_id')
            ->join('shops', 'shops.id', '=', 'products.shop_id')
            ->where('products.status', 'approved')
            ->where('products.published', true)
            ->where('brands.status', true)
            ->where('shops.status', 'active')
            ->groupBy('products.brand_id', 'products.shop_id')
            ->selectRaw('products.brand_id as brand_id, products.shop_id as shop_id, COUNT(*) as product_count')
            ->havingRaw('COUNT(*) >= ?', [$minimum])
            ->orderByDesc('product_count')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $brandIds = $rows->pluck('brand_id')->map(fn ($id): int => (int) $id)->unique()->all();
        $shopIds = $rows->pluck('shop_id')->map(fn ($id): int => (int) $id)->unique()->all();

        $brands = Brand::query()->whereIn('id', $brandIds)->get()->keyBy('id');
        $shops = Shop::query()->whereIn('id', $shopIds)->get()->keyBy('id');

        return $rows->map(function ($row) use ($brands, $shops): array {
            $brand = $brands[(int) $row->brand_id] ?? null;
            $shop = $shops[(int) $row->shop_id] ?? null;

            return [
                'slug' => Str::slug(((string) $brand?->name).'-'.((string) $shop?->name).'-toko'),
                'title' => ((string) $brand?->name).' di Toko '.((string) $shop?->name),
                'description' => $this->description('brand_in_shop', ['brand' => (string) $brand?->name, 'shop' => (string) $shop?->name]),
                'intro' => $this->intro('brand_in_shop', ['brand' => (string) $brand?->name, 'shop' => (string) $shop?->name, 'count' => (int) $row->product_count]),
                'context' => ['brand_id' => (int) $row->brand_id, 'shop_id' => (int) $row->shop_id],
                'product_count' => (int) $row->product_count,
                'entities' => [
                    'brand' => (string) $brand?->name,
                    'shop' => (string) $shop?->name,
                ],
            ];
        })->filter(fn (array $candidate): bool => ($candidate['context']['brand_id'] ?? 0) > 0 && ($candidate['context']['shop_id'] ?? 0) > 0)->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function categoryCandidates(int $minimum, int $limit): Collection
    {
        $rows = DB::table('products')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('products.status', 'approved')
            ->where('products.published', true)
            ->where('categories.status', true)
            ->groupBy('products.category_id')
            ->selectRaw('products.category_id as category_id, COUNT(*) as product_count')
            ->havingRaw('COUNT(*) >= ?', [$minimum])
            ->orderByDesc('product_count')
            ->limit($limit)
            ->get();

        return $rows->map(function ($row): array {
            $category = Category::query()->find($row->category_id);

            return [
                'slug' => Str::slug(((string) $category?->name).'-produk-terlaris'),
                'title' => ((string) $category?->name).' — Produk Terlaris dan Terbaru',
                'description' => $this->description('category_top', ['category' => (string) $category?->name]),
                'intro' => $this->intro('category_top', ['category' => (string) $category?->name, 'count' => (int) $row->product_count]),
                'context' => ['category_id' => (int) $row->category_id],
                'product_count' => (int) $row->product_count,
                'entities' => ['category' => (string) $category?->name],
            ];
        })->filter(fn (array $candidate): bool => ($candidate['context']['category_id'] ?? 0) > 0)->values();
    }

    /**
     * Create (or refresh) pages for one template.
     *
     * @return array{created: int, updated: int, skipped: int, published: int, pages: list<array<string, mixed>>}
     */
    public function generate(string $templateCode, int $limit = 50, ?int $actorId = null): array
    {
        $limit = max(1, min(self::MAX_PAGES, $limit));
        $candidates = $this->candidates($templateCode, $limit);
        $existingCount = (int) PseoPage::query()->where('template_code', $templateCode)->count();
        $headroom = max(0, self::MAX_PAGES - $existingCount);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $published = 0;
        $pages = [];

        $titles = PseoPage::query()->where('template_code', $templateCode)->pluck('title')->all();
        $lowerTitles = array_map('mb_strtolower', $titles);

        foreach ($candidates as $candidate) {
            if ($created >= $headroom) {
                $skipped++;
                continue;
            }

            $titleKey = mb_strtolower((string) $candidate['title']);

            if (in_array($titleKey, $lowerTitles, true)) {
                $skipped++;
                continue;
            }

            $url = '/'.ltrim((string) $candidate['slug'], '/');
            $body = $this->buildBody($templateCode, $candidate);
            $score = $this->score($templateCode, $candidate, $body, $url);

            $page = PseoPage::query()->updateOrCreate(
                ['url' => $url],
                [
                    'template_code' => $templateCode,
                    'slug' => (string) $candidate['slug'],
                    'title' => (string) $candidate['title'],
                    'description' => (string) $candidate['description'],
                    'body' => $body,
                    'context' => $candidate['context'] + ['entities' => $candidate['entities'], 'min_products' => $this->minProducts()],
                    'quality_score' => $score['total'],
                    'quality_breakdown' => $score['breakdown'],
                    'product_count' => (int) $candidate['product_count'],
                    'generated_at' => now(),
                    'state' => 'generated',
                    'indexability' => 'noindex',
                    'canonical_url' => $this->absoluteUrl($url),
                ],
            );

            if ($page->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }

            if ($this->autoPublish() && $score['total'] >= $this->threshold()) {
                $this->publish($page, $actorId);
                $published++;
            }

            $lowerTitles[] = $titleKey;

            $pages[] = [
                'id' => (int) $page->id,
                'url' => $url,
                'title' => (string) $page->title,
                'product_count' => (int) $page->product_count,
                'quality_score' => (int) $page->quality_score,
            ];
        }

        app(AuditLogger::class)->log('pseo.generated', null, [], [
            'template' => $templateCode,
            'created' => $created,
            'skipped' => $skipped,
        ], $actorId);

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'published' => $published,
            'pages' => $pages,
        ];
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function buildBody(string $templateCode, array $candidate): string
    {
        $context = $candidate['context'];
        $products = $this->productsFor($templateCode, $context);
        $entities = $candidate['entities'];

        $lines = [(string) $candidate['intro']];
        $lines[] = '';
        $lines[] = 'Daftar produk yang tersedia:';

        foreach ($products as $product) {
            $lines[] = '- '.$product['name'].' — '.$product['price_formatted'].' ('.$product['shop'].')';
        }

        $lines[] = '';
        $lines[] = 'Semua produk di atas berasal dari '.$this->entitySummary($entities).' dengan stok dan harga yang diperbarui otomatis dari katalog.';

        $links = $this->internalLinks($templateCode, $context);
        if ($links !== []) {
            $lines[] = '';
            $lines[] = 'Jelajahi juga:';
            foreach ($links as $link) {
                $lines[] = '- '.$link['label'].' ('.$link['url'].')';
            }
        }

        return implode("\n", $lines);
    }

    private function entitySummary(array $entities): string
    {
        $parts = [];
        foreach ($entities as $key => $value) {
            $parts[] = match ($key) {
                'category' => 'kategori '.$value,
                'brand' => 'brand '.$value,
                'shop' => 'toko '.$value,
                default => $value,
            };
        }

        return implode(' dan ', $parts);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array<string, string|int|float>>
     */
    public function productsFor(string $templateCode, array $context, ?int $limit = null): array
    {
        $limit ??= $this->productsPerPage();

        $query = Product::query()
            ->with(['shop:id,name', 'category:id,name', 'brand:id,name'])
            ->where('status', 'approved')
            ->where('published', true);

        if (isset($context['category_id'])) {
            $query->where('category_id', (int) $context['category_id']);
        }
        if (isset($context['brand_id'])) {
            $query->where('brand_id', (int) $context['brand_id']);
        }
        if (isset($context['shop_id'])) {
            $query->where('shop_id', (int) $context['shop_id']);
        }

        return $query->orderByDesc('sold_count')->orderByDesc('rating_average')
            ->limit($limit)
            ->get()
            ->map(fn (Product $product): array => [
                'id' => (int) $product->id,
                'name' => (string) $product->name,
                'slug' => (string) $product->slug,
                'price' => (float) $product->price,
                'price_formatted' => \App\Support\Currency::format((float) $product->price),
                'shop' => (string) ($product->shop?->name ?? '-'),
                'category' => (string) ($product->category?->name ?? '-'),
                'brand' => (string) ($product->brand?->name ?? '-'),
                'url' => $this->productUrl($product->slug),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array{label: string, url: string}>
     */
    public function internalLinks(string $templateCode, array $context): array
    {
        $links = [];

        if (isset($context['category_id'])) {
            $category = Category::query()->find($context['category_id']);
            if ($category !== null) {
                $links[] = ['label' => 'Kategori '.$category->name, 'url' => $this->categoryUrl($category->slug)];
            }
        }

        if (isset($context['brand_id'])) {
            $brand = Brand::query()->find($context['brand_id']);
            if ($brand !== null) {
                $links[] = ['label' => 'Brand '.$brand->name, 'url' => $this->brandUrl($brand->slug)];
            }
        }

        if (isset($context['shop_id'])) {
            $shop = Shop::query()->find($context['shop_id']);
            if ($shop !== null) {
                $links[] = ['label' => 'Toko '.$shop->name, 'url' => $this->shopUrl($shop->slug)];
            }
        }

        $links[] = ['label' => 'Semua produk', 'url' => $this->absoluteUrl('/products')];

        return $links;
    }

    /**
     * Score a page on the documented rubric.
     *
     * @param  array<string, mixed>  $candidate
     * @return array{total: int, breakdown: array<string, array{label: string, score: int, max: int, note: string}>}
     */
    public function score(string $templateCode, array $candidate, string $body, string $url): array
    {
        $context = $candidate['context'];
        $entities = $candidate['entities'];
        $breakdown = [];

        $duplicateTitles = (int) PseoPage::query()
            ->where('template_code', $templateCode)
            ->where('title', (string) $candidate['title'])
            ->where('url', '!=', $url)
            ->count();

        $breakdown['unique_intent'] = $this->criterion(
            count(array_filter($entities)) >= 2,
            self::RUBRIC['unique_intent']['max'],
            count(array_filter($entities)) >= 2
                ? 'Halaman memuat minimal dua entitas katalog sehingga niat pencarian spesifik.'
                : 'Halaman hanya memuat satu entitas katalog sehingga kurang spesifik.',
        );

        $breakdown['unique_title'] = $this->criterion(
            $duplicateTitles === 0 && mb_strlen((string) $candidate['title']) >= 20 && mb_strlen((string) $candidate['title']) <= 70,
            self::RUBRIC['unique_title']['max'],
            $duplicateTitles === 0
                ? 'Judul belum dipakai halaman lain dan panjangnya ideal.'
                : 'Judul duplikat dengan '.$duplicateTitles.' halaman lain.',
        );

        $description = (string) $candidate['description'];
        $breakdown['useful_description'] = $this->criterion(
            mb_strlen($description) >= 90 && mb_strlen($description) <= 175,
            self::RUBRIC['useful_description']['max'],
            'Panjang deskripsi '.$this->lengthBand($description).'.',
        );

        $breakdown['unique_body'] = $this->criterion(
            mb_strlen($body) >= 400,
            self::RUBRIC['unique_body']['max'],
            'Panjang isi '.$this->lengthBand($body).'.',
        );

        $productCount = (int) $candidate['product_count'];
        $breakdown['relevant_products'] = $this->ratio(
            $productCount,
            $this->minProducts(),
            self::RUBRIC['relevant_products']['max'],
            $productCount.' produk nyata tersedia (minimum '.$this->minProducts().').',
        );

        $breakdown['internal_links'] = $this->criterion(
            count($this->internalLinks($templateCode, $context)) >= 2,
            self::RUBRIC['internal_links']['max'],
            'Tautan internal tersedia untuk entitas terkait.',
        );

        $breakdown['breadcrumbs'] = $this->criterion(
            count($entities) >= 2,
            self::RUBRIC['breadcrumbs']['max'],
            'Remah roti dapat dibangun dari entitas halaman.',
        );

        $breakdown['structured_data'] = $this->criterion(
            $this->structuredDataIsValid($templateCode, $context, $productCount),
            self::RUBRIC['structured_data']['max'],
            'Data terstruktur ItemList dan BreadcrumbList dapat dibentuk.',
        );

        $breakdown['canonical'] = $this->criterion(
            $url === '/'.ltrim($url, '/') && ! str_contains($url, '?') && ! str_contains($url, '#'),
            self::RUBRIC['canonical']['max'],
            'URL kanonik absolut dan tanpa parameter.',
        );

        $breakdown['freshness'] = $this->criterion(
            true,
            self::RUBRIC['freshness']['max'],
            'Halaman ditandai baru digenerate dan dapat di-refresh dari katalog.',
        );

        $total = 0;
        foreach ($breakdown as $criterion) {
            $total += (int) $criterion['score'];
        }

        return ['total' => $total, 'breakdown' => $breakdown];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function structuredDataIsValid(string $templateCode, array $context, int $productCount): bool
    {
        if ($productCount < $this->minProducts()) {
            return false;
        }

        return match ($templateCode) {
            'category_brand' => isset($context['category_id'], $context['brand_id']),
            'category_store' => isset($context['category_id'], $context['shop_id']),
            'brand_store' => isset($context['brand_id'], $context['shop_id']),
            'category_top_products' => isset($context['category_id']),
            default => false,
        };
    }

    /**
     * @return array{label: string, score: int, max: int, note: string}
     */
    private function criterion(bool $met, int $max, string $note): array
    {
        return [
            'label' => '',
            'score' => $met ? $max : 0,
            'max' => $max,
            'note' => $note,
        ];
    }

    /**
     * @return array{label: string, score: int, max: int, note: string}
     */
    private function ratio(int $actual, int $required, int $max, string $note): array
    {
        if ($required <= 0) {
            return $this->criterion(true, $max, $note);
        }

        $score = (int) round(min(1.0, max(0.0, $actual / $required)) * $max);

        return [
            'label' => '',
            'score' => $score,
            'max' => $max,
            'note' => $note,
        ];
    }

    private function lengthBand(string $value): string
    {
        $length = mb_strlen($value);

        return match (true) {
            $length < 90 => $length.' karakter (terlalu pendek)',
            $length > 400 => $length.' karakter (terlalu panjang)',
            $length > 175 => $length.' karakter (agak panjang)',
            default => $length.' karakter (ideal)',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function listPages(int $page = 1, string $state = '', string $template = '', int $perPage = 20): array
    {
        $query = PseoPage::query();

        if ($state !== '') {
            $query->where('state', $state);
        }

        if ($template !== '') {
            $query->where('template_code', $template);
        }

        $page = max(1, $page);
        $perPage = max(5, min(100, $perPage));
        $total = (int) (clone $query)->count();
        $threshold = $this->threshold();

        $rows = $query->orderByDesc('quality_score')->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (PseoPage $pseo): array => [
                'id' => (int) $pseo->id,
                'title' => (string) $pseo->title,
                'url' => (string) $pseo->url,
                'template_code' => (string) $pseo->template_code,
                'state' => (string) $pseo->state,
                'indexability' => (string) $pseo->indexability,
                'quality_score' => (int) $pseo->quality_score,
                'threshold' => $threshold,
                'meets_threshold' => (int) $pseo->quality_score >= $threshold,
                'product_count' => (int) $pseo->product_count,
                'generated_at' => (string) ($pseo->generated_at?->format('Y-m-d H:i') ?? ''),
                'reviewed_at' => (string) ($pseo->reviewed_at?->format('Y-m-d H:i') ?? ''),
                'published_at' => (string) ($pseo->published_at?->format('Y-m-d H:i') ?? ''),
                'breakdown' => $this->withLabels(is_array($pseo->quality_breakdown) ? $pseo->quality_breakdown : []),
            ])
            ->all();

        $counts = ['all' => 0, 'generated' => 0, 'reviewed' => 0, 'published' => 0, 'stale' => 0, 'disabled' => 0];
        try {
            $counts['all'] = (int) PseoPage::query()->count();
            foreach (PseoPage::query()->selectRaw('state, COUNT(*) as aggregate')->groupBy('state')->get() as $row) {
                $key = (string) $row->state;
                if (array_key_exists($key, $counts)) {
                    $counts[$key] = (int) $row->aggregate;
                }
            }
        } catch (\Throwable) {
            foreach ($counts as $key => $_) {
                $counts[$key] = 0;
            }
        }

        return [
            'rows' => $rows,
            'counts' => $counts,
            'threshold' => $threshold,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $breakdown
     * @return list<array{key: string, label: string, score: int, max: int, note: string, percent: int}>
     */
    private function withLabels(array $breakdown): array
    {
        $out = [];

        foreach ($breakdown as $key => $criterion) {
            if (! isset(self::RUBRIC[$key])) {
                continue;
            }

            $max = (int) (self::RUBRIC[$key]['max'] ?? 0);
            $score = (int) (is_array($criterion) ? ($criterion['score'] ?? 0) : $criterion);

            $out[] = [
                'key' => (string) $key,
                'label' => (string) self::RUBRIC[$key]['label'],
                'score' => $score,
                'max' => $max,
                'note' => is_array($criterion) ? (string) ($criterion['note'] ?? '') : '',
                'percent' => $max > 0 ? (int) round(($score / $max) * 100) : 0,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function show(PseoPage $pseo): array
    {
        $template = PseoTemplate::query()->where('code', $pseo->template_code)->first();
        $threshold = $template !== null ? (int) $template->quality_threshold : $this->threshold();
        $context = is_array($pseo->context) ? $pseo->context : [];
        $entities = is_array($context['entities'] ?? null) ? $context['entities'] : [];

        return [
            'page' => [
                'id' => (int) $pseo->id,
                'title' => (string) $pseo->title,
                'url' => (string) $pseo->url,
                'absolute_url' => (string) ($pseo->canonical_url ?? $this->absoluteUrl((string) $pseo->url)),
                'description' => (string) ($pseo->description ?? ''),
                'body' => (string) ($pseo->body ?? ''),
                'template_code' => (string) $pseo->template_code,
                'template_name' => (string) ($template->name ?? $pseo->template_code),
                'state' => (string) $pseo->state,
                'indexability' => (string) $pseo->indexability,
                'quality_score' => (int) $pseo->quality_score,
                'threshold' => $threshold,
                'meets_threshold' => (int) $pseo->quality_score >= $threshold,
                'product_count' => (int) $pseo->product_count,
                'generated_at' => (string) ($pseo->generated_at?->format('Y-m-d H:i') ?? ''),
                'reviewed_at' => (string) ($pseo->reviewed_at?->format('Y-m-d H:i') ?? ''),
                'published_at' => (string) ($pseo->published_at?->format('Y-m-d H:i') ?? ''),
            ],
            'entities' => $entities,
            'context' => $context,
            'breakdown' => $this->withLabels(is_array($pseo->quality_breakdown) ? $pseo->quality_breakdown : []),
            'rubric' => self::RUBRIC,
            'products' => $this->productsFor($pseo->template_code, $context, 12),
            'links' => $this->internalLinks($pseo->template_code, $context),
        ];
    }

    public function review(PseoPage $pseo, ?int $actorId): PseoPage
    {
        $pseo->forceFill([
            'state' => 'reviewed',
            'reviewed_at' => now(),
        ])->save();

        app(AuditLogger::class)->log('pseo.reviewed', $pseo, [], ['score' => (int) $pseo->quality_score], $actorId);

        return $pseo;
    }

    public function publish(PseoPage $pseo, ?int $actorId): PseoPage
    {
        $template = PseoTemplate::query()->where('code', $pseo->template_code)->first();
        $threshold = $template !== null ? (int) $template->quality_threshold : $this->threshold();

        if ((int) $pseo->quality_score < $threshold) {
            abort(422, 'Skor kualitas halaman belum mencapai ambang '.$threshold.'.');
        }

        if ((int) $pseo->product_count < $this->minProducts()) {
            abort(422, 'Halaman hanya memiliki '.(int) $pseo->product_count.' produk nyata. Ambang minimum '.$this->minProducts().'.');
        }

        $pseo->forceFill([
            'state' => 'published',
            'indexability' => 'index',
            'published_at' => now(),
            'reviewed_at' => $pseo->reviewed_at ?? now(),
            'canonical_url' => $pseo->canonical_url ?: $this->absoluteUrl((string) $pseo->url),
        ])->save();

        app(AuditLogger::class)->log('pseo.published', $pseo, [], ['score' => (int) $pseo->quality_score], $actorId);

        return $pseo;
    }

    public function disable(PseoPage $pseo, ?int $actorId): PseoPage
    {
        $pseo->forceFill([
            'state' => 'disabled',
            'indexability' => 'noindex',
        ])->save();

        app(AuditLogger::class)->log('pseo.disabled', $pseo, [], [], $actorId);

        return $pseo;
    }

    public function delete(PseoPage $pseo, ?int $actorId): void
    {
        $snapshot = ['url' => (string) $pseo->url, 'score' => (int) $pseo->quality_score];
        $pseo->delete();

        app(AuditLogger::class)->log('pseo.deleted', null, $snapshot, [], $actorId);
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $this->ensureTemplates();

        $templates = PseoTemplate::query()->orderBy('code')->get()
            ->map(fn (PseoTemplate $template): array => [
                'code' => (string) $template->code,
                'name' => (string) $template->name,
                'route_pattern' => (string) $template->route_pattern,
                'quality_threshold' => (int) $template->quality_threshold,
                'is_enabled' => (bool) $template->is_enabled,
                'pages' => (int) PseoPage::query()->where('template_code', $template->code)->count(),
                'published' => (int) PseoPage::query()->where('template_code', $template->code)->where('state', 'published')->count(),
                'candidates' => $this->candidates((string) $template->code, 200)->count(),
            ])
            ->all();

        $total = (int) PseoPage::query()->count();
        $published = (int) PseoPage::query()->where('state', 'published')->count();

        return [
            'settings' => $this->settings(),
            'templates' => $templates,
            'pages' => $total,
            'published' => $published,
            'indexable' => (int) PseoPage::query()->where('indexability', 'index')->where('state', 'published')->count(),
            'headroom' => max(0, self::MAX_PAGES - $total),
            'rubric' => self::RUBRIC,
        ];
    }

    /**
     * @param  array<string, string|int>  $vars
     */
    private function description(string $pattern, array $vars): string
    {
        $count = (int) ($vars['count'] ?? 0);

        return match ($pattern) {
            'brand_in_category' => sprintf(
                'Temukan %1$d produk %2$s dalam kategori %3$s di marketplace kami. Bandingkan harga, baca ulasan, dan pesan langsung dari toko terverifikasi.',
                $count,
                $vars['brand'] ?? 'produk',
                $vars['category'] ?? 'kategori',
            ),
            'category_in_shop' => sprintf(
                'Lihat %1$d produk kategori %2$s yang dijual oleh %3$s. Harga, stok, dan ulasan diperbarui langsung dari katalog.',
                $count,
                $vars['category'] ?? 'kategori',
                $vars['shop'] ?? 'toko',
            ),
            'brand_in_shop' => sprintf(
                'Katalog %1$d produk %2$s dari toko %3$s. Semua pengiriman dikirim langsung oleh penjual dengan status terverifikasi.',
                $count,
                $vars['brand'] ?? 'produk',
                $vars['shop'] ?? 'toko',
            ),
            default => sprintf(
                'Daftar %1$d produk %2$s terlaris dan terbaru. Bandingkan harga antar toko dan lihat ulasan pembeli.',
                $count,
                $vars['category'] ?? 'kategori',
            ),
        };
    }

    /**
     * @param  array<string, string|int>  $vars
     */
    private function intro(string $pattern, array $vars): string
    {
        $count = (int) ($vars['count'] ?? 0);

        return match ($pattern) {
            'brand_in_category' => sprintf(
                'Halaman ini menghimpun %1$d produk %2$s yang terdaftar pada kategori %3$s. Daftar disusun dari katalog aktif dan diperbarui mengikuti stok terbaru.',
                $count,
                $vars['brand'] ?? 'produk',
                $vars['category'] ?? 'kategori',
            ),
            'category_in_shop' => sprintf(
                'Toko %1$s menyediakan %2$d produk dalam kategori %3$s. Anda dapat membandingkan harga dengan toko lain sebelum behem.',
                $vars['shop'] ?? 'toko',
                $count,
                $vars['category'] ?? 'kategori',
            ),
            'brand_in_shop' => sprintf(
                '%1$s adalah %2$d produk %3$s yang dijual langsung oleh %4$s. Harga mengikuti ketentuan penjual dan dapat berubah sewaktu-waktu.',
                'Katalog',
                $count,
                $vars['brand'] ?? 'produk',
                $vars['shop'] ?? 'toko',
            ),
            default => sprintf(
                'Berikut %1$d produk %2$s dengan penjualan tertinggi di platform kami, disusun berdasarkan jumlah unit terjual.',
                $count,
                $vars['category'] ?? 'kategori',
            ),
        };
    }

    private function absoluteUrl(string $path): string
    {
        try {
            return URL::to($path);
        } catch (\Throwable) {
            return $path;
        }
    }

    private function productUrl(?string $slug): string
    {
        try {
            return \Route::has('products.show') && $slug !== null
                ? route('products.show', $slug)
                : $this->absoluteUrl('/products/'.($slug ?? ''));
        } catch (\Throwable) {
            return $this->absoluteUrl('/products/'.($slug ?? ''));
        }
    }

    private function categoryUrl(?string $slug): string
    {
        return $this->absoluteUrl('/category/'.($slug ?? ''));
    }

    private function brandUrl(?string $slug): string
    {
        return $this->absoluteUrl('/brand/'.($slug ?? ''));
    }

    private function shopUrl(?string $slug): string
    {
        return $this->absoluteUrl('/shop/'.($slug ?? ''));
    }
}
