<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;

class ReviewController extends Controller
{
    public function index()
    {
        $shop = auth('vendor')->user()->shop;
        $reviews = ProductReview::whereHas('product', fn($q) => $q->where('shop_id', $shop->id))->with(['product', 'customer'])->latest()->paginate(15);
        return view('vendor.reviews.index', compact('reviews'));
    }

    public function update(Request $request, ProductReview $review)
    {
        $shop = auth('vendor')->user()->shop;
        if ($review->product->shop_id !== $shop->id) abort(403);
        $review->update(['status' => $request->boolean('status')]);
        return back()->with('success', 'Status ulasan diperbarui.');
    }
}
