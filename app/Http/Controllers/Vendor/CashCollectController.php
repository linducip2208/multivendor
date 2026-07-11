<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\DeliveryCashCollect;
use App\Models\Order;
use Illuminate\Http\Request;

class CashCollectController extends Controller
{
    public function index(Request $request)
    {
        $shop = auth('vendor')->user()->shop;
        $deliveryManId = auth('vendor')->id();

        $query = DeliveryCashCollect::where('delivery_man_id', $deliveryManId)
            ->with(['order.shop']);

        if ($request->filled('status')) {
            if ($request->status === 'collected') {
                $query->where('collected', true);
            } elseif ($request->status === 'pending') {
                $query->where('collected', false);
            }
        }

        $collects = $query->latest()->paginate(20);
        $totalPending = DeliveryCashCollect::where('delivery_man_id', $deliveryManId)
            ->where('collected', false)
            ->sum('amount');
        $totalCollected = DeliveryCashCollect::where('delivery_man_id', $deliveryManId)
            ->where('collected', true)
            ->sum('amount');

        return view('vendor.cash-collect.index', compact('collects', 'totalPending', 'totalCollected'));
    }

    public function markCollected(DeliveryCashCollect $collect)
    {
        if ($collect->delivery_man_id !== auth('vendor')->id()) {
            abort(403);
        }

        $collect->markCollected();

        $dm = auth('vendor')->user();
        if ($dm->wallet) {
            $dm->wallet->credit($collect->amount, 'Cash collected from order #' . $collect->order->order_number);
        }

        return back()->with('success', 'Pembayaran COD #' . $collect->order->order_number . ' ditandai lunas.');
    }
}
