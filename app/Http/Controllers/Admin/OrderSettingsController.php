<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class OrderSettingsController extends Controller
{
    public function index()
    {
        return view('admin.order-settings.index');
    }

    public function update(Request $request)
    {
        $settings = [
            'order_prefix',
            'order_min_amount',
            'order_max_amount',
            'order_cancel_time',
            'order_auto_confirm',
            'order_guest_checkout',
            'invoice_prefix',
            'invoice_terms',
            'invoice_footer',
            'invoice_logo',
            'delivery_verification',
            'delivery_otp_length',
        ];

        foreach ($settings as $key) {
            if ($request->has($key)) {
                SystemSetting::set($key, $request->$key);
            }
        }

        return back()->with('success', 'Pengaturan order & invoice disimpan.');
    }
}
