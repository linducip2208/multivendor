<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\OrderResource;
use App\Http\Resources\RefundResource;
use App\Http\Resources\ShipmentResource;
use App\Models\Order;
use App\Services\Api\ApiCatalog;
use App\Services\Api\ApiFilter;
use App\Services\OrderWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $filter = ApiCatalog::orders();
        $paginator = $filter->paginate(
            Order::where('customer_id', $request->user()->id)->with(['shop', 'items.product', 'items.variant']),
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

    public function show(Request $request, int $order): JsonResponse
    {
        $model = $this->owned($request, $order);

        return $this->ok(new OrderResource($model->load(['shop', 'items.product', 'items.variant', 'statusHistory', 'shipments'])));
    }

    public function shipments(Request $request, int $order): JsonResponse
    {
        $model = $this->owned($request, $order);
        $filter = ApiCatalog::shipments();
        $paginator = $filter->paginate($model->shipments()->with(['order', 'provider']), $request);

        return $this->paged(
            $paginator,
            ShipmentResource::collection($paginator->getCollection()),
            'OK',
            $filter,
            $request
        );
    }

    public function refunds(Request $request, int $order): JsonResponse
    {
        $model = $this->owned($request, $order);
        $filter = ApiCatalog::refunds();
        $paginator = $filter->paginate($model->refunds()->with(['order', 'orderItem']), $request);

        return $this->paged(
            $paginator,
            RefundResource::collection($paginator->getCollection()),
            'OK',
            $filter,
            $request
        );
    }

    public function track(Request $request, string $number): JsonResponse
    {
        $order = Order::where('order_number', $number)
            ->where('customer_id', $request->user()->id)
            ->with(['shop', 'statusHistory', 'shipments'])
            ->first();

        $this->abortUnlessOwned($order !== null, 'order_not_found');

        return $this->ok([
            'order_number' => $order->order_number,
            'status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'tracking' => $order->shipping_tracking_id,
            'courier' => $order->shipping_method,
            'service' => $order->shipping_service,
            'estimated_delivery' => null,
            'shipments' => ShipmentResource::collection($order->shipments)->resolve($request),
            'history' => $order->statusHistory->map(fn ($row): array => [
                'id' => (int) $row->id,
                'status' => $row->status,
                'note' => $row->note,
                'at' => \App\Support\ApiResponse::iso($row->created_at),
            ])->all(),
        ]);
    }

    public function cancel(Request $request, int $order, OrderWorkflowService $workflow): JsonResponse
    {
        $model = $this->owned($request, $order);
        $data = $request->validate(['reason' => 'nullable|string|max:500']);

        $workflow->cancel($model, (int) $request->user()->id, $data['reason'] ?? 'Dibatalkan customer melalui API');
        $this->markResource($request, 'order', (int) $model->id);

        return $this->ok(new OrderResource($model->fresh(['shop', 'items.product', 'statusHistory'])), 'Pesanan dibatalkan');
    }

    public function confirmReceipt(Request $request, int $order, OrderWorkflowService $workflow): JsonResponse
    {
        $model = $this->owned($request, $order);
        $data = $request->validate(['note' => 'nullable|string|max:500']);

        $workflow->complete($model, (int) $request->user()->id, $data['note'] ?? 'Pesanan dikonfirmasi diterima customer');
        $this->markResource($request, 'order', (int) $model->id);

        return $this->ok(new OrderResource($model->fresh(['shop', 'items.product', 'statusHistory'])), 'Pesanan dikonfirmasi');
    }

    private function owned(Request $request, int $order): Order
    {
        $model = Order::where('customer_id', $request->user()->id)->whereKey($order)->first();

        $this->abortUnlessOwned($model !== null, 'order_not_found');

        return $model;
    }
}
