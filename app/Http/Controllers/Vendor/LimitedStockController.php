<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Product;

class LimitedStockController extends Controller
{
    public function index()
    {
        $shop = auth('vendor')->user()->shop;
        $threshold = 10;
        $products = Product::where('shop_id', $shop->id)->where('current_stock', '<=', $threshold)->where('status', 'approved')->paginate(20);
        return view('vendor.limited-stock.index', compact('products', 'threshold'));
    }
}
