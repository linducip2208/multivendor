<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\VendorSubscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function plans()
    {
        $plans = SubscriptionPlan::orderBy('sort_order')->get();
        return view('admin.subscriptions.plans', compact('plans'));
    }

    public function storePlan(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:subscription_plans,slug',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'billing_period' => 'required|in:monthly,yearly,lifetime',
            'max_products' => 'required|integer|min:0',
            'commission_rate' => 'required|numeric|min:0|max:100',
        ]);

        SubscriptionPlan::create($validated + [
            'is_active' => $request->boolean('is_active'),
            'can_chat' => $request->boolean('can_chat'),
            'can_export' => $request->boolean('can_export'),
            'can_bulk_import' => $request->boolean('can_bulk_import'),
            'can_pos' => $request->boolean('can_pos'),
            'can_barcode' => $request->boolean('can_barcode'),
            'featured_shop' => $request->boolean('featured_shop'),
            'sort_order' => $request->integer('sort_order', 0),
        ]);

        return back()->with('success', 'Paket langganan dibuat.');
    }

    public function updatePlan(Request $request, SubscriptionPlan $plan)
    {
        $plan->update($request->only([
            'name', 'description', 'price', 'billing_period',
            'max_products', 'commission_rate', 'is_active',
            'can_chat', 'can_export', 'can_bulk_import', 'can_pos',
            'can_barcode', 'featured_shop', 'sort_order',
        ]));

        return back()->with('success', 'Paket diperbarui.');
    }

    public function destroyPlan(SubscriptionPlan $plan)
    {
        if ($plan->subscriptions()->exists()) {
            return back()->with('error', 'Paket masih memiliki langganan aktif.');
        }
        $plan->delete();
        return back()->with('success', 'Paket dihapus.');
    }

    public function subscriptions()
    {
        $subscriptions = VendorSubscription::with(['vendor', 'shop', 'plan'])->latest()->paginate(20);
        return view('admin.subscriptions.index', compact('subscriptions'));
    }

    public function showSubscription(VendorSubscription $subscription)
    {
        $subscription->load(['vendor', 'shop', 'plan']);
        return view('admin.subscriptions.show', compact('subscription'));
    }

    public function updateSubscriptionStatus(Request $request, VendorSubscription $subscription)
    {
        $subscription->update(['status' => $request->status]);
        return back()->with('success', 'Status langganan diperbarui.');
    }
}
