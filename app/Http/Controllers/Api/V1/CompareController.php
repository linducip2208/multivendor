<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ProductResource;
use App\Models\CompareList;
use App\Models\Product;
use App\Services\Api\ApiCatalog;
use App\Services\Api\ApiFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompareController extends ApiController
{
    private const MAX_ITEMS = 4;

    public function index(Request $request): JsonResponse
    {
        $filter = ApiCatalog::wishlist();
        $paginator = $filter->paginate(
            CompareList::where('customer_id', $request->user()->id)
                ->with(['product.shop', 'product.category', 'product.brand', 'product.variants']),
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
        $customerId = (int) $request->user()->id;

        $already = CompareList::where('customer_id', $customerId)->where('product_id', $productId)->exists();

        if ($already) {
            return $this->ok(['product_id' => $productId], 'Produk sudah ada di daftar perbandingan.');
        }

        $count = CompareList::where('customer_id', $customerId)->count();

        if ($count >= self::MAX_ITEMS) {
            return $this->ok(
                ['limit' => self::MAX_ITEMS, 'count' => $count],
                'Daftar perbandingan sudah penuh. Hapus salah satu item terlebih dahulu.',
                [],
                422
            );
        }

        CompareList::create(['customer_id' => $customerId, 'product_id' => $productId]);
        $this->markResource($request, 'compare_list', $productId);

        return $this->created(['product_id' => $productId], 'Produk ditambahkan ke daftar perbandingan');
    }

    public function destroy(Request $request, int $product): JsonResponse
    {
        $deleted = CompareList::where('customer_id', $request->user()->id)
            ->where('product_id', $product)
            ->delete();

        $this->abortUnlessOwned($deleted > 0);

        return $this->ok(['product_id' => $product], 'Produk dihapus dari daftar perbandingan');
    }

    public function clear(Request $request): JsonResponse
    {
        $count = CompareList::where('customer_id', $request->user()->id)->delete();

        return $this->ok(['removed' => $count], 'Daftar perbandingan dikosongkan');
    }

    public function compare(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_ids' => 'required|array|min:1|max:'.self::MAX_ITEMS,
            'product_ids.*' => 'integer|distinct|exists:products,id',
        ]);

        $products = Product::where('status', 'approved')
            ->where('published', true)
            ->whereIn('id', $data['product_ids'])
            ->with(['shop', 'brand', 'category', 'variants'])
            ->get()
            ->keyBy('id');

        $fields = ['price', 'effective_price', 'stock_available', 'rating_average', 'weight', 'product_type'];

        $columns = [];
        foreach ($data['product_ids'] as $id) {
            $product = $products->get($id);

            if ($product === null) {
                continue;
            }

            $row = ['product_id' => (int) $id];

            foreach ($fields as $field) {
                $row[$field] = $product->{$field};
            }

            $columns[] = $row;
        }

        return $this->ok([
            'fields' => $fields,
            'columns' => $columns,
            'products' => ProductResource::collection($products->values())->resolve($request),
        ]);
    }
}
