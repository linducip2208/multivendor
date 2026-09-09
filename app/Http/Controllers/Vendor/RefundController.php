<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Services\RefundWorkflowService;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function index(Request $request)
    {
        $shop = auth('vendor')->user()->shop;
        $query = OrderItem::whereHas('order', fn ($q) => $q->where('shop_id', $shop->id))
            ->where('refund_status', '!=', 'none')->with(['order', 'product'])->latest();
        if ($request->filled('status')) {
            $query->where('refund_status', $request->status);
        }
        $refunds = $query->paginate(15);

        return view('vendor.refund.index', compact('refunds'));
    }

    public function update(Request $request, OrderItem $item, RefundWorkflowService $refunds)
    {
        $shop = auth('vendor')->user()->shop;
        if ($item->order->shop_id !== $shop->id) {
            abort(403);
        }

        $validated = $request->validate(['status' => 'required|in:approved,rejected', 'note' => 'nullable|string|max:2000']);
        $refunds->decide($item, auth('vendor')->id(), $validated['status'], $validated['note'] ?? null);

        $labels = ['approved' => 'disetujui', 'rejected' => 'ditolak'];

        return back()->with('success', 'Refund '.($labels[$validated['status']] ?? $validated['status']));
    }
}
