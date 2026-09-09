<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\DeliveryCashCollect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashCollectController extends Controller
{
    public function index(Request $request)
    {
        $deliveryId = auth('delivery')->id();
        $collects = DeliveryCashCollect::where('delivery_man_id', $deliveryId)->with('order.shop')->latest()->paginate(20);

        return view('vendor.cash-collect.index', ['collects' => $collects,
            'totalPending' => DeliveryCashCollect::where('delivery_man_id', $deliveryId)->where('collected', false)->sum('amount'),
            'totalCollected' => DeliveryCashCollect::where('delivery_man_id', $deliveryId)->where('collected', true)->sum('amount')]);
    }

    public function markCollected(DeliveryCashCollect $collect)
    {
        abort_unless($collect->delivery_man_id === auth('delivery')->id(), 403);
        DB::transaction(function () use ($collect) {
            $collect = DeliveryCashCollect::lockForUpdate()->findOrFail($collect->id);
            $collect->markCollected();
        });

        // COD collection is a custody record, not delivery-man wallet revenue.
        return back()->with('success', 'Dana COD ditandai telah diterima dan menunggu rekonsiliasi.');
    }
}
