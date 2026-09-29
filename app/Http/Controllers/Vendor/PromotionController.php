<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Services\Vendor\VendorPromotionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function index(Request $request, VendorPromotionService $promotions): View
    {
        $type = VendorScopeRequest::enum($request, 'type', ['coupon', 'campaign'], 'coupon');

        $data = $promotions->index($type, VendorScopeRequest::search($request));

        return view('vendor.promotions.index', [
            'type' => $data['type'],
            'search' => $data['search'],
            'coupons' => $data['coupons'],
            'campaigns' => $data['campaigns'],
            'stats' => $data['stats'],
            'types' => $data['types'],
            'campaignTypes' => $data['campaign_types'],
        ]);
    }

    public function store(Request $request, VendorPromotionService $promotions): RedirectResponse
    {
        $kind = $request->string('kind', 'coupon')->toString();

        if ($kind === 'campaign') {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:160'],
                'code' => ['nullable', 'string', 'max:60', 'alpha_dash'],
                'type' => ['required', 'in:promotion,flash_sale,bundle'],
                'description' => ['nullable', 'string', 'max:1000'],
                'discount_value' => ['nullable', 'numeric', 'min:0'],
                'discount_type' => ['nullable', 'in:percentage,fixed'],
                'budget' => ['nullable', 'numeric', 'min:0'],
                'status' => ['required', 'in:draft,scheduled,active,paused,ended'],
                'starts_at' => ['nullable', 'date'],
                'ends_at' => ['nullable', 'date', 'after:starts_at'],
                'products' => ['nullable', 'array'],
                'products.*' => ['integer'],
            ]);

            $promotions->storeCampaign($validated);

            return back()->with('success', 'Promo berhasil dibuat.');
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:coupons,code'],
            'title' => ['nullable', 'string', 'max:160'],
            'coupon_type' => ['required', 'in:percentage,fixed,free_shipping'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'min_purchase' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_per_customer' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'boolean'],
            'products' => ['nullable', 'array'],
            'products.*' => ['integer'],
        ]);

        $promotions->storeCoupon($validated);

        return back()->with('success', 'Kode promo '.$validated['code'].' berhasil dibuat.');
    }

    public function destroy(Request $request, VendorPromotionService $promotions): RedirectResponse
    {
        $validated = $request->validate([
            'kind' => ['required', 'in:coupon,campaign'],
            'id' => ['required', 'integer'],
        ]);

        if ($validated['kind'] === 'campaign') {
            $promotions->destroyCampaign($validated['id']);

            return back()->with('success', 'Promo dihapus.');
        }

        $promotions->destroyCoupon($validated['id']);

        return back()->with('success', 'Kode promo dihapus.');
    }
}
