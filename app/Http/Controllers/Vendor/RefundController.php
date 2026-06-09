<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function index(Request $request)
    {
        $shop = auth('vendor')->user()->shop;
        $query = OrderItem::whereHas('order', fn($q) => $q->where('shop_id', $shop->id))
            ->where('refund_status', '!=', 'none')->with(['order', 'product'])->latest();
        if ($request->filled('status')) $query->where('refund_status', $request->status);
        $refunds = $query->paginate(15);
        return view('vendor.refund.index', compact('refunds'));
    }

    public function update(Request $request, OrderItem $item)
    {
        $shop = auth('vendor')->user()->shop;
        if ($item->order->shop_id !== $shop->id) abort(403);

        $request->validate(['status' => 'required|in:approved,rejected']);
        $item->update(['refund_status' => $request->status]);

        $labels = ['approved' => 'disetujui', 'rejected' => 'ditolak'];
        return back()->with('success', 'Refund ' . ($labels[$request->status] ?? $request->status));
    }
}
