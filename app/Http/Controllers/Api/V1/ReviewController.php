<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ReviewResource;
use App\Models\OrderItem;
use App\Models\ProductReview;
use App\Services\Api\ApiCatalog;
use App\Services\Api\ApiFilter;
use App\Services\Api\ErrorCodes;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $filter = ApiCatalog::reviews();
        $paginator = $filter->paginate(
            ProductReview::where('customer_id', $request->user()->id)
                ->with(['product:id,name,slug,thumbnail', 'customer']),
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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'order_item_id' => 'nullable|integer|exists:order_items,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
            'images' => 'nullable|array|max:5',
            'images.*' => 'string|max:255',
        ]);

        $item = $this->reviewableItem($request, $data);

        $review = DB::transaction(function () use ($data, $request, $item): ProductReview {
            $review = ProductReview::create([
                'product_id' => $data['product_id'],
                'customer_id' => $request->user()->id,
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
                'images' => $data['images'] ?? [],
                'status' => true,
            ]);

            $item->forceFill(['is_reviewed' => true])->save();

            return $review;
        });

        $this->markResource($request, 'product_review', (int) $review->id);

        return $this->created(new ReviewResource($review->load('customer')), 'Ulasan dikirim');
    }

    public function destroy(Request $request, int $review): JsonResponse
    {
        $model = ProductReview::where('customer_id', $request->user()->id)->whereKey($review)->first();

        $this->abortUnlessOwned($model !== null, 'review_not_found');

        $model->delete();

        return $this->ok(null, 'Ulasan dihapus');
    }

    private function reviewableItem(Request $request, array $data): OrderItem
    {
        $query = OrderItem::where('product_id', $data['product_id'])
            ->where('is_reviewed', false)
            ->whereHas('order', fn ($q) => $q
                ->where('customer_id', $request->user()->id)
                ->where('payment_status', 'paid')
                ->where('order_status', 'delivered'));

        if (! empty($data['order_item_id'])) {
            $query->whereKey($data['order_item_id']);
        }

        $item = $query->lockForUpdate()->first();

        if ($item === null) {
            return throw ApiResponse::error(
                ErrorCodes::FORBIDDEN,
                'Produk ini belum dapat direview: pesanan belum selesai atau sudah pernah direview.',
                403
            );
        }

        return $item;
    }
}
