<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class BarcodeController extends Controller
{
    public function index()
    {
        $shop = auth('vendor')->user()->shop;
        $products = Product::where('shop_id', $shop->id)->where('status', 'approved')->paginate(20);
        return view('vendor.barcode.index', compact('products'));
    }

    public function print(Request $request)
    {
        $shop = auth('vendor')->user()->shop;
        $ids = $request->ids ? explode(',', $request->ids) : [];
        $products = Product::where('shop_id', $shop->id)->whereIn('id', $ids)->get();
        return view('vendor.barcode.print', compact('products'));
    }
}
