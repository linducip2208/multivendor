<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ShopPublicResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Services\Api\ApiCatalog;
use App\Services\Api\ApiFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends ApiController
{
    public function __invoke(Request $request, ApiFilter $filter): JsonResponse
    {
        $data = $request->validate([
            'q' => 'required|string|min:2|max:100',
            'type' => 'nullable|in:all,products,categories,stores',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $type = $data['type'] ?? 'all';
        $perPage = min(50, max(1, (int) ($data['per_page'] ?? 10)));
        $term = $data['q'];
        $results = [];

        if ($type === 'all' || $type === 'products') {
            $results['products'] = ProductResource::collection(
                $this->products($term)->limit($perPage)->get()
            )->resolve($request);
        }

        if ($type === 'all' || $type === 'categories') {
            $results['categories'] = CategoryResource::collection(
                Category::where('status', true)->where('name', 'like', '%'.$term.'%')->limit($perPage)->get()
            )->resolve($request);
        }

        if ($type === 'all' || $type === 'stores') {
            $results['stores'] = ShopPublicResource::collection(
                Shop::where('status', 'active')
                    ->where(static function (Builder $q) use ($term): void {
                        $q->where('name', 'like', '%'.$term.'%')->orWhere('city', 'like', '%'.$term.'%');
                    })
                    ->withCount('products')
                    ->limit($perPage)
                    ->get()
            )->resolve($request);
        }

        $meta = ApiCatalog::search()->meta($request);
        $meta['q'] = $term;
        $meta['type'] = $type;
        $meta['per_page'] = $perPage;

        return $this->ok($results, 'OK', $meta);
    }

    private function products(string $term): Builder
    {
        return Product::where('status', 'approved')
            ->where('published', true)
            ->whereHas('shop', static fn (Builder $shop) => $shop->where('status', 'active'))
            ->where(static function (Builder $q) use ($term): void {
                $q->where('name', 'like', '%'.$term.'%')
                    ->orWhere('sku', 'like', '%'.$term.'%')
                    ->orWhere('short_description', 'like', '%'.$term.'%');
            })
            ->with(['shop', 'category', 'brand'])
            ->latest('id');
    }
}
