<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ReviewResource;
use App\Http\Resources\ShipmentResource;
use App\Http\Resources\VendorResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Transaction;
use App\Models\VendorWithdrawRequest;
use App\Services\Api\ApiCatalog;
use App\Services\Api\ApiFilter;
use App\Services\Api\ErrorCodes;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorPortalController extends ApiController
{
    public function profile(Request $request): JsonResponse
    {
        $vendor = $request->user();

        return $this->ok([
            'vendor' => new VendorResource($vendor->load('shop', 'wallet')),
            'shop' => $vendor->shop === null ? null : [
                'id' => (int) $vendor->shop->id,
                'name' => $vendor->shop->name,
                'slug' => $vendor->shop->slug,
                'status' => $vendor->shop->status,
                'is_on_vacation' => (bool) $vendor->shop->vacation_mode,
                'commission_type' => $vendor->shop->commission_type,
                'commission_value' => ApiResponse::money($vendor->shop->commission_value),
            ],
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $filter = ApiCatalog::products();
        $paginator = $filter->paginate(
            $this->shopQuery($request)->with(['category', 'brand', 'variants']),
            $request
        );

        return $this->paged(
            $paginator,
            ProductResource::collection($paginator->getCollection()),
            'OK',
            $filter,
            $request
        );
    }

    public function product(Request $request, int $product): JsonResponse
    {
        $model = Product::where('shop_id', $this->shopId($request))->whereKey($product)->first();

        $this->abortUnlessOwned($model !== null, 'product_not_found');

        return $this->ok(new ProductResource($model->load(['category', 'brand', 'variants'])));
    }

    public function updateProduct(Request $request, int $product): JsonResponse
    {
        $model = Product::where('shop_id', $this->shopId($request))->whereKey($product)->first();

        $this->abortUnlessOwned($model !== null, 'product_not_found');

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'short_description' => 'sometimes|nullable|string|max:500',
            'description' => 'sometimes|nullable|string',
            'price' => 'sometimes|numeric|min:0|max:999999999999',
            'special_price' => 'sometimes|nullable|numeric|min:0|max:999999999999',
            'current_stock' => 'sometimes|integer|min:0|max:10000000',
            'weight' => 'sometimes|nullable|integer|min:0|max:100000',
            'published' => 'sometimes|boolean',
            // DB enum is pending|approved|suspended; accept legacy
            // draft|rejected values and map them so vendors can never
            // self-approve or write an enum the DB rejects.
            'status' => 'sometimes|in:draft,pending,approved,rejected',
        ]);

        if (isset($data['status'])) {
            $data['status'] = match ($data['status']) {
                'draft' => 'pending',
                'rejected' => 'suspended',
                'approved' => $model->status === 'approved' ? 'approved' : 'pending',
                default => $data['status'],
            };
        }

        $model->fill($data)->save();
        $this->markResource($request, 'product', (int) $model->id);

        return $this->ok(new ProductResource($model->fresh(['category', 'brand'])), 'Produk diperbarui');
    }

    public function orders(Request $request): JsonResponse
    {
        $filter = ApiCatalog::orders();
        $paginator = $filter->paginate(
            Order::where('shop_id', $this->shopId($request))->with(['customer', 'items.product', 'items.variant']),
            $request
        );

        return $this->paged(
            $paginator,
            OrderResource::collection($paginator->getCollection()),
            'OK',
            $filter,
            $request
        );
    }

    public function order(Request $request, int $order): JsonResponse
    {
        $model = Order::where('shop_id', $this->shopId($request))->whereKey($order)->first();

        $this->abortUnlessOwned($model !== null, 'order_not_found');

        return $this->ok(new OrderResource($model->load(['customer', 'items.product', 'statusHistory', 'shipments'])));
    }

    public function shipments(Request $request): JsonResponse
    {
        $filter = ApiCatalog::shipments();
        $paginator = $filter->paginate(
            \App\Models\OrderShipment::whereIn('order_id', Order::where('shop_id', $this->shopId($request))->select('id'))
                ->with(['order', 'provider']),
            $request
        );

        return $this->paged(
            $paginator,
            ShipmentResource::collection($paginator->getCollection()),
            'OK',
            $filter,
            $request
        );
    }

    public function payouts(Request $request, ApiFilter $filter): JsonResponse
    {
        $shopId = $this->shopId($request);
        $paginator = ApiCatalog::walletTransactions()->paginate(
            Transaction::where('shop_id', $shopId)->where('status', 'success')->orderByDesc('id'),
            $request
        );

        $rows = $paginator->getCollection()->map(fn (Transaction $row): array => [
            'id' => (int) $row->id,
            'transaction_id' => $row->transaction_id,
            'order_id' => $row->order_id === null ? null : (int) $row->order_id,
            'amount' => ApiResponse::money($row->amount),
            'admin_commission' => ApiResponse::money($row->admin_commission),
            'vendor_amount' => ApiResponse::money($row->vendor_amount),
            'payment_method' => $row->payment_method,
            'status' => $row->status,
            'paid_at' => ApiResponse::iso($row->paid_at),
        ])->all();

        $meta = ApiResponse::metaFrom($paginator, [], null);
        $meta['summary'] = [
            'gross' => ApiResponse::money(Transaction::where('shop_id', $shopId)->where('status', 'success')->sum('amount')),
            'commission' => ApiResponse::money(Transaction::where('shop_id', $shopId)->where('status', 'success')->sum('admin_commission')),
            'net' => ApiResponse::money(Transaction::where('shop_id', $shopId)->where('status', 'success')->sum('vendor_amount')),
            'currency' => 'IDR',
        ];

        return $this->ok($rows, 'OK', $meta);
    }

    public function withdrawRequests(Request $request): JsonResponse
    {
        $rows = VendorWithdrawRequest::where('shop_id', $this->shopId($request))
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (VendorWithdrawRequest $row): array => [
                'id' => (int) $row->id,
                'amount' => ApiResponse::money($row->amount),
                'note' => $row->note ?? null,
                'status' => $row->status,
                'rejection_reason' => $row->rejection_reason ?? null,
                'approved_at' => ApiResponse::iso($row->approved_at),
                'completed_at' => ApiResponse::iso($row->completed_at),
                'created_at' => ApiResponse::iso($row->created_at),
            ])
            ->all();

        return $this->ok($rows);
    }

    public function reviews(Request $request): JsonResponse
    {
        $filter = ApiCatalog::reviews();
        $paginator = $filter->paginate(
            ProductReview::whereIn('product_id', Product::where('shop_id', $this->shopId($request))->select('id'))
                ->with(['product:id,name,slug', 'customer']),
            $request
        );

        return $this->paged(
            $paginator,
            ReviewResource::collection($paginator->getCollection())->resolve($request),
            'OK',
            $filter,
            $request
        );
    }

    public function inventory(Request $request): JsonResponse
    {
        $shopId = $this->shopId($request);

        return $this->ok([
            'products_count' => Product::where('shop_id', $shopId)->count(),
            'published_count' => Product::where('shop_id', $shopId)->where('published', true)->count(),
            'low_stock' => Product::where('shop_id', $shopId)
                ->whereColumn('current_stock', '<=', 'low_stock_threshold')
                ->count(),
            'out_of_stock' => Product::where('shop_id', $shopId)->where('current_stock', '<=', 0)->count(),
            'stock_value' => ApiResponse::money(
                DB::table('products')->where('shop_id', $shopId)->selectRaw('COALESCE(SUM(current_stock * price), 0) AS total')->value('total')
            ),
            'currency' => 'IDR',
        ]);
    }

    public function toggleVacation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vacation_mode' => 'required|boolean',
            'vacation_message' => 'nullable|string|max:255',
        ]);

        $shop = \App\Models\Shop::whereKey($this->shopId($request))->first();

        $this->abortUnlessOwned($shop !== null, 'shop_not_found');

        $shop->forceFill($data)->save();
        $this->markResource($request, 'shop', (int) $shop->id);

        return $this->ok([
            'is_on_vacation' => (bool) $shop->vacation_mode,
            'vacation_message' => $shop->vacation_message,
        ], 'Status toko diperbarui');
    }

    private function shopQuery(Request $request)
    {
        return Product::where('shop_id', $this->shopId($request));
    }

    private function shopId(Request $request): int
    {
        $shopId = $request->user()->shop?->id;

        if ($shopId === null) {
            abort(403, 'Akun vendor dengan toko aktif diperlukan.');
        }

        return (int) $shopId;
    }
}
