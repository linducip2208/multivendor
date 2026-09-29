<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ProductResource;
use App\Models\Wishlist;
use App\Services\Api\ApiCatalog;
use App\Services\Api\ApiFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $filter = ApiCatalog::wishlist();
        $paginator = $filter->paginate(
            Wishlist::where('customer_id', $request->user()->id)
                ->with(['product.shop', 'product.category', 'product.brand']),
            $request
        );

        return $this->paged(
            $paginator,
            ProductResource::collection($paginator->getCollection()->pluck('product')->filter()),
            'OK',
            $filter,
            $request
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['product_id' => 'required|integer|exists:products,id']);
        $productId = (int) $data['product_id'];

        $exists = Wishlist::where('customer_id', $request->user()->id)
            ->where('product_id', $productId)
            ->exists();

        if ($exists) {
            return $this->ok(['product_id' => $productId], 'Produk sudah ada di wishlist.');
        }

        Wishlist::create(['customer_id' => $request->user()->id, 'product_id' => $productId]);
        $this->markResource($request, 'wishlist', $productId);

        return $this->created(['product_id' => $productId], 'Produk ditambahkan ke wishlist');
    }

    public function destroy(Request $request, int $product): JsonResponse
    {
        $deleted = Wishlist::where('customer_id', $request->user()->id)
            ->where('product_id', $product)
            ->delete();

        $this->abortUnlessOwned($deleted > 0);

        return $this->ok(['product_id' => $product], 'Produk dihapus dari wishlist');
    }

    public function ids(Request $request): JsonResponse
    {
        return $this->ok(
            Wishlist::where('customer_id', $request->user()->id)
                ->orderBy('id')
                ->limit(500)
                ->pluck('product_id')
                ->map(static fn ($id): int => (int) $id)
                ->all()
        );
    }

    public function clear(Request $request): JsonResponse
    {
        return $this->ok(
            ['removed' => Wishlist::where('customer_id', $request->user()->id)->delete()],
            'Wishlist dikosongkan'
        );
    }
}
