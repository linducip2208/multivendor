<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\BrandResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ReviewResource;
use App\Http\Resources\ShopPublicResource;
use App\Http\Resources\VariantResource;
use App\Http\Resources\VendorResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Shop;
use App\Models\User;
use App\Services\Api\ApiCatalog;
use App\Services\Api\ApiFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends ApiController
{
    public function products(Request $request): JsonResponse
    {
        $filter = ApiCatalog::products();
        $paginator = $filter->paginate($this->publishedQuery(), $request);

        return $this->paged(
            $paginator,
            ProductResource::collection($paginator->getCollection()),
            'OK',
            $filter,
            $request
        );
    }

    public function product(Request $request, string $slug): JsonResponse
    {
        $product = $this->publishedQuery()
            ->with(['shop', 'category', 'brand', 'variants'])
            ->where('slug', $slug)
            ->firstOrFail();

        Product::whereKey($product->id)->increment('view_count');

        return $this->ok(new ProductResource($product));
    }

    public function productVariants(Request $request, string $slug): JsonResponse
    {
        $product = $this->publishedQuery()->where('slug', $slug)->firstOrFail();
        $filter = ApiCatalog::variants();
        $paginator = $filter->paginate($product->variants()->with('product'), $request);

        return $this->paged(
            $paginator,
            VariantResource::collection($paginator->getCollection()),
            'OK',
            $filter,
            $request
        );
    }

    public function productReviews(Request $request, string $slug): JsonResponse
    {
        $product = $this->publishedQuery()->where('slug', $slug)->firstOrFail();
        $filter = ApiCatalog::reviews();
        $paginator = $filter->paginate(
            ProductReview::where('product_id', $product->id)->where('status', true)->with('customer'),
            $request
        );

        return $this->paged(
            $paginator,
            ReviewResource::collection($paginator->getCollection()),
            'OK',
            $filter,
            $request
        );
    }

    public function categories(Request $request): JsonResponse
    {
        $filter = ApiCatalog::categories();
        $paginator = $filter->paginate(
            Category::where('status', true)->whereNull('parent_id')->with('children'),
            $request
        );

        return $this->paged(
            $paginator,
            CategoryResource::collection($paginator->getCollection()),
            'OK',
            $filter,
            $request
        );
    }

    public function category(Request $request, string $slug): JsonResponse
    {
        $category = Category::where('status', true)
            ->where('slug', $slug)
            ->with(['parent', 'children'])
            ->firstOrFail();

        return $this->ok(new CategoryResource($category));
    }

    public function brands(Request $request): JsonResponse
    {
        $filter = ApiCatalog::brands();
        $paginator = $filter->paginate(
            Brand::where('status', true)->withCount('products'),
            $request
        );

        return $this->paged(
            $paginator,
            BrandResource::collection($paginator->getCollection()),
            'OK',
            $filter,
            $request
        );
    }

    public function brand(Request $request, string $slug): JsonResponse
    {
        return $this->ok(new BrandResource(
            Brand::where('status', true)->where('slug', $slug)->withCount('products')->firstOrFail()
        ));
    }

    public function stores(Request $request): JsonResponse
    {
        $filter = ApiCatalog::stores();
        $paginator = $filter->paginate(
            Shop::where('status', 'active')->withCount('products'),
            $request
        );

        return $this->paged(
            $paginator,
            ShopPublicResource::collection($paginator->getCollection()),
            'OK',
            $filter,
            $request
        );
    }

    public function store(Request $request, string $slug): JsonResponse
    {
        return $this->ok(new ShopPublicResource(
            Shop::where('status', 'active')->where('slug', $slug)->withCount('products')->firstOrFail()
        ));
    }

    public function storeProducts(Request $request, string $slug): JsonResponse
    {
        $shop = Shop::where('status', 'active')->where('slug', $slug)->firstOrFail();
        $filter = ApiCatalog::products();
        $paginator = $filter->paginate($this->publishedQuery()->where('shop_id', $shop->id), $request);

        return $this->paged(
            $paginator,
            ProductResource::collection($paginator->getCollection()),
            'OK',
            $filter,
            $request
        );
    }

    public function vendors(Request $request): JsonResponse
    {
        $filter = ApiCatalog::vendors();
        $paginator = $filter->paginate(
            User::where('role', 'vendor')->where('status', 'active')->with('shop'),
            $request
        );

        return $this->paged(
            $paginator,
            VendorResource::collection($paginator->getCollection()),
            'OK',
            $filter,
            $request
        );
    }

    public function vendor(Request $request, int $vendor): JsonResponse
    {
        $model = User::where('role', 'vendor')
            ->where('status', 'active')
            ->whereKey($vendor)
            ->with('shop')
            ->first();

        $this->abortUnlessOwned($model !== null, 'vendor_not_found');

        return $this->ok(new VendorResource($model));
    }

    private function publishedQuery(): Builder
    {
        return Product::where('status', 'approved')
            ->where('published', true)
            ->whereHas('shop', static fn (Builder $shop) => $shop->where('status', 'active'))
            ->with(['shop', 'category', 'brand']);
    }
}
