<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class DigitalProductController extends Controller
{
    public function index() {
        $shop = auth('vendor')->user()->shop;
        $products = Product::where('shop_id',$shop->id)->where('product_type','digital')->latest()->paginate(15);
        return view('vendor.digital-product.index',compact('products'));
    }
    public function upload(Request $request, Product $product) {
        $shop = auth('vendor')->user()->shop;
        if($product->shop_id !== $shop->id) abort(403);
        if($request->hasFile('digital_file')) {
            $path = $request->file('digital_file')->store('digital-products','public');
            $product->update(['digital_file'=>$path]);
        }
        return back()->with('success','File digital diupload.');
    }
}
