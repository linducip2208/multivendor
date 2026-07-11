<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use Illuminate\Http\Request;

class ShippingCategoryController extends Controller
{
    public function index()
    {
        $categories = Category::whereNull('parent_id')->with('children')->get();
        $shippingMethods = ShippingMethod::all();
        $zones = ShippingZone::with('rates')->get();
        return view('admin.shipping-category.index', compact('categories', 'shippingMethods', 'zones'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'shipping_method_id' => 'required|exists:shipping_methods,id',
            'cost' => 'required|numeric|min:0',
            'zone_id' => 'nullable|exists:shipping_zones,id',
        ]);

        \App\Models\SystemSetting::set(
            'cat_shipping_' . $request->category_id . '_' . $request->shipping_method_id,
            $request->cost
        );

        return back()->with('success', 'Biaya pengiriman kategori disimpan.');
    }

    public function destroy(Request $request)
    {
        \App\Models\SystemSetting::set(
            'cat_shipping_' . $request->category_id . '_' . $request->shipping_method_id,
            null
        );
        return back()->with('success', 'Biaya pengiriman dihapus.');
    }
}
