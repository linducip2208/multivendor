<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class TrackOrderController extends Controller
{
    public function show(Request $request)
    {
        $order = null;
        if ($request->has('order_number')) {
            $order = Order::where('order_number', $request->order_number)
                ->where('customer_id', $request->user()->id)
                ->with(['items.product', 'statusHistory', 'shop'])
                ->first();
        }
        return view('storefront.track-order', compact('order'));
    }
}
