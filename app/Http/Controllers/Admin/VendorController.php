<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $query = Shop::with('vendor')->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $shops = $query->paginate(15)->withQueryString();

        return view('admin.vendors.index', compact('shops'));
    }

    public function create()
    {
        return view('admin.vendors.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'phone' => 'nullable|string|max:20',
            'shop_name' => 'required|string|max:255',
            'shop_description' => 'nullable|string',
            'shop_address' => 'nullable|string',
            'commission_type' => 'required|in:percentage,fixed',
            'commission_value' => 'required|numeric|min:0',
            'status' => 'required|in:pending,active,suspended',
        ]);

        $vendor = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'],
            'role' => 'vendor',
            'status' => $validated['status'] === 'active' ? 'active' : 'inactive',
        ]);

        Wallet::create(['user_id' => $vendor->id, 'balance' => 0]);

        $slug = Str::slug($validated['shop_name']);
        $originalSlug = $slug;
        $counter = 1;
        while (Shop::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        Shop::create([
            'vendor_id' => $vendor->id,
            'name' => $validated['shop_name'],
            'slug' => $slug,
            'description' => $validated['shop_description'],
            'address' => $validated['shop_address'],
            'phone' => $validated['phone'],
            'commission_type' => $validated['commission_type'],
            'commission_value' => $validated['commission_value'],
            'status' => $validated['status'],
        ]);

        return redirect()->route('admin.vendors.index')->with('success', 'Vendor berhasil ditambahkan.');
    }

    public function show(Shop $shop)
    {
        $shop->load(['vendor', 'products' => fn ($q) => $q->latest()->take(10), 'orders' => fn ($q) => $q->latest()->take(10)]);
        app(\App\Services\AuditLogger::class)->impersonate('vendor', (int) $shop->getKey(), auth('admin')->id(), ['shop' => $shop->name]);
        $commissionPreview = $this->commissionPreview($shop);

        return view('admin.vendors.show', compact('shop', 'commissionPreview'));
    }

    public function edit(Shop $shop)
    {
        return view('admin.vendors.edit', compact('shop'));
    }

    public function update(Request $request, Shop $shop)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $shop->vendor_id,
            'password' => 'nullable|min:6',
            'phone' => 'nullable|string|max:20',
            'shop_name' => 'required|string|max:255',
            'shop_description' => 'nullable|string',
            'shop_address' => 'nullable|string',
            'commission_type' => 'required|in:percentage,fixed',
            'commission_value' => 'required|numeric|min:0',
            'status' => 'required|in:pending,active,suspended',
        ]);

        $shop->vendor->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'status' => $validated['status'] === 'active' ? 'active' : 'inactive',
        ]);

        if ($validated['password']) {
            $shop->vendor->update(['password' => Hash::make($validated['password'])]);
        }

        $shop->update([
            'name' => $validated['shop_name'],
            'description' => $validated['shop_description'],
            'address' => $validated['shop_address'],
            'phone' => $validated['phone'],
            'commission_type' => $validated['commission_type'],
            'commission_value' => $validated['commission_value'],
            'status' => $validated['status'],
        ]);

        $before = ['commission' => $shop->getOriginal('commission_type').': '.$shop->getOriginal('commission_value'), 'status' => $shop->getOriginal('status')];
        app(\App\Services\AuditLogger::class)->log('vendor.updated', $shop, $before, [
            'commission' => $validated['commission_type'].': '.$validated['commission_value'],
            'status' => $validated['status'],
        ], auth('admin')->id());

        return redirect()->route('admin.vendors.index')->with('success', 'Vendor berhasil diperbarui.');
    }

    public function destroy(Shop $shop)
    {
        $vendor = $shop->vendor;
        $shop->delete();
        $vendor->delete();
        return redirect()->route('admin.vendors.index')->with('success', 'Vendor berhasil dihapus.');
    }

    public function updateStatus(Request $request, Shop $shop)
    {
        $request->validate(['status' => 'required|in:pending,active,suspended,rejected']);
        $shop->update([
            'status' => $request->status,
            'rejection_reason' => $request->status === 'rejected' ? $request->reason : null,
        ]);

        if ($request->status === 'active') {
            $shop->vendor->update(['status' => 'active']);
        } elseif (in_array($request->status, ['suspended', 'rejected'])) {
            $shop->vendor->update(['status' => 'inactive']);
        }

        $labels = ['pending' => 'Pending', 'active' => 'Aktif', 'suspended' => 'Ditangguhkan', 'rejected' => 'Ditolak'];
        return back()->with('success', 'Status vendor diubah menjadi ' . ($labels[$request->status] ?? $request->status));
    }

    /**
     * Pratinjau komisi bertingkat per kategori untuk toko ini.
     * Tarif kategori dari system_settings, fallback ke komisi toko.
     */
    private function commissionPreview(Shop $shop): array
    {
        $rates = [];
        try {
            $raw = \App\Models\SystemSetting::get('commission_category_rates', '');
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            if (is_array($decoded)) {
                foreach ($decoded as $catId => $pct) {
                    $rates[(int) $catId] = max(0.0, min(100.0, (float) $pct));
                }
            }
        } catch (\Throwable) {
            $rates = [];
        }
        $examples = [50000, 150000, 500000];
        $rows = [];
        foreach ($examples as $price) {
            $categoryRate = $rates !== [] ? (float) reset($rates) : (float) ($shop->commission_value ?? 0);
            $isFixed = strtolower((string) ($shop->commission_type ?? 'percentage')) === 'fixed';
            $commission = $isFixed ? (float) ($shop->commission_value ?? 0) : round($price * ($categoryRate / 100), 2);
            $rows[] = ['price' => $price, 'rate' => $isFixed ? null : $categoryRate, 'commission' => $commission, 'net' => round($price - $commission, 2)];
        }

        return [
            'type' => (string) ($shop->commission_type ?? 'percentage'),
            'value' => (float) ($shop->commission_value ?? 0),
            'category_rates' => $rates,
            'examples' => $rows,
        ];
    }
}
