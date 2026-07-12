<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class DiscountSettingsController extends Controller
{
    public function index()
    {
        return view('admin.discount-settings.index');
    }

    public function update(Request $request)
    {
        $settings = [
            'discount_bearer',
            'discount_admin_share',
            'discount_vendor_share',
            'coupon_bearer',
            'coupon_admin_share',
            'coupon_vendor_share',
            'discount_max_percentage',
            'discount_require_approval',
        ];

        $checkboxes = ['discount_require_approval'];

        foreach ($settings as $key) {
            if (in_array($key, $checkboxes)) {
                SystemSetting::set($key, $request->boolean($key) ? '1' : null);
            } elseif ($request->has($key)) {
                SystemSetting::set($key, $request->$key);
            }
        }

        return back()->with('success', 'Pengaturan diskon disimpan.');
    }
}
