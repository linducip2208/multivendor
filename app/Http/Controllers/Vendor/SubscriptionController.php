<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Services\Vendor\VendorSubscriptionService;
use App\Support\Currency;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(private readonly VendorSubscriptionService $subscriptions) {}

    public function index(Request $request): View
    {
        $this->subscriptions->reconcile();

        $data = $this->subscriptions->overview();

        return view('vendor.subscription.index', [
            'shop' => $data['shop'],
            'subscription' => $data['subscription'],
            'plan' => $data['plan'],
            'status' => $data['status'],
            'entitlements' => $data['entitlements'],
            'usage' => $data['usage'],
            'consumption' => $data['consumption'],
            'plans' => $data['plans'],
            'currentPlanId' => $data['current_plan_id'],
            'currency' => Currency::config(),
        ]);
    }

    public function subscribe(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'payment_method' => ['nullable', 'string', 'max:40'],
            'transaction_reference' => ['nullable', 'string', 'max:120'],
            'auto_renew' => ['nullable', 'boolean'],
        ]);

        $plan = SubscriptionPlan::query()->findOrFail($validated['plan_id']);

        abort_if(! $plan->is_active, 422, 'Paket ini tidak tersedia.');

        $subscription = $this->subscriptions->subscribe($validated['plan_id'], $validated);

        return back()->with(
            'success',
            'Berlangganan paket '.$plan->name.' aktif sampai '.($subscription->ends_at?->format('d M Y') ?? '-').'.'
        );
    }

    public function renew(Request $request): RedirectResponse
    {
        $subscription = $this->subscriptions->renew();

        return back()->with(
            'success',
            'Periode langganan diperpanjang sampai '.($subscription->ends_at?->format('d M Y') ?? '-').'.'
        );
    }

    public function cancel(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $this->subscriptions->cancel($validated['reason']);

        return back()->with('success', 'Langganan dibatalkan. Toko tetap aktif sampai akhir periode berjalan.');
    }
}
