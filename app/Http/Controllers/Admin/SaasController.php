<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SaasPlan;
use App\Models\SaasSubscription;
use App\Models\Tenant;
use App\Services\AuditLogger;
use App\Services\Backoffice\SaasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SaasController extends Controller
{
    public function __construct(private readonly SaasService $saas) {}

    public function index(Request $request): View
    {
        return view('admin.tenants.index', $this->saas->tenants(
            (int) $request->query('page', 1),
            trim((string) $request->query('search', '')),
            (string) $request->query('status', ''),
        ));
    }

    public function show(Tenant $tenant): View
    {
        return view('admin.tenants.show', $this->saas->tenantDetail($tenant));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->saas->createTenant($this->validateTenant($request), auth('admin')->id());

        return redirect()->route('admin.tenants.show', $tenant)->with('success', 'Tenant "'.$tenant->name.'" dibuat.');
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->saas->updateTenant($tenant, $this->validateTenant($request, $tenant->id), auth('admin')->id());

        return back()->with('success', 'Tenant "'.$tenant->name.'" diperbarui.');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $this->saas->deleteTenant($tenant, auth('admin')->id());

        return redirect()->route('admin.tenants.index')->with('success', 'Tenant dinonaktifkan dan dihapus.');
    }

    public function plans(Request $request): View
    {
        return view('admin.saas.plans', $this->saas->plans((int) $request->query('page', 1)));
    }

    public function storePlan(Request $request): RedirectResponse
    {
        $plan = $this->saas->savePlan(null, $this->validatePlan($request), auth('admin')->id());

        return back()->with('success', 'Paket "'.$plan->name.'" dibuat.');
    }

    public function updatePlan(Request $request, SaasPlan $plan): RedirectResponse
    {
        $this->saas->savePlan($plan, $this->validatePlan($request, $plan->id), auth('admin')->id());

        return back()->with('success', 'Paket "'.$plan->name.'" diperbarui.');
    }

    public function destroyPlan(SaasPlan $plan): RedirectResponse
    {
        $this->saas->deletePlan($plan, auth('admin')->id());

        return back()->with('success', 'Paket dihapus.');
    }

    public function subscriptions(Request $request): View
    {
        return view('admin.saas.subscriptions', $this->saas->subscriptions(
            (int) $request->query('page', 1),
            (string) $request->query('status', ''),
            trim((string) $request->query('search', '')),
        ));
    }

    public function updateSubscription(Request $request, SaasSubscription $subscription): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(SaasService::SUBSCRIPTION_STATUSES))],
        ]);

        $updated = $this->saas->updateSubscriptionStatus($subscription, (string) $validated['status'], auth('admin')->id());

        return back()->with('success', 'Langganan diperbarui menjadi '.$updated->status.'.');
    }

    public function usage(): View
    {
        return view('admin.saas.usage', $this->saas->usageOverview());
    }

    public function themes(): View
    {
        return view('admin.saas.themes', $this->saas->themes());
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTenant(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'domain' => ['required', 'string', 'max:160', 'regex:/^[a-z0-9.\-]+$/i', Rule::unique('tenants', 'domain')->ignore($ignoreId)],
            'domains' => ['nullable', 'array', 'max:10'],
            'domains.*' => ['string', 'max:160', 'regex:/^[a-z0-9.\-]+$/i'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'favicon_url' => ['nullable', 'string', 'max:500'],
            'theme' => ['nullable', 'array'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'timezone' => ['nullable', 'timezone'],
            'locale' => ['nullable', 'string', 'size:5'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'enabled_features' => ['nullable', 'array'],
            'enabled_features.*' => ['string', Rule::in(array_map(fn ($case): string => $case->value, \App\Enums\PlatformFeature::cases()))],
            'default_commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'trial_ends_at' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePlan(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'alpha_dash', Rule::unique('saas_plans', 'slug')->ignore($ignoreId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'monthly_price' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'yearly_price' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'currency' => ['nullable', 'string', 'size:3'],
            'max_products' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'max_vendors' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'max_staff' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'max_orders_per_month' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'max_storage_mb' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'pseo_page_quota' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'ai_request_quota' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'features' => ['nullable', 'array', 'max:40'],
            'features.*' => ['string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);
    }
}
