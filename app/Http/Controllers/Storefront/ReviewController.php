<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request) {
        $v = $request->validate(['product_id'=>'required|exists:products,id','rating'=>'required|integer|min:1|max:5','comment'=>'nullable|string']);
        $v['customer_id'] = auth()->id();
        ProductReview::create($v);
        return back()->with('success','Ulasan berhasil dikirim!');
    }
}
