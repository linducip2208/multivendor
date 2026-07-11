<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = \App\Models\SystemSetting::pluck('value', 'key')->toArray();
        $mailProvider = Provider::ofType('mail')->active()->first();
        return view('admin.settings.index', compact('settings', 'mailProvider'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'app_name' => 'required|string|max:255',
            'app_url' => 'required|url',
            'brand_color' => 'nullable|string|max:7',
            'mail_mailer' => 'required|string',
            'mail_host' => 'nullable|string',
            'mail_port' => 'nullable|string',
            'mail_username' => 'nullable|string',
            'mail_password' => 'nullable|string',
            'mail_from_address' => 'nullable|email',
            'currency' => 'nullable|string|max:10',
            'currency_symbol' => 'nullable|string|max:5',
            'order_prefix' => 'nullable|string|max:10',
            'min_withdraw' => 'nullable|numeric|min:0',
            'commission_default' => 'nullable|numeric|min:0|max:100',
        ]);

        foreach ($validated as $key => $value) {
            \App\Models\SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        // Handle logo upload
        if ($request->hasFile('logo_file')) {
            $path = $request->file('logo_file')->store('logo', 'public');
            \App\Models\SystemSetting::updateOrCreate(
                ['key' => 'logo_url'],
                ['value' => asset('storage/' . $path)]
            );
        } elseif ($request->filled('logo_url')) {
            \App\Models\SystemSetting::updateOrCreate(
                ['key' => 'logo_url'],
                ['value' => $request->logo_url]
            );
        }

        // Handle favicon upload
        if ($request->hasFile('favicon_file')) {
            $path = $request->file('favicon_file')->store('logo', 'public');
            \App\Models\SystemSetting::updateOrCreate(
                ['key' => 'favicon_url'],
                ['value' => asset('storage/' . $path)]
            );
        } elseif ($request->filled('favicon_url')) {
            \App\Models\SystemSetting::updateOrCreate(
                ['key' => 'favicon_url'],
                ['value' => $request->favicon_url]
            );
        }

        \Illuminate\Support\Facades\Cache::forget('whitelabel_branding');

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }
}
