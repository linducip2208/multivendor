<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class FeaturedDealController extends Controller
{
    public function index()
    {
        $products = Product::where('status', 'approved')->get();
        $featured = Product::where('status', 'approved')->where('featured', true)->with('shop')->paginate(15);
        return view('admin.featured-deals.index', compact('products', 'featured'));
    }

    public function store(Request $request)
    {
        $request->validate(['product_ids' => 'required|array|max:20', 'product_ids.*' => 'exists:products,id']);
        Product::whereIn('id', $request->product_ids)->update(['featured' => true]);
        return back()->with('success', 'Featured deal ditambahkan.');
    }

    public function remove(Product $product)
    {
        $product->update(['featured' => false]);
        return back()->with('success', 'Featured deal dihapus.');
    }
}
