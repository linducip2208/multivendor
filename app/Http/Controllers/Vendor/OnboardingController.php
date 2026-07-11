<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\SubscriptionPlan;
use App\Models\VendorSubscription;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function step1()
    {
        $shop = auth('vendor')->user()->shop;
        if (!$shop) return redirect()->route('vendor.dashboard');
        return view('vendor.onboarding.step1', compact('shop'));
    }

    public function storeStep1(Request $request)
    {
        $shop = auth('vendor')->user()->shop;
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'required|string',
            'phone' => 'nullable|string',
        ]);

        $shop->update($request->only(['name', 'description', 'address', 'phone']));

        return redirect()->route('vendor.onboarding.step2');
    }

    public function step2()
    {
        $shop = auth('vendor')->user()->shop;
        $categories = \App\Models\Category::whereNull('parent_id')->get();
        return view('vendor.onboarding.step2', compact('shop', 'categories'));
    }

    public function storeStep2(Request $request)
    {
        $shop = auth('vendor')->user()->shop;
        $request->validate([
            'bank_name' => 'required|string',
            'bank_account_number' => 'required|string',
            'bank_account_name' => 'required|string',
            'tin' => 'nullable|string',
        ]);

        $shop->update($request->only(['bank_name', 'bank_account_number', 'bank_account_name', 'tin']));

        return redirect()->route('vendor.onboarding.step3');
    }

    public function step3()
    {
        $shop = auth('vendor')->user()->shop;
        $shippingMethods = \App\Models\ShippingMethod::all();
        return view('vendor.onboarding.step3', compact('shop', 'shippingMethods'));
    }

    public function storeStep3(Request $request)
    {
        $shop = auth('vendor')->user()->shop;

        if ($request->has('shipping_methods')) {
            foreach ($request->shipping_methods as $methodId => $cost) {
                \App\Models\ShopShippingMethod::updateOrCreate(
                    ['shop_id' => $shop->id, 'shipping_method_id' => $methodId],
                    ['cost' => $cost, 'status' => true]
                );
            }
        }

        return redirect()->route('vendor.onboarding.step4');
    }

    public function step4()
    {
        $shop = auth('vendor')->user()->shop;
        return view('vendor.onboarding.step4', compact('shop'));
    }

    public function storeStep4(Request $request)
    {
        $shop = auth('vendor')->user()->shop;

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('shops/logos', 'public');
            $shop->update(['logo' => $path]);
        }
        if ($request->hasFile('banner')) {
            $path = $request->file('banner')->store('shops/banners', 'public');
            $shop->update(['banner' => $path]);
        }

        $shop->update(['onboarding_completed' => true]);

        $this->assignDefaultPlan($shop);

        $notifications = new NotificationService;
        $notifications->sendOnboardingComplete(auth('vendor')->user());

        return redirect()->route('vendor.dashboard')->with('success', 'Selamat! Setup toko Anda selesai. Mulai tambahkan produk.');
    }

    public function skip()
    {
        $shop = auth('vendor')->user()->shop;
        if ($shop) {
            $shop->update(['onboarding_completed' => true]);
            $this->assignDefaultPlan($shop);
        }
        return redirect()->route('vendor.dashboard');
    }

    protected function assignDefaultPlan(Shop $shop): void
    {
        $existing = VendorSubscription::where('shop_id', $shop->id)
            ->where('vendor_id', $shop->vendor_id)
            ->where('status', 'active')
            ->exists();

        if ($existing) return;

        $freePlan = SubscriptionPlan::where('slug', 'free')
            ->orWhere('price', 0)
            ->where('is_active', true)
            ->first();

        if (!$freePlan) {
            $freePlan = SubscriptionPlan::firstOrCreate(
                ['slug' => 'free'],
                [
                    'name' => 'Free',
                    'price' => 0,
                    'billing_period' => 'lifetime',
                    'max_products' => 10,
                    'commission_rate' => 10,
                    'can_chat' => true,
                    'can_pos' => true,
                    'is_active' => true,
                    'sort_order' => 0,
                ]
            );
        }

        VendorSubscription::create([
            'vendor_id' => $shop->vendor_id,
            'shop_id' => $shop->id,
            'subscription_plan_id' => $freePlan->id,
            'status' => 'active',
            'amount_paid' => 0,
            'starts_at' => now(),
            'ends_at' => $freePlan->billing_period === 'lifetime' ? now()->addYears(100) : now()->addMonth(),
        ]);
    }
}
