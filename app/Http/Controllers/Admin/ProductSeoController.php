<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductSeoController extends Controller
{
    public function index()
    {
        $products = Product::where('status', 'approved')->paginate(20);
        return view('admin.product-seo.index', compact('products'));
    }

    public function update(Request $request, Product $product)
    {
        $request->validate(['meta_title' => 'nullable|string|max:255', 'meta_description' => 'nullable|string|max:500', 'meta_image' => 'nullable|string|max:500']);
        $product->update($request->only(['meta_title', 'meta_description', 'meta_image']));
        return back()->with('success', 'SEO meta diperbarui.');
    }
}
