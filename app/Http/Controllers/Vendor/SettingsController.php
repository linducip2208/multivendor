<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Services\Vendor\VendorSettingsService;
use App\Support\Currency;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function index(Request $request, VendorSettingsService $settings): View
    {
        return view('vendor.settings.index', [
            'shop' => $settings->publicShop(),
            'maskedAccount' => $settings->maskedAccount(),
        ]);
    }

    public function update(Request $request, VendorSettingsService $settings): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:160'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:12'],
            'meta_title' => ['nullable', 'string', 'max:160'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'vacation_mode' => ['nullable', 'boolean'],
            'vacation_message' => ['nullable', 'string', 'max:500'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account_name' => ['nullable', 'string', 'max:120'],
            'bank_account_number' => [
                'nullable',
                'string',
                'max:64',
                'regex:/^[0-9\-\s]{6,64}$/',
            ],
        ], [
            'bank_account_number.regex' => 'Nomor rekening hanya boleh berisi angka, spasi atau tanda hubung.',
        ]);

        $settings->updateStore($validated);

        return back()->with('success', 'Pengaturan toko disimpan.');
    }

    public function shipping(Request $request, VendorSettingsService $settings): View
    {
        $data = $settings->shipping();

        return view('vendor.settings.shipping', [
            'methods' => $data['methods'],
            'currency' => Currency::config(),
        ]);
    }

    public function updateShipping(Request $request, VendorSettingsService $settings): RedirectResponse
    {
        $validated = $request->validate([
            'rates' => ['nullable', 'array'],
            'rates.*' => ['array'],
            'rates.*.enabled' => ['nullable', 'boolean'],
            'rates.*.cost' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
        ]);

        $settings->updateShipping($validated['rates'] ?? []);

        return back()->with('success', 'Tarif pengiriman disimpan.');
    }

    public function notifications(Request $request, VendorSettingsService $settings): View
    {
        $data = $settings->notifications();

        return view('vendor.settings.notifications', [
            'matrix' => $data['matrix'],
            'categories' => $data['categories'],
            'channels' => $data['channels'],
        ]);
    }

    public function updateNotifications(Request $request, VendorSettingsService $settings): RedirectResponse
    {
        $validated = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*' => ['array'],
            'preferences.*.*' => ['array'],
            'preferences.*.*.enabled' => ['nullable', 'boolean'],
            'preferences.*.*.destination' => ['nullable', 'string', 'max:190'],
        ], [], [
            'preferences.*.*.enabled' => 'preferensi notifikasi',
        ]);

        $written = $settings->updateNotifications($validated['preferences']);

        return back()->with('success', $written.' preferensi notifikasi disimpan.');
    }

    public function security(Request $request, VendorSettingsService $settings): View
    {
        $data = $settings->security();

        return view('vendor.settings.security', $data);
    }

    public function updateSecurity(Request $request, VendorSettingsService $settings): RedirectResponse
    {
        $validated = $request->validate([
            'two_factor_enabled' => ['nullable', 'boolean'],
            'login_alert' => ['nullable', 'boolean'],
            'login_alert_after' => ['nullable', 'integer', 'min:1', 'max:100'],
            'session_timeout_minutes' => ['nullable', 'integer', 'min:5', 'max:10080'],
            'password_rotation_enabled' => ['nullable', 'boolean'],
            'password_rotation_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'max_failed_attempts' => ['nullable', 'integer', 'min:1', 'max:100'],
            'lockout_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'ip_allowlist' => ['nullable', 'string', 'max:2000'],
            'password' => [
                'nullable',
                'string',
                'min:8',
                'confirmed',
                Rule::password(),
            ],
        ]);

        if (! empty($validated['password'])) {
            $user = auth('vendor')->user();
            $user->forceFill(['password' => bcrypt($validated['password'])])->save();
        }

        $settings->updateSecurity($validated);

        return back()->with('success', 'Pengaturan keamanan disimpan.');
    }
}
