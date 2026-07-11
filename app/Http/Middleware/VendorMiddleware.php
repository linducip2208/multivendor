<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use App\Models\VendorSubscription;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VendorMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::guard('vendor')->check() || !Auth::guard('vendor')->user()->isVendor()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
            return redirect()->route('vendor.login');
        }

        $vendor = Auth::guard('vendor')->user();
        $shop = $vendor->shop;

        if ($shop && !$shop->onboarding_completed) {
            if (!$request->routeIs('vendor.onboarding.*') && !$request->routeIs('vendor.logout')) {
                return redirect()->route('vendor.onboarding.step1');
            }
            return $next($request);
        }

        $subscription = null;
        if ($shop) {
            $subscription = VendorSubscription::where('vendor_id', $vendor->id)
                ->where('shop_id', $shop->id)
                ->where('status', 'active')
                ->where(function ($q) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
                })
                ->with('plan')
                ->latest()
                ->first();
        }

        $restrictedRoutes = [
            'vendor.pos.*' => 'can_pos',
            'vendor.bulk-import.*' => 'can_bulk_import',
            'vendor.barcode.*' => 'can_barcode',
            'vendor.digital.*' => 'can_export',
        ];

        foreach ($restrictedRoutes as $pattern => $feature) {
            if ($request->routeIs($pattern)) {
                if (!$subscription || !$subscription->plan->{$feature}) {
                    if ($request->expectsJson()) {
                        return response()->json(['message' => 'Fitur ini memerlukan upgrade paket.'], 403);
                    }
                    return redirect()->route('vendor.dashboard')
                        ->with('error', 'Fitur ini memerlukan upgrade paket langganan.');
                }
            }
        }

        if ($subscription && $subscription->plan->max_products > 0) {
            if ($request->routeIs('vendor.products.store') || $request->routeIs('vendor.bulk-import.store')) {
                $productCount = \App\Models\Product::where('shop_id', $shop->id)->count();
                if ($productCount >= $subscription->plan->max_products) {
                    if ($request->expectsJson()) {
                        return response()->json(['message' => 'Batas produk tercapai. Upgrade paket untuk menambah.'], 403);
                    }
                    return back()->with('error', 'Batas produk tercapai (' . $subscription->plan->max_products . ' produk). Upgrade paket untuk menambah.');
                }
            }
        }

        return $next($request);
    }
}
