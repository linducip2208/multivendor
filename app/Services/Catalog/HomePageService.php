<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Banner;
use App\Models\Category;
use App\Models\FlashDeal;
use App\Models\Product;
use App\Models\Shop;
use App\Support\Feature;
use App\Enums\PlatformFeature;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Homepage composition.
 *
 * Sections are persisted in `homepage_sections` (admin ordered / enabled /
 * configured) with a code-defined default order. The view renders whichever
 * sections the admin left switched on — nothing about the homepage structure is
 * hardcoded in Blade.
 */
class HomePageService
{
    public const CACHE_KEY = 'homepage:v1';
    public const CACHE_TTL = 300;

    /**
     * Every section code the renderer understands, in default order.
     *
     * @return list<array{code:string, title:string, icon:string}>
     */
    public static function registry(): array
    {
        return [
            ['code' => 'hero', 'title' => 'Hero Banner', 'icon' => 'image'],
            ['code' => 'flash_sale', 'title' => 'Flash Sale', 'icon' => 'flame'],
            ['code' => 'categories', 'title' => 'Kategori Pilihan', 'icon' => 'grid'],
            ['code' => 'promo_banners', 'title' => 'Banner Promosi', 'icon' => 'image'],
            ['code' => 'flash_deals', 'title' => 'Flash Deals', 'icon' => 'flame'],
            ['code' => 'featured', 'title' => 'Produk Unggulan', 'icon' => 'award'],
            ['code' => 'best_sellers', 'title' => 'Terlaris', 'icon' => 'trending'],
            ['code' => 'new_arrivals', 'title' => 'Produk Terbaru', 'icon' => 'sparkles'],
            ['code' => 'recommended', 'title' => 'Rekomendasi Untuk Anda', 'icon' => 'heart'],
            ['code' => 'trending', 'title' => 'Lagi Trending', 'icon' => 'flame'],
            ['code' => 'deals_of_the_day', 'title' => 'Deal of the Day', 'icon' => 'percent'],
            ['code' => 'most_demanded', 'title' => 'Paling Dicari', 'icon' => 'search'],
            ['code' => 'top_brands', 'title' => 'Brand Terpopuler', 'icon' => 'award'],
            ['code' => 'top_stores', 'title' => 'Toko Unggulan', 'icon' => 'store'],
            ['code' => 'category_merchandising', 'title' => 'Kategori Pilihan', 'icon' => 'grid'],
            ['code' => 'buying_guides', 'title' => 'Panduan Belanja', 'icon' => 'book'],
            ['code' => 'blog', 'title' => 'Artikel & Ulasan', 'icon' => 'book'],
            ['code' => 'trust', 'title' => 'Keunggulan Platform', 'icon' => 'shield-check'],
            ['code' => 'newsletter', 'title' => 'Newsletter', 'icon' => 'mail'],
        ];
    }

    /**
     * Ordered, enabled section configuration.
     *
     * @return list<array<string, mixed>>
     */
    public function sections(): array
    {
        $defaults = collect(self::registry())->mapWithKeys(fn (array $s) => [
            $s['code'] => [
                'code' => $s['code'],
                'title' => $s['title'],
                'icon' => $s['icon'],
                'enabled' => true,
                'sort_order' => array_search($s['code'], array_column(self::registry(), 'code'), true),
                'subtitle' => null,
                'settings' => [],
            ],
        ])->all();

        if (! Schema::hasTable('homepage_sections')) {
            return array_values($defaults);
        }

        $rows = \App\Models\HomepageSection::query()
            ->orderBy('sort_order')
            ->get()
            ->keyBy('code')
            ->all();

        foreach ($rows as $code => $row) {
            if (! isset($defaults[$code])) {
                continue;
            }
            $defaults[$code] = [
                'code' => $code,
                'title' => $row->title ?: $defaults[$code]['title'],
                'icon' => $defaults[$code]['icon'],
                'enabled' => (bool) $row->is_enabled,
                'sort_order' => (int) $row->sort_order,
                'subtitle' => $row->subtitle,
                'settings' => $row->settings ?? [],
            ];
        }

        return array_values(array_filter($defaults, fn (array $s) => $s['enabled']));
    }

    /**
     * Data payload for a single section. Only the requested section is loaded,
     * so a disabled section costs zero queries.
     */
    public function data(string $code, array $settings = [], ?int $customerId = null): array
    {
        $limit = (int) ($settings['limit'] ?? 10);

        return match ($code) {
            'hero' => ['slides' => $this->banners('hero', 3)],
            'promo_banners' => ['slides' => $this->banners('sidebar', 3)],
            'categories', 'category_merchandising' => ['categories' => $this->rootCategories(12)],
            'flash_sale' => ['deal' => $this->activeFlashDeal()],
            'flash_deals' => ['products' => $this->flashDealProducts($limit)],
            'deals_of_the_day' => ['deal' => $this->dealOfTheDay()],
            'featured' => ['products' => $this->query($limit, ['featured' => true])],
            'best_sellers' => ['products' => $this->query($limit, [], 'sold_count', 'desc')],
            'new_arrivals' => ['products' => $this->query($limit, [], 'created_at', 'desc')],
            'trending' => ['products' => $this->query($limit, [], 'view_count', 'desc')],
            'most_demanded' => ['products' => $this->mostDemanded($limit)],
            'recommended' => ['products' => $this->recommended($customerId, $limit)],
            'top_brands' => ['brands' => $this->topBrands(12)],
            'top_stores' => ['shops' => $this->topStores(8)],
            'buying_guides' => ['posts' => $this->guides(3)],
            'blog' => ['posts' => $this->guides(4)],
            'trust' => ['items' => $this->trustItems()],
            'newsletter' => ['enabled' => Feature::enabled(PlatformFeature::Loyalty)],
            default => [],
        };
    }

    /* ------------------------------------------------------------------ */

    private function saleable(): \Illuminate\Database\Eloquent\Builder
    {
        return Product::query()
            ->where('status', 'approved')
            ->where('published', true)
            ->whereHas('shop', fn ($q) => $q->where('status', 'active'));
    }

    private function query(int $limit, array $where = [], string $orderBy = 'id', string $direction = 'desc')
    {
        return Cache::remember(
            'home:products:'.md5(serialize([$limit, $where, $orderBy, $direction])),
            self::CACHE_TTL,
            fn () => $this->saleable()
                ->where($where)
                ->with(['shop', 'category', 'brand'])
                ->orderBy($orderBy, $direction)
                ->limit(min(24, max(1, $limit)))
                ->get()
        );
    }

    private function mostDemanded(int $limit)
    {
        return Cache::remember('home:demanded:'.$limit, self::CACHE_TTL, function () use ($limit) {
            $ids = \App\Models\OrderItem::query()
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->whereIn('orders.order_status', ['processing', 'packed', 'shipped', 'delivered', 'completed'])
                ->groupBy('order_items.product_id')
                ->orderByRaw('count(*) DESC')
                ->limit(min(24, max(1, $limit)))
                ->pluck('order_items.product_id');

            return $this->saleable()->whereIn('id', $ids)->with(['shop', 'category', 'brand'])->get();
        });
    }

    private function recommended(?int $customerId, int $limit)
    {
        if (! $customerId) {
            return $this->query($limit, ['featured' => true]);
        }

        $categoryIds = \App\Models\OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.customer_id', $customerId)
            ->groupBy('products.category_id')
            ->orderByRaw('count(*) DESC')
            ->limit(5)
            ->pluck('products.category_id')
            ->all();

        if ($categoryIds === []) {
            return $this->query($limit, ['featured' => true]);
        }

        return Cache::remember('home:reco:'.$customerId.':'.$limit, self::CACHE_TTL, function () use ($limit, $categoryIds) {
            return $this->saleable()
                ->whereIn('category_id', $categoryIds)
                ->with(['shop', 'category', 'brand'])
                ->orderByDesc('rating_average')
                ->limit(min(24, max(1, $limit)))
                ->get();
        });
    }

    private function activeFlashDeal(): ?FlashDeal
    {
        return FlashDeal::query()
            ->where('status', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->withCount('products')
            ->orderByBestDiscount()
            ->first();
    }

    private function flashDealProducts(int $limit)
    {
        $deal = $this->activeFlashDeal();
        if (! $deal) {
            return collect();
        }

        return $this->saleable()
            ->whereHas('flashDeals', fn ($q) => $q->where('flash_deals.id', $deal->id))
            ->with(['shop', 'category', 'brand'])
            ->limit(min(24, max(1, $limit)))
            ->get();
    }

    private function dealOfTheDay(): ?object
    {
        return Cache::remember('home:dotd:'.now()->toDateString(), self::CACHE_TTL, function () {
            $row = \Illuminate\Support\Facades\DB::table('deals_of_the_day')
                ->join('products', 'products.id', '=', 'deals_of_the_day.product_id')
                ->where('date', now()->toDateString())
                ->orderByRaw(\App\Models\DealOfTheDay::effectiveExpression().' desc')
                ->select('deals_of_the_day.*')
                ->first();

            if (! $row || ! $row->product_id) {
                return null;
            }

            $product = $this->saleable()->with(['shop', 'category', 'brand'])->find($row->product_id);

            $discount = $row->discount_type === 'flat' && (float) ($product?->price ?? 0) > 0
                ? (int) round(min(100, (float) $row->discount_value / (float) $product->price * 100))
                : (int) $row->discount_value;

            return $product ? ['product' => $product, 'discount' => $discount] : null;
        });
    }

    private function banners(string $position, int $limit)
    {
        if (! Schema::hasTable('banners')) {
            return collect();
        }

        return Cache::remember("home:banners:{$position}", self::CACHE_TTL, fn () => Banner::query()
            ->where('status', true)
            ->where('position', $position)
            ->orderBy('sort_order')
            ->limit($limit)
            ->get());
    }

    private function rootCategories(int $limit)
    {
        return Cache::remember('home:categories:'.$limit, self::CACHE_TTL, function () use ($limit) {
            $query = Category::query()->whereNull('parent_id')->where('status', true);

            return Schema::hasColumn('categories', 'sort_order')
                ? $query->orderBy('sort_order')->limit($limit)->get()
                : $query->limit($limit)->get();
        });
    }

    private function topBrands(int $limit)
    {
        return Cache::remember('home:brands:'.$limit, self::CACHE_TTL, function () use ($limit) {
            $ids = \Illuminate\Support\Facades\DB::table('products')
                ->where('status', 'approved')->where('published', true)
                ->whereNotNull('brand_id')
                ->groupBy('brand_id')
                ->orderByRaw('count(*) DESC')
                ->limit($limit)
                ->pluck('brand_id');

            return \App\Models\Brand::whereIn('id', $ids)->limit($limit)->get();
        });
    }

    private function topStores(int $limit)
    {
        return Cache::remember('home:shops:'.$limit, self::CACHE_TTL, function () use ($limit) {
            $ids = \Illuminate\Support\Facades\DB::table('products')
                ->where('status', 'approved')->where('published', true)
                ->groupBy('shop_id')
                ->orderByRaw('count(*) DESC')
                ->limit($limit)
                ->pluck('shop_id');

            return Shop::whereIn('id', $ids)->where('status', 'active')->limit($limit)->get();
        });
    }

    private function guides(int $limit)
    {
        if (! Schema::hasTable('blog_posts')) {
            return collect();
        }

        return Cache::remember('home:blog:'.$limit, self::CACHE_TTL, fn () => \App\Models\BlogPost::query()
            ->where('is_published', true)
            ->where('published_at', '<=', now())
            ->with('author')
            ->latest('published_at')
            ->limit($limit)
            ->get());
    }

    /** @return list<array{icon:string, title:string, text:string}> */
    private function trustItems(): array
    {
        return [
            ['icon' => 'shield-check', 'title' => 'Pembayaran Aman', 'text' => 'Transaksi diproses lewat payment gateway resmi dan terenkripsi end-to-end.'],
            ['icon' => 'store', 'title' => 'Ribuan Toko Terverifikasi', 'text' => 'Setiap seller melewati proses verifikasi sebelum bisa berjualan.'],
            ['icon' => 'refresh', 'title' => 'Retur Mudah', 'text' => 'Ajukan retur dalam 7 hari langsung dari halaman pesanan Anda.'],
            ['icon' => 'headset', 'title' => 'Bantuan Responsif', 'text' => 'Tim support dan seller merespons pertanyaan Anda lewat tiket dan chat.'],
        ];
    }
}
