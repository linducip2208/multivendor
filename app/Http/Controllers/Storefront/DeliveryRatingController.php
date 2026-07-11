<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\DeliveryManRating;
use App\Models\Order;
use Illuminate\Http\Request;

class DeliveryRatingController extends Controller
{
    public function create(Order $order)
    {
        if ($order->customer_id !== auth()->id()) {
            abort(403);
        }

        if (!$order->delivery_man_id) {
            return back()->with('error', 'Tidak ada kurir untuk order ini.');
        }

        $existing = DeliveryManRating::where('order_id', $order->id)
            ->where('customer_id', auth()->id())
            ->first();

        return view('storefront.delivery.rate', compact('order', 'existing'));
    }

    public function store(Request $request, Order $order)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:500',
        ]);

        if ($order->customer_id !== auth()->id()) {
            abort(403);
        }

        DeliveryManRating::updateOrCreate(
            ['order_id' => $order->id, 'customer_id' => auth()->id()],
            [
                'delivery_man_id' => $order->delivery_man_id,
                'rating' => $request->rating,
                'review' => $request->review,
            ]
        );

        return redirect()->route('orders.show', $order->id)->with('success', 'Terima kasih! Rating telah disimpan.');
    }
}
