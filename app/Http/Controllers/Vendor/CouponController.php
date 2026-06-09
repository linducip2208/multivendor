<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index()
    {
        $shop = auth('vendor')->user()->shop;
        $coupons = Coupon::where('shop_id', $shop->id)->latest()->paginate(15);
        return view('vendor.coupon.index', compact('coupons'));
    }

    public function create()
    {
        return view('vendor.coupon.create');
    }

    public function store(Request $request)
    {
        $shop = auth('vendor')->user()->shop;
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:coupons,code',
            'title' => 'nullable|string|max:255',
            'coupon_type' => 'required|in:percentage,fixed,free_shipping',
            'discount_value' => 'required|numeric|min:0',
            'min_purchase' => 'numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'usage_limit' => 'nullable|integer|min:1',
            'status' => 'boolean',
        ]);
        $validated['shop_id'] = $shop->id;
        Coupon::create($validated);
        return redirect()->route('vendor.coupon.index')->with('success', 'Kupon berhasil dibuat.');
    }

    public function edit(Coupon $coupon)
    {
        $shop = auth('vendor')->user()->shop;
        if ($coupon->shop_id !== $shop->id) abort(403);
        return view('vendor.coupon.edit', compact('coupon'));
    }

    public function update(Request $request, Coupon $coupon)
    {
        $shop = auth('vendor')->user()->shop;
        if ($coupon->shop_id !== $shop->id) abort(403);
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:coupons,code,' . $coupon->id,
            'title' => 'nullable|string|max:255',
            'coupon_type' => 'required|in:percentage,fixed,free_shipping',
            'discount_value' => 'required|numeric|min:0',
            'min_purchase' => 'numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'usage_limit' => 'nullable|integer|min:1',
            'status' => 'boolean',
        ]);
        $coupon->update($validated);
        return redirect()->route('vendor.coupon.index')->with('success', 'Kupon diperbarui.');
    }

    public function destroy(Coupon $coupon)
    {
        $shop = auth('vendor')->user()->shop;
        if ($coupon->shop_id !== $shop->id) abort(403);
        $coupon->delete();
        return back()->with('success', 'Kupon dihapus.');
    }
}
