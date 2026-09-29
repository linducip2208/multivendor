<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Services\RefundWorkflowService;
use App\Support\Currency;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RefundController extends Controller
{
    private const DECISIONS = ['approved', 'rejected'];

    public function index(Request $request): View
    {
        $shopId = (int) auth('vendor')->user()->shop_id;
        $status = VendorScopeRequest::enum($request, 'status', ['none', 'requested', 'approved', 'rejected', 'refunded']);

        $refunds = OrderItem::query()
            ->whereHas('order', fn ($query) => $query->where('shop_id', $shopId))
            ->when($status !== '', fn ($query) => $query->where('refund_status', $status))
            ->when(
                VendorScopeRequest::search($request) !== '',
                fn ($query) => $query->whereHas('order', fn ($order) => $order
                    ->where('shop_id', $shopId)
                    ->where('order_number', 'like', '%'.VendorScopeRequest::search($request).'%'))
            )
            ->with(['order:id,order_number,total,customer_id,shop_id', 'product:id,name,thumbnail', 'orderItem.product:id,name'])
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $counts = OrderItem::query()
            ->whereHas('order', fn ($query) => $query->where('shop_id', $shopId))
            ->selectRaw('refund_status, COUNT(*) as aggregate')
            ->groupBy('refund_status')
            ->pluck('aggregate', 'refund_status');

        return view('vendor.refund.index', [
            'refunds' => $refunds,
            'status' => $status,
            'search' => VendorScopeRequest::search($request),
            'counts' => $counts,
            'decisions' => self::DECISIONS,
            'currency' => Currency::config(),
        ]);
    }

    public function update(Request $request, OrderItem $item, RefundWorkflowService $refunds): RedirectResponse
    {
        abort_if((int) $item->order?->shop_id !== (int) auth('vendor')->user()->shop_id, 403);

        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $refunds->decide($item, (int) auth('vendor')->id(), $validated['status'], $validated['note'] ?? null);

        $labels = ['approved' => 'disetujui', 'rejected' => 'ditolak'];

        return back()->with('success', 'Refund '.$labels[$validated['status']].'.');
    }

    public function returns(Request $request): View
    {
        $shopId = (int) auth('vendor')->user()->shop_id;
        $status = VendorScopeRequest::enum($request, 'status', ['requested', 'approved', 'received', 'rejected', 'completed']);

        $returns = OrderReturn::query()
            ->whereHas('order', fn ($query) => $query->where('shop_id', $shopId))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->with(['order:id,order_number,total,shop_id,customer_id', 'orderItem:id,order_id,product_id,quantity,sub_total', 'orderItem.product:id,name'])
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $counts = DB::table('order_returns')
            ->join('orders', 'orders.id', '=', 'order_returns.order_id')
            ->where('orders.shop_id', $shopId)
            ->select('order_returns.status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('order_returns.status')
            ->pluck('aggregate', 'status');

        return view('vendor.returns.index', [
            'returns' => $returns,
            'status' => $status,
            'counts' => $counts,
            'value' => Money::sum($returns->getCollection()->pluck('amount')),
            'currency' => Currency::config(),
        ]);
    }
}
