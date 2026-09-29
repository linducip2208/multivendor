<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Search\SearchQuery;
use App\Support\Currency;
use Illuminate\Http\Request;

/**
 * Catalogue browsing: products, categories, brands, stores and merchandising
 * landing pages. Every page renders real inventory and emits valid
 * structured data; nothing here generates thin content.
 */
class CatalogController extends Controller
{
    public function products(Request $request)
    {
        $query = SearchQuery::fromRequest($request);
        $result = $this->searcher()->search($query);

        return view('storefront.products.index', [
            'result' => $result,
            'products' => $result->paginator,
            'query' => $query,
            'categories' => $this->rootCategories(),
            'brands' => $this->brands(),
            'canonicalUrl' => $this->canonicalFor($request, 'products.index'),
        ]);
    }

    public function product(Request $request, string $slug)
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->where('status', 'approved')
            ->where('published', true)
            ->whereHas('shop', fn ($q) => $q->where('status', 'active'))
            ->with(['shop', 'category', 'brand', 'variants', 'attributes', 'reviews' => fn ($q) => $q->where('status', 'approved')->latest()->limit(20)])
            ->firstOrFail();

        Product::whereKey($product->id)->increment('view_count');

        $ratingBreakdown = $this->ratingBreakdown($product);
        $similar = $this->similar($product);
        $related = $this->related($product);
        $boughtTogether = $this->frequentlyBoughtTogether($product);
        $shippingEstimate = $this->shippingEstimate($product);
        $breadcrumb = $this->productBreadcrumb($product);

        $variantPayload = [
            'base_price_label' => Currency::format($product->getEffectivePrice()),
            'base_stock' => (int) $product->current_stock,
            'base_max_qty' => (int) ($product->max_qty ?: 99),
            'base_stock_label' => $this->stockLabel((int) $product->current_stock, (int) $product->low_stock_threshold),
            'variants' => $product->variants->map(fn ($v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'price_label' => Currency::format($v->getEffectivePrice()),
                'stock' => (int) $v->stock,
                'attributes' => is_array($v->variant_attributes) ? $v->variant_attributes : [],
            ])->values()->all(),
        ];

        return view('storefront.products.show', [
            'product' => $product,
            'similar' => $similar,
            'related' => $related,
            'boughtTogether' => $boughtTogether,
            'ratingBreakdown' => $ratingBreakdown,
            'shippingEstimate' => $shippingEstimate,
            'breadcrumbItems' => $breadcrumb,
            'variantPayload' => $variantPayload,
            'metaTitle' => $product->meta_title ?: $product->name,
            'metaDescription' => $product->meta_description
                ?: \Illuminate\Support\Str::limit(strip_tags((string) ($product->short_description ?: $product->description)), 155),
            'metaImage' => $product->meta_image ?: ($product->thumbnail ? url('img/'.ltrim($product->thumbnail, '/')) : null),
            'canonicalUrl' => route('products.show', $product->slug),
            'metaRobots' => $request->query('preview') && auth()->check() ? 'noindex, nofollow' : null,
            'ogType' => 'product',
            'productPrice' => $product->getEffectivePrice(),
            'jsonLd' => $this->productSchema($product, $breadcrumb),
        ]);
    }

    public function category(Request $request, string $slug)
    {
        $category = Category::where('slug', $slug)->where('status', true)->firstOrFail();

        $query = SearchQuery::fromRequest($request);
        $query = new SearchQuery(
            $query->term,
            array_values(array_unique(array_merge([$category->id], $this->descendantIds($category->id)))),
            $query->brandIds, $query->shopIds, $query->minPrice, $query->maxPrice,
            $query->minRating, $query->inStockOnly, $query->destination,
            $query->attributeFilters, $query->sorts, $query->page, $query->perPage,
            $query->typoTolerance, $query->driver,
        );

        $result = $this->searcher()->search($query);

        $breadcrumb = array_merge($this->categoryBreadcrumb($category), [[
            'label' => $category->name,
            'href' => route('categories.show', $category->slug),
        ]]);

        return view('storefront.categories.show', [
            'category' => $category,
            'children' => Category::where('parent_id', $category->id)->where('status', true)->orderBy('sort_order')->get(),
            'result' => $result,
            'products' => $result->paginator,
            'query' => $query,
            'brands' => $this->brands(),
            'breadcrumbItems' => $breadcrumb,
            'metaTitle' => $category->meta_title ?: $category->name,
            'metaDescription' => $category->meta_description ?: \Illuminate\Support\Str::limit(strip_tags((string) $category->description), 155),
            'metaImage' => $category->image ? url('img/'.ltrim($category->image, '/')) : null,
            'canonicalUrl' => route('categories.show', $category->slug),
            'jsonLd' => $this->breadcrumbsSchema($breadcrumb, route('categories.show', $category->slug)),
        ]);
    }

    public function categoryIndex()
    {
        $categories = $this->rootCategories()->load(['children' => fn ($q) => $q->where('status', true)->orderBy('sort_order')]);

        $breadcrumb = [['label' => 'Kategori', 'href' => null]];

        return view('storefront.categories.index', [
            'categories' => $categories,
            'breadcrumbItems' => $breadcrumb,
            'metaTitle' => 'Kategori Produk',
            'metaDescription' => 'Jelajahi seluruh kategori produk yang tersedia di '.config('app.name').'.',
            'canonicalUrl' => route('categories.index'),
            'jsonLd' => $this->breadcrumbsSchema($breadcrumb, route('categories.index')),
        ]);
    }

    public function brand(Request $request, string $slug)
    {
        $brand = Brand::where('slug', $slug)->where('status', true)->firstOrFail();

        $query = SearchQuery::fromRequest($request);
        $query = new SearchQuery(
            $query->term, $query->categoryIds, [$brand->id], $query->shopIds,
            $query->minPrice, $query->maxPrice, $query->minRating, $query->inStockOnly,
            $query->destination, $query->attributeFilters, $query->sorts, $query->page,
            $query->perPage, $query->typoTolerance, $query->driver,
        );

        $result = $this->searcher()->search($query);
        $breadcrumb = [['label' => 'Brand', 'href' => route('brands.index')], ['label' => $brand->name, 'href' => route('brands.show', $brand->slug)]];

        return view('storefront.brands.show', [
            'brand' => $brand,
            'result' => $result,
            'products' => $result->paginator,
            'query' => $query,
            'breadcrumbItems' => $breadcrumb,
            'metaTitle' => $brand->meta_title ?: "Produk {$brand->name}",
            'metaDescription' => $brand->meta_description ?: "Belanja produk bermerek {$brand->name} di ".config('app.name').'.',
            'metaImage' => $brand->logo ? url('img/'.ltrim($brand->logo, '/')) : null,
            'canonicalUrl' => route('brands.show', $brand->slug),
            'jsonLd' => $this->breadcrumbsSchema($breadcrumb, route('brands.show', $brand->slug)),
        ]);
    }

    public function brandIndex()
    {
        $brands = Brand::query()
            ->where('status', true)
            ->withCount(['products' => fn ($q) => $q->where('status', 'approved')->where('published', true)])
            ->having('products_count', '>', 0)
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->paginate(48);

        $breadcrumb = [['label' => 'Brand', 'href' => null]];

        return view('storefront.brands.index', [
            'brands' => $brands,
            'breadcrumbItems' => $breadcrumb,
            'metaTitle' => 'Merek / Brand',
            'metaDescription' => 'Daftar merek yang tersedia di '.config('app.name').'.',
            'canonicalUrl' => route('brands.index'),
            'jsonLd' => $this->breadcrumbsSchema($breadcrumb, route('brands.index')),
        ]);
    }

    public function storeIndex(Request $request)
    {
        $shops = Shop::query()
            ->where('status', 'active')
            ->withCount(['products' => fn ($q) => $q->where('status', 'approved')->where('published', true)])
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$this->escapeLike($request->input('q')).'%'))
            ->when($request->filled('city'), fn ($q) => $q->where('city', $request->input('city')))
            ->having('products_count', '>', 0)
            ->orderByDesc('rating_average')
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        $breadcrumb = [['label' => 'Toko', 'href' => null]];

        return view('storefront.stores.index', [
            'shops' => $shops,
            'cities' => Shop::where('status', 'active')->whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
            'breadcrumbItems' => $breadcrumb,
            'metaTitle' => 'Semua Toko',
            'metaDescription' => 'Temukan toko terbaik di '.config('app.name').' dan Belanja langsung dari Hundreds seller.',
            'canonicalUrl' => route('stores.index'),
            'jsonLd' => $this->breadcrumbsSchema($breadcrumb, route('stores.index')),
        ]);
    }

    /* ---- merchandising landing pages ---- */

    public function deals(Request $request)
    {
        return $this->landing($request, 'products.index', [
            'discount_max' => \App\Models\SystemSetting::get('deals_min_discount', 10),
        ], 'deals', 'Promo & Deals', 'Temukan produk dengan diskon terbesar di '.config('app.name').'.');
    }

    public function flashSale(Request $request)
    {
        $deal = \App\Models\FlashDeal::query()
            ->where('status', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->orderByBestDiscount()
            ->with('products.shop')
            ->first();

        $breadcrumb = [['label' => 'Flash Sale', 'href' => null]];

        return view('storefront.flash-sale', [
            'deal' => $deal,
            'products' => $deal?->products->filter()->take(24) ?? collect(),
            'breadcrumbItems' => $breadcrumb,
            'metaTitle' => 'Flash Sale',
            'metaDescription' => 'Flash sale '.config('app.name').' — harga terbaik hanya dalam waktu terbatas.',
            'canonicalUrl' => route('flash-sale'),
            'jsonLd' => $this->breadcrumbsSchema($breadcrumb, route('flash-sale')),
        ]);
    }

    public function newArrivals(Request $request)
    {
        return $this->landing($request, 'products.index', [], 'new-arrivals', 'Produk Terbaru',
            'Produkbartu yang baru saja masuk di '.config('app.name').'.');
    }

    public function bestSellers(Request $request)
    {
        return $this->landing($request, 'products.index', ['sort' => 'popular'], 'best-sellers', 'Produk Terlaris',
            'Produk paling banyak dibeli di '.config('app.name').'.');
    }

    /* ------------------------------------------------------------------ */

    private function landing(Request $request, string $ignore, array $forced, string $routeName, string $title, string $description)
    {
        $request->merge($forced);
        $query = SearchQuery::fromRequest($request);
        $result = $this->searcher()->search($query);

        $breadcrumb = [['label' => $title, 'href' => null]];

        return view('storefront.listing', [
            'result' => $result,
            'products' => $result->paginator,
            'query' => $query,
            'categories' => $this->rootCategories(),
            'brands' => $this->brands(),
            'breadcrumbItems' => $breadcrumb,
            'heading' => $title,
            'lede' => $description,
            'canonicalUrl' => route($routeName),
            'metaTitle' => $title,
            'metaDescription' => $description,
            'jsonLd' => $this->breadcrumbsSchema($breadcrumb, route($routeName)),
        ]);
    }

    private function searcher(): \App\Search\SearchManager
    {
        return app(\App\Search\SearchManager::class);
    }

    private function rootCategories()
    {
        return \Illuminate\Support\Facades\Cache::remember('nav:root_categories', 3600, fn () => Category::query()
            ->whereNull('parent_id')
            ->where('status', true)
            ->orderBy('sort_order')
            ->limit(12)
            ->get());
    }

    private function brands()
    {
        return \Illuminate\Support\Facades\Cache::remember('nav:brands', 3600, fn () => Brand::query()
            ->where('status', true)
            ->whereHas('products', fn ($q) => $q->where('status', 'approved')->where('published', true))
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->limit(40)
            ->get());
    }

    private function descendantIds(int $categoryId): array
    {
        $children = Category::where('parent_id', $categoryId)->pluck('id')->all();
        if ($children === []) {
            return [];
        }

        return array_values(array_unique(array_merge(
            $children,
            Category::whereIn('parent_id', $children)->pluck('id')->all()
        )));
    }

    private function similar(Product $product)
    {
        return Product::query()
            ->where('status', 'approved')->where('published', true)
            ->where('id', '!=', $product->id)
            ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))
            ->when($product->brand_id, fn ($q) => $q->where('brand_id', $product->brand_id))
            ->with(['shop', 'category', 'brand'])
            ->orderByDesc('rating_average')
            ->limit(8)
            ->get();
    }

    private function related(Product $product)
    {
        $ids = \Illuminate\Support\Facades\DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.product_id', '!=', $product->id)
            ->whereIn('order_items.product_id', OrderItem::where('product_id', $product->id)->select('product_id'))
            ->groupBy('order_items.product_id')
            ->orderByDesc(\Illuminate\Support\Facades\DB::raw('count(*)'))
            ->limit(8)
            ->pluck('order_items.product_id');

        if ($ids->isEmpty()) {
            return $this->similar($product);
        }

        return Product::query()
            ->whereIn('id', $ids)
            ->where('status', 'approved')->where('published', true)
            ->with(['shop', 'category', 'brand'])
            ->get();
    }

    private function frequentlyBoughtTogether(Product $product)
    {
        $ids = OrderItem::where('product_id', $product->id)
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id')
            ->orderByDesc(\Illuminate\Support\Facades\DB::raw('count(*)'))
            ->limit(4)
            ->pluck('order_items.product_id')
            ->reject(fn ($id) => (int) $id === (int) $product->id)
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->whereIn('id', $ids)
            ->where('status', 'approved')->where('published', true)
            ->with(['shop', 'category', 'brand'])
            ->get();
    }

    /** @return array<string, int> */
    private function ratingBreakdown(Product $product): array
    {
        try {
            $rows = \Illuminate\Support\Facades\DB::table('product_reviews')
                ->where('product_id', $product->id)
                ->where('status', 'approved')
                ->selectRaw('rating, count(*) as aggregate')
                ->groupBy('rating')
                ->pluck('aggregate', 'rating');

            $breakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
            foreach ($rows as $rating => $count) {
                $breakdown[(int) $rating] = (int) $count;
            }

            return $breakdown;
        } catch (\Throwable) {
            return [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        }
    }

    private function shippingEstimate(Product $product): ?string
    {
        $days = (int) \App\Models\SystemSetting::get('shipping_estimate_days', 3);

        return $days > 0 ? now()->addDays($days)->format('d M Y') : null;
    }

    /** @return list<array{label: string, href: ?string}> */
    private function productBreadcrumb(Product $product): array
    {
        $items = [['label' => 'Beranda', 'href' => route('home')]];

        if ($product->category) {
            foreach ($this->categoryBreadcrumb($product->category) as $crumb) {
                $items[] = $crumb;
            }
            $items[] = ['label' => $product->category->name, 'href' => route('categories.show', $product->category->slug)];
        }

        if ($product->brand) {
            $items[] = ['label' => $product->brand->name, 'href' => route('brands.show', $product->brand->slug)];
        }

        $items[] = ['label' => $product->name, 'href' => null];

        return $items;
    }

    /** @return list<array{label: string, href: ?string}> */
    private function categoryBreadcrumb(Category $category): array
    {
        $items = [['label' => 'Kategori', 'href' => route('categories.index')]];
        $parent = $category->parent;

        while ($parent) {
            array_unshift($items, ['label' => $parent->name, 'href' => route('categories.show', $parent->slug)]);
            $parent = $parent->parent;
        }

        return $items;
    }

    private function stockLabel(int $stock, int $threshold): string
    {
        return match (true) {
            $stock <= 0 => 'Stok habis',
            $stock <= $threshold => "Tersisa {$stock} unit",
            default => "Stok {$stock}",
        };
    }

    private function canonicalFor(Request $request, string $routeName): string
    {
        // Faceted/sorted/paginated variants all canonicalise to the clean URL so
        // the catalogue does not compete with itself in search results.
        return route($routeName);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }

    /* ---- structured data ---- */

    private function productSchema(Product $product, array $breadcrumb): array
    {
        $price = $product->getEffectivePrice();
        $url = route('products.show', $product->slug);

        $node = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'url' => $url,
            'sku' => $product->sku ?: (string) $product->id,
            'description' => \Illuminate\Support\Str::limit(strip_tags((string) ($product->short_description ?: $product->description)), 500),
            'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
            'offers' => [
                '@type' => 'Offer',
                'url' => $url,
                'priceCurrency' => Currency::config()['code'],
                'price' => number_format($price, Currency::config()['decimals'], '.', ''),
                'availability' => ((int) $product->current_stock > 0)
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/'.ucfirst((string) ($product->condition ?: 'new')).'Condition',
                'seller' => ['@type' => 'Organization', 'name' => $product->shop?->name ?? config('app.name')],
            ],
        ];

        $images = $product->images;
        if (is_string($images)) {
            $images = json_decode($images, true) ?: [];
        }
        $images = array_values(array_filter((array) $images));

        if ($product->thumbnail) {
            array_unshift($images, $product->thumbnail);
        }
        if ($images !== []) {
            $node['image'] = array_map(fn ($i) => str_starts_with($i, 'http') ? $i : url('img/'.ltrim($i, '/')), $images);
        }

        if ((int) $product->rating_count > 0 && (float) $product->rating_average > 0) {
            $node['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => number_format((float) $product->rating_average, 1, '.', ''),
                'reviewCount' => (int) $product->rating_count,
                'bestRating' => 5,
                'worstRating' => 1,
            ];
        }

        // Variant Offer hook: when the product has priced variants, expose one
        // Offer per variant (base offer first) so crawlers see every purchasable
        // option. Single-variant-less products keep the original single Offer.
        try {
            $variantOffers = $this->variantOffers($product, $url, $node['offers']);
            if ($variantOffers !== []) {
                $node['offers'] = count($variantOffers) > 1 ? $variantOffers : $node['offers'];
            }
        } catch (\Throwable) {
            // Never break the base Product node because of variant expansion.
        }

        return $node + $this->breadcrumbsSchema($breadcrumb, $url);
    }

    /**
     * Build one Offer per variant (base offer first). Returns [] when the
     * product has no priced variants, letting the caller keep the single Offer.
     *
     * @param  array<string, mixed>  $baseOffer
     * @return list<array<string, mixed>>
     */
    private function variantOffers(Product $product, string $url, array $baseOffer): array
    {
        $variants = $product->relationLoaded('variants')
            ? $product->variants
            : $product->variants()->get(['id', 'sku', 'price', 'special_price', 'discount_start', 'discount_end', 'stock']);

        if ($variants->isEmpty()) {
            return [];
        }

        $offers = [$baseOffer];

        foreach ($variants as $variant) {
            $price = $variant->getEffectivePrice();
            if ($price <= 0) {
                continue;
            }

            $offer = [
                '@type' => 'Offer',
                'url' => $url.'?variant='.$variant->id,
                'priceCurrency' => $baseOffer['priceCurrency'],
                'price' => number_format($price, Currency::config()['decimals'], '.', ''),
                'availability' => ((int) $variant->stock > 0)
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'itemCondition' => $baseOffer['itemCondition'],
                'seller' => $baseOffer['seller'],
            ];

            if ($variant->sku) {
                $offer['sku'] = (string) $variant->sku;
            }

            $offers[] = $offer;
        }

        return count($offers) > 1 ? $offers : [];
    }

    private function breadcrumbsSchema(array $items, string $url): array
    {
        $nodes = [];
        foreach (array_values($items) as $i => $item) {
            $nodes[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['label'],
                'item' => $item['href'] ?? $url,
            ];
        }

        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $nodes];
    }
}
