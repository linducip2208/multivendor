<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ClearanceSaleController extends Controller
{
    public function index()
    {
        $shop = auth('vendor')->user()->shop;
        $products = Product::where('shop_id', $shop->id)->where('status', 'approved')
            ->whereNotNull('special_price')->where('special_price', '<', 999999999)
            ->paginate(15);
        return view('vendor.clearance.index', compact('products'));
    }

    public function update(Request $request)
    {
        $shop = auth('vendor')->user()->shop;
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'discount_type' => 'required|in:flat,percentage',
            'discount_value' => 'required|numeric|min:0',
        ]);

        $product = Product::where('shop_id', $shop->id)->findOrFail($request->product_id);

        if ($request->discount_type === 'percentage') {
            $product->special_price = $product->price * (1 - ($request->discount_value / 100));
        } else {
            $product->special_price = max(0, $product->price - $request->discount_value);
        }

        $product->discount_type = $request->discount_type;
        $product->discount_start = now();
        $product->discount_end = now()->addDays(7);
        $product->save();

        return back()->with('success', 'Clearance sale diterapkan.');
    }

    public function remove(Product $product)
    {
        $shop = auth('vendor')->user()->shop;
        if ($product->shop_id !== $shop->id) abort(403);
        $product->update(['special_price' => null, 'discount_type' => null, 'discount_start' => null, 'discount_end' => null]);
        return back()->with('success', 'Clearance sale dihapus.');
    }
}
