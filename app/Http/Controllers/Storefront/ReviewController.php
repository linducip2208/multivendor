<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function store(Request $request) {
        $v = $request->validate(['product_id'=>'required|exists:products,id','rating'=>'required|integer|min:1|max:5','comment'=>'nullable|string|max:2000']);
        DB::transaction(function () use ($v) {
            $item = OrderItem::where('product_id', $v['product_id'])->where('is_reviewed', false)
                ->whereHas('order', fn ($query) => $query->where('customer_id', auth()->id())->where('payment_status', 'paid')->where('order_status', 'delivered'))
                ->lockForUpdate()->first();
            if (!$item) abort(422, 'Ulasan hanya tersedia untuk produk dari pesanan terkirim yang belum diulas.');
            ProductReview::create($v + ['customer_id' => auth()->id(), 'status' => true]);
            $item->update(['is_reviewed' => true]);
        });
        return back()->with('success','Ulasan berhasil dikirim!');
    }
}
