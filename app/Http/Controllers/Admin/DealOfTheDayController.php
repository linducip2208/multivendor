<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class DealOfTheDayController extends Controller
{
    public function index()
    {
        $deals = \App\Models\DealOfTheDay::with('product.shop')->latest()->paginate(15);
        $products = Product::where('status', 'approved')->get();
        return view('admin.deals.index', compact('deals', 'products'));
    }

    public function store(Request $request)
    {
        $request->validate(['product_id' => 'required|exists:products,id|unique:deals_of_the_day,product_id,date,'.now()->toDateString(), 'discount_type' => 'required|in:flat,percentage', 'discount_value' => 'required|numeric|min:0']);
        \App\Models\DealOfTheDay::create(['product_id' => $request->product_id, 'discount_type' => $request->discount_type, 'discount_value' => $request->discount_value, 'date' => now()->toDateString()]);

        $product = Product::find($request->product_id);
        if ($request->discount_type === 'percentage') {
            $product->special_price = $product->price * (1 - ($request->discount_value / 100));
        } else {
            $product->special_price = max(0, $product->price - $request->discount_value);
        }
        $product->discount_type = $request->discount_type;
        $product->discount_start = now();
        $product->discount_end = now()->endOfDay();
        $product->save();

        return redirect()->route('admin.deals.index')->with('success', 'Deal of the day ditambahkan.');
    }

    public function destroy(\App\Models\DealOfTheDay $deal)
    {
        $product = $deal->product;
        $product->update(['special_price' => null, 'discount_type' => null, 'discount_start' => null, 'discount_end' => null]);
        $deal->delete();
        return back()->with('success', 'Deal dihapus.');
    }
}
