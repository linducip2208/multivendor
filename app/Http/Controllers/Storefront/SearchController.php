<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Search\SearchAnalytics;
use App\Search\SearchManager;
use App\Search\SearchQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SearchController extends Controller
{
    public function __construct(private readonly SearchManager $search) {}

    public function index(Request $request)
    {
        $query = SearchQuery::fromRequest($request);
        $result = $this->search->search($query);

        SearchAnalytics::record($query, $result, $request, 'search');

        $breadcrumb = [['label' => $query->hasTerm() ? 'Pencarian' : 'Semua Produk', 'href' => null]];

        // Penanganan zero-result: saran otomatis dari analitik pencarian
        // (istilah populer yang mirip), dihitung hanya saat memang nol hasil.
        $saranNolHasil = $result->total === 0 && $query->hasTerm()
            ? SearchAnalytics::saranUntukNolHasil($query->term)
            : [];

        return view('storefront.search.index', [
            'result' => $result,
            'products' => $result->paginator,
            'query' => $query,
            'saranNolHasil' => $saranNolHasil,
            'suggestions' => $query->hasTerm() ? $this->suggestPayload($query->term, 5, $request) : [
                'products' => [], 'categories' => [], 'brands' => [], 'shops' => [], 'terms' => [],
            ],
            'categories' => Cache::remember('search:facets:categories', 300, fn () => Category::whereNull('parent_id')->where('status', true)->orderBy('sort_order')->limit(12)->get()),
            'brands' => Cache::remember('search:facets:brands', 300, fn () => Brand::where('status', true)->orderBy('name')->limit(40)->get()),
            'breadcrumbItems' => $breadcrumb,
            'metaTitle' => $query->hasTerm() ? 'Hasil pencarian "'.$query->term.'"' : 'Cari Produk',
            'metaDescription' => $query->hasTerm()
                ? 'Cari "'.$query->term.'" di '.config('app.name').'.'
                : 'Cari produk di '.config('app.name').'.',
            'canonicalUrl' => $query->hasTerm() ? route('search', ['q' => $query->term]) : route('search'),
            'jsonLd' => $this->searchSchema($result, $query),
        ]);
    }

    /**
     * Header autocomplete.
     *
     * The payload is catalogue-only, so it is safe to cache and to hand to a
     * shared cache. Nothing derived from the signed-in customer (cart, wishlist,
     * recent searches, personalised prices) is ever written to the cache.
     */
    public function suggest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:80'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $term = trim($validated['q']);
        $limit = (int) ($validated['limit'] ?? 6);
        $minLength = (int) config('search.suggest.min_length', 2);

        if (mb_strlen($term) < $minLength || ! (bool) config('search.suggest.enabled', true)) {
            return $this->emptySuggest();
        }

        $payload = $this->suggestPayload($term, $limit, $request);

        $etag = '"'.sha1(json_encode($payload) ?: 'empty').'"';

        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response()->json(null, 304)->header('ETag', $etag);
        }

        return response()->json($payload)
            ->header('ETag', $etag)
            ->header('Cache-Control', 'private, max-age='.(int) config('search.suggest.result_ttl', 60).', stale-while-revalidate=300');
    }

    private function suggestPayload(string $term, int $limit, Request $request): array
    {
        $ttl = (int) config('search.suggest.ttl', 180);
        $key = (string) config('search.suggest.cache_prefix', 'search:suggest:v2').':'.hash('sha256', mb_strtolower($term).'|'.$limit.'|'.$request->getLocale());

        $payload = cache()->remember($key, $ttl, function () use ($term, $limit): array {
            return $this->search->suggest($term, $limit);
        });

        if (! is_array($payload)) {
            $payload = ['products' => [], 'categories' => [], 'brands' => [], 'shops' => [], 'terms' => []];
        }

        return $payload + ['products' => [], 'categories' => [], 'brands' => [], 'shops' => [], 'terms' => []];
    }

    private function emptySuggest(): JsonResponse
    {
        return response()->json([
            'products' => [], 'categories' => [], 'brands' => [], 'shops' => [], 'terms' => [],
        ])->header('Cache-Control', 'no-store');
    }

    private function searchSchema($result, SearchQuery $query): array
    {
        $items = [];
        foreach (array_slice($result->items->all(), 0, 12) as $product) {
            $isModel = ! is_array($product);
            $slug = $isModel ? $product->slug : ($product['slug'] ?? null);
            $name = $isModel ? $product->name : ($product['name'] ?? null);

            if ($slug === null || $name === null) {
                continue;
            }

            $items[] = [
                '@type' => 'ListItem',
                'position' => count($items) + 1,
                'url' => route('products.show', $slug),
                'name' => $name,
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $query->hasTerm() ? 'Pencarian: '.$query->term : 'Semua produk',
            'url' => route('search', $query->toArray()),
            'mainEntity' => [
                '@type' => 'ItemList',
                'numberOfItems' => $result->total,
                'itemListElement' => $items,
            ],
        ];
    }
}
