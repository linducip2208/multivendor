<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V4;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\BrandResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ShopPublicResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Services\Api\ApiCatalog;
use App\Services\Api\ApiFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * v4 is a thin, strictly typed, public read API.
 *
 * Every response uses cursor pagination so a large catalogue never degrades into
 * deep offsets, and nothing here can be reached with a write credential.
 */
class PublicReadController extends ApiController
{
    public function products(Request $request, ApiFilter $filter): JsonResponse
    {
        $paginator = ApiCatalog::products()->cursor('id', 'desc')->paginate($this->publishedProducts(), $request);

        return $this->paged($paginator, ProductResource::collection($paginator->items())->resolve($request), 'OK', $filter, $request);
    }

    public function product(Request $request, string $slug): JsonResponse
    {
        $product = $this->publishedProducts()->with(['shop', 'category', 'brand', 'variants'])->where('slug', $slug)->firstOrFail();

        return $this->ok(new ProductResource($product));
    }

    public function categories(Request $request, ApiFilter $filter): JsonResponse
    {
        $paginator = ApiCatalog::categories()
            ->cursor('id', 'asc')
            ->paginate(Category::where('status', true)->whereNull('parent_id')->with('children'), $request);

        return $this->paged(
            $paginator,
            CategoryResource::collection($paginator->items())->resolve($request),
            'OK',
            $filter,
            $request
        );
    }

    public function category(Request $request, string $slug): JsonResponse
    {
        $category = Category::where('status', true)->where('slug', $slug)->with(['parent', 'children'])->firstOrFail();

        return $this->ok(new CategoryResource($category));
    }

    public function brands(Request $request, ApiFilter $filter): JsonResponse
    {
        $paginator = ApiCatalog::brands()
            ->cursor('id', 'asc')
            ->paginate(Brand::where('status', true)->withCount('products'), $request);

        return $this->paged(
            $paginator,
            BrandResource::collection($paginator->items())->resolve($request),
            'OK',
            $filter,
            $request
        );
    }

    public function stores(Request $request, ApiFilter $filter): JsonResponse
    {
        $paginator = ApiCatalog::stores()
            ->cursor('id', 'desc')
            ->paginate(Shop::where('status', 'active')->withCount('products'), $request);

        return $this->paged(
            $paginator,
            ShopPublicResource::collection($paginator->items())->resolve($request),
            'OK',
            $filter,
            $request
        );
    }

    public function store(Request $request, string $slug): JsonResponse
    {
        $shop = Shop::where('status', 'active')->where('slug', $slug)->withCount('products')->firstOrFail();

        return $this->ok(new ShopPublicResource($shop));
    }

    public function search(Request $request, ApiFilter $filter): JsonResponse
    {
        $data = $request->validate([
            'q' => 'required|string|min:2|max:100',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $limit = min(50, max(1, (int) ($data['limit'] ?? 20)));
        $term = $data['q'];

        return $this->ok([
            'query' => $term,
            'products' => ProductResource::collection(
                $this->publishedProducts()
                    ->where(static function (Builder $q) use ($term): void {
                        $q->where('name', 'like', '%'.$term.'%')
                            ->orWhere('sku', 'like', '%'.$term.'%')
                            ->orWhere('short_description', 'like', '%'.$term.'%');
                    })
                    ->limit($limit)
                    ->get()
            )->resolve($request),
            'categories' => CategoryResource::collection(
                Category::where('status', true)->where('name', 'like', '%'.$term.'%')->limit($limit)->get()
            )->resolve($request),
            'stores' => ShopPublicResource::collection(
                Shop::where('status', 'active')
                    ->where(static function (Builder $q) use ($term): void {
                        $q->where('name', 'like', '%'.$term.'%')->orWhere('city', 'like', '%'.$term.'%');
                    })
                    ->limit($limit)
                    ->get()
            )->resolve($request),
        ], 'OK', ['limit' => $limit, 'query' => $term]);
    }

    private function publishedProducts(): Builder
    {
        return Product::where('status', 'approved')
            ->where('published', true)
            ->whereHas('shop', static fn (Builder $shop) => $shop->where('status', 'active'))
            ->with(['shop', 'category', 'brand']);
    }
}
