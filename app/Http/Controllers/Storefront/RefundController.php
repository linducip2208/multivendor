<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Refund;
use App\Services\RefundWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    private const INDEX_VIEW = 'storefront.refunds.index';

    private const SHOW_VIEW = 'storefront.refunds.show';

    public function index(Request $request): JsonResponse|RedirectResponse|\Illuminate\Contracts\View\View
    {
        $refunds = Refund::query()
            ->whereHas('order', fn ($query) => $query->where('customer_id', auth()->id()))
            ->with(['order.shop', 'orderItem.product'])
            ->latest()
            ->paginate(15);

        if ($request->expectsJson()) {
            return response()->json(['data' => $refunds]);
        }

        if (! view()->exists(self::INDEX_VIEW)) {
            return redirect()->route('orders.index')->with('info', 'Riwayat refund dapat dilihat pada detail pesanan.');
        }

        return view(self::INDEX_VIEW, compact('refunds'));
    }

    public function show(Request $request, Refund $refund): JsonResponse|RedirectResponse|\Illuminate\Contracts\View\View
    {
        $this->authorizeRefund($refund);

        $refund->load(['order.shop', 'orderItem.product', 'provider']);

        if ($request->expectsJson()) {
            return response()->json(['data' => $refund]);
        }

        if (! view()->exists(self::SHOW_VIEW)) {
            return redirect()->route('orders.show', $refund->order_id)->with('info', 'Detail refund ditampilkan pada halaman pesanan.');
        }

        return view(self::SHOW_VIEW, compact('refund'));
    }

    public function store(Request $request, Order $order, RefundWorkflowService $refunds): RedirectResponse
    {
        abort_unless((int) $order->customer_id === (int) auth()->id(), 403);

        $validated = $request->validate([
            'order_item_id' => 'required|integer|exists:order_items,id',
            'amount' => 'nullable|numeric|min:0.01',
            'reason' => ['required', 'string', 'in:'.implode(',', array_keys(\App\Models\OrderReturn::reasonLabels()))],
            'reason_detail' => ['nullable', 'string', 'min:10', 'max:2000'],
        ]);

        $item = $order->items()->whereKey($validated['order_item_id'])->firstOrFail();
        $detail = $validated['reason_detail'] ?? \App\Models\OrderReturn::reasonLabels()[$validated['reason']];

        $refunds->request($item, (int) auth()->id(), '['.$validated['reason'].'] '.$detail, $validated['amount'] ?? null);

        return back()->with('success', 'Permintaan refund telah dikirim ke penjual untuk ditinjau.');
    }

    private function authorizeRefund(Refund $refund): void
    {
        $customerId = (int) ($refund->order?->customer_id ?? 0);
        $vendorId = (int) ($refund->order?->shop?->vendor_id ?? 0);
        $isAdmin = auth('admin')->check() || auth()->user()?->isAdmin() === true;

        abort_unless(
            $customerId === (int) auth()->id() || $vendorId === (int) auth('vendor')->id() || $isAdmin,
            403,
        );
    }
}
