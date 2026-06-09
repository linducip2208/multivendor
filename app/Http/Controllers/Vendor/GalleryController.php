<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Product;

class GalleryController extends Controller
{
    public function index() {
        $shop = auth('vendor')->user()->shop;
        $products = Product::where('shop_id',$shop->id)->where('status','approved')->whereNotNull('thumbnail')->paginate(20);
        return view('vendor.gallery.index',compact('products'));
    }
}
