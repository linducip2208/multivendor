<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Search\SearchManager;
use App\Search\SearchQuery;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function __construct(private readonly SearchManager $search) {}

    public function show(Request $request, Shop $shop)
    {
        abort_unless($shop->status === 'active', 404);

        $shop->loadCount('products');
        $this->loadFollowerCount($shop);

        $query = new SearchQuery(
            term: trim((string) $request->input('q', '')),
            shopIds: [$shop->id],
            categoryIds: $this->ids($request->input('category')),
            brandIds: $this->ids($request->input('brand')),
            minPrice: $request->filled('min_price') ? (float) $request->input('min_price') : null,
            maxPrice: $request->filled('max_price') ? (float) $request->input('max_price') : null,
            minRating: $request->filled('min_rating') ? (float) $request->input('min_rating') : null,
            inStockOnly: $request->boolean('in_stock'),
            sorts: [(string) $request->input('sort', 'relevance')],
            page: max(1, (int) $request->input('page', 1)),
        );

        $result = $this->search->search($query);

        return view('storefront.shop.show', [
            'shop' => $shop,
            'result' => $result,
            'products' => $result->paginator,
            'query' => $query,
        ]);
    }

    /** @return list<int> */
    private function ids(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('intval', $value)));
        }

        if (is_string($value) && $value !== '') {
            return array_values(array_filter(array_map('intval', explode(',', $value))));
        }

        return [];
    }

    private function loadFollowerCount(Shop $shop): void
    {
        try {
            $shop->setAttribute(
                'followers_count',
                \Illuminate\Support\Facades\DB::table('shop_followers')->where('shop_id', $shop->id)->count()
            );
        } catch (\Throwable) {
            $shop->setAttribute('followers_count', 0);
        }
    }
}
