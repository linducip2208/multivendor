<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function settings()
    {
        $vendor = auth('vendor')->user();
        $shop = $vendor->shop;
        if (!$shop) return redirect()->route('vendor.dashboard')->with('error', 'Toko belum disetujui.');
        return view('vendor.shop.settings', compact('shop'));
    }

    public function updateSettings(Request $request)
    {
        $vendor = auth('vendor')->user();
        $shop = $vendor->shop;

        $validated = $request->validate([
            'shop_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'logo' => 'nullable|string|max:500',
            'banner' => 'nullable|string|max:500',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_account_name' => 'nullable|string|max:255',
        ]);

        $shop->update([
            'name' => $validated['shop_name'],
            'description' => $validated['description'] ?? null,
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'logo' => $validated['logo'] ?? null,
            'banner' => $validated['banner'] ?? null,
            'bank_name' => $validated['bank_name'] ?? null,
            'bank_account_number' => $validated['bank_account_number'] ?? null,
            'bank_account_name' => $validated['bank_account_name'] ?? null,
        ]);

        if (!empty($validated['phone'])) $vendor->update(['phone' => $validated['phone']]);

        return back()->with('success', 'Pengaturan toko diperbarui.');
    }

    public function toggleVacation()
    {
        $shop = auth('vendor')->user()->shop;
        $shop->update(['vacation_mode' => !$shop->vacation_mode]);
        $status = $shop->vacation_mode ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('success', "Mode liburan {$status}.");
    }
}
