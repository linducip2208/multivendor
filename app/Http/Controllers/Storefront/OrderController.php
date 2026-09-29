<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\RefundWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::where('customer_id', auth()->id())
            ->with(['shop', 'items.product'])
            ->latest()
            ->paginate(10);

        return view('storefront.orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        if ($order->customer_id !== auth()->id()) {
            abort(403);
        }

        $order->load(['shop', 'items.product', 'items.variant', 'statusHistory', 'transaction', 'refunds', 'returns']);

        return view('storefront.orders.show', [
            'order' => $order,
            'invoiceNumber' => $order->invoiceNumber(),
            'returnReasons' => \App\Models\OrderReturn::reasonLabels(),
            'labelSender' => $order->shippingLabelSender(),
            'codOtpRequired' => $order->codOtpRequired(),
            'codOtpVerified' => $order->codOtpVerified(),
            // ADITIF slot: label siap tampil + daftar jam valid untuk form
            // penjadwalan ulang (di-render bila view menyediakannya).
            'deliverySlot' => \App\Services\OrderWorkflowService::deliverySlotLabel($order),
            'deliverySlotTimes' => \App\Services\OrderWorkflowService::DELIVERY_SLOT_TIMES,
        ]);
    }

    /**
     * ADITIF slot: ubah jadwal pengiriman milik pelanggan sendiri.
     * Kepemilikan + validasi slot ditegakkan; penulisan atomik +
     * idempoten di OrderWorkflowService::scheduleDeliverySlot().
     * (Pengkabelan route diserahkan ke pemilik routes/*.php.)
     */
    public function updateSlot(Request $request, Order $order, \App\Services\OrderWorkflowService $workflow): RedirectResponse
    {
        if ($order->customer_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'delivery_slot_date' => 'required|date|after_or_equal:today',
            'delivery_slot_time' => 'nullable|string|in:'.implode(',', \App\Services\OrderWorkflowService::DELIVERY_SLOT_TIMES),
            'delivery_slot_label' => 'nullable|string|max:120',
        ]);

        $workflow->scheduleDeliverySlot(
            $order,
            (string) $validated['delivery_slot_date'],
            $validated['delivery_slot_time'] ?? null,
            auth()->id(),
            isset($validated['delivery_slot_label']) && trim((string) $validated['delivery_slot_label']) !== ''
                ? trim((string) $validated['delivery_slot_label'])
                : null,
        );

        return back()->with('success', 'Jadwal pengiriman diperbarui.');
    }

    public function requestRefund(Request $request, OrderItem $orderItem, RefundWorkflowService $refunds): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:'.implode(',', array_keys(\App\Models\OrderReturn::reasonLabels()))],
            'reason_detail' => ['nullable', 'string', 'min:10', 'max:2000'],
        ]);

        $detail = $validated['reason_detail'] ?? \App\Models\OrderReturn::reasonLabels()[$validated['reason']];
        $refunds->request($orderItem, auth()->id(), '['.$validated['reason'].'] '.$detail);

        return back()->with('success', 'Permintaan refund telah dikirim ke penjual untuk ditinjau.');
    }
}
