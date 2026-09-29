<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbandonedCart;
use App\Models\Affiliate;
use App\Models\Campaign;
use App\Services\AuditLogger;
use App\Services\Marketing\CampaignRuleEngine;
use App\Services\Marketing\CampaignService;
use App\Services\Marketing\GrowthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MarketingController extends Controller
{
    public function __construct(
        private readonly CampaignService $campaigns,
        private readonly CampaignRuleEngine $rules,
        private readonly GrowthService $growth,
    ) {}

    public function index(Request $request): View
    {
        return view('admin.campaigns.index', $this->campaigns->index(
            (int) $request->query('page', 1),
            trim((string) $request->query('search', '')),
            (string) $request->query('status', ''),
            (string) $request->query('type', ''),
        ));
    }

    public function create(): View
    {
        return view('admin.campaigns.create', [
            'types' => CampaignService::TYPES,
            'statuses' => CampaignService::STATUSES,
            'ruleCatalogue' => $this->rules->catalogue(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCampaign($request);

        $campaign = DB::transaction(function () use ($validated): Campaign {
            $campaign = Campaign::create([
                'name' => (string) $validated['name'],
                'slug' => $this->campaigns->uniqueSlug((string) $validated['name']),
                'type' => (string) $validated['type'],
                'description' => $validated['description'] ?? null,
                'rules' => $validated['rules'] ?? null,
                'audience' => ['segment_ids' => array_map('intval', (array) ($validated['segment_ids'] ?? []))],
                'budget' => $this->nullableFloat($validated['budget'] ?? null),
                'discount_value' => (float) ($validated['discount_value'] ?? 0),
                'discount_type' => (string) ($validated['discount_type'] ?? 'percentage'),
                'usage_limit' => $this->nullableInt($validated['usage_limit'] ?? null),
                'per_user_limit' => $this->nullableInt($validated['per_user_limit'] ?? null),
                'status' => (string) $validated['status'],
                'starts_at' => $validated['starts_at'] ?? null,
                'ends_at' => $validated['ends_at'] ?? null,
            ]);

            $this->campaigns->syncScope(
                $campaign,
                array_map('intval', (array) ($validated['product_ids'] ?? [])),
                array_map('intval', (array) ($validated['category_ids'] ?? [])),
            );

            return $campaign;
        });

        app(AuditLogger::class)->log('campaign.created', $campaign, [], ['name' => $campaign->name], auth('admin')->id());

        return redirect()->route('admin.campaigns.show', $campaign)->with('success', 'Kampanye "'.$campaign->name.'" berhasil dibuat.');
    }

    public function edit(Campaign $campaign): View
    {
        $campaign->loadMissing(['products:id', 'categories:id']);

        return view('admin.campaigns.edit', [
            'campaign' => $this->campaigns->summarise($campaign),
            'model' => $campaign,
            'selectedProducts' => $campaign->products->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'selectedCategories' => $campaign->categories->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'selectedRules' => is_array($campaign->rules) ? $campaign->rules : [],
            'types' => CampaignService::TYPES,
            'statuses' => CampaignService::STATUSES,
            'ruleCatalogue' => $this->rules->catalogue(),
        ]);
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        $validated = $this->validateCampaign($request, $campaign);
        $before = $campaign->only(['name', 'type', 'status', 'budget', 'starts_at', 'ends_at']);

        DB::transaction(function () use ($campaign, $validated): void {
            $campaign->forceFill([
                'name' => (string) $validated['name'],
                'type' => (string) $validated['type'],
                'description' => $validated['description'] ?? null,
                'rules' => $validated['rules'] ?? null,
                'audience' => ['segment_ids' => array_map('intval', (array) ($validated['segment_ids'] ?? []))],
                'budget' => $this->nullableFloat($validated['budget'] ?? null),
                'discount_value' => (float) ($validated['discount_value'] ?? 0),
                'discount_type' => (string) ($validated['discount_type'] ?? 'percentage'),
                'usage_limit' => $this->nullableInt($validated['usage_limit'] ?? null),
                'per_user_limit' => $this->nullableInt($validated['per_user_limit'] ?? null),
                'status' => (string) $validated['status'],
                'starts_at' => $validated['starts_at'] ?? null,
                'ends_at' => $validated['ends_at'] ?? null,
            ])->save();

            $this->campaigns->syncScope(
                $campaign,
                array_map('intval', (array) ($validated['product_ids'] ?? [])),
                array_map('intval', (array) ($validated['category_ids'] ?? [])),
            );
        });

        app(AuditLogger::class)->log('campaign.updated', $campaign, $before, $campaign->only(['name', 'type', 'status', 'budget', 'starts_at', 'ends_at']), auth('admin')->id());

        return redirect()->route('admin.campaigns.show', $campaign)->with('success', 'Kampanye berhasil diperbarui.');
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        $snapshot = ['name' => (string) $campaign->name, 'status' => (string) $campaign->status, 'revenue' => (float) $campaign->revenue];

        $campaign->delete();

        app(AuditLogger::class)->log('campaign.deleted', null, $snapshot, [], auth('admin')->id());

        return redirect()->route('admin.campaigns.index')->with('success', 'Kampanye dihapus.');
    }

    public function show(Campaign $campaign): View
    {
        return view('admin.campaigns.show', $this->campaigns->show($campaign));
    }

    public function toggle(Campaign $campaign): RedirectResponse
    {
        $updated = $this->campaigns->toggle($campaign, auth('admin')->id());

        return back()->with(
            'success',
            $updated->status === 'active'
                ? 'Kampanye diaktifkan.'
                : 'Kampanye dijeda. Pelanggan tidak akan melihatnya lagi.',
        );
    }

    public function abandonedCarts(Request $request): View
    {
        return view('admin.abandoned-carts', $this->growth->abandonedCarts(
            (int) $request->query('page', 1),
            (string) $request->query('filter', ''),
            trim((string) $request->query('search', '')),
        ));
    }

    public function sendAbandonedReminder(Request $request, AbandonedCart $cart): RedirectResponse
    {
        $result = $this->growth->sendReminder($cart, auth('admin')->id());

        return back()->with($result['queued'] ? 'success' : 'error', $result['reason']);
    }

    /**
     * Pengingat abandoned cart bertahap (tahap 1-3 + kupon pemulih di akhir).
     * Untuk integrator: daftarkan route POST sendiri bila dibutuhkan.
     */
    public function remindStaged(Request $request, AbandonedCart $cart): RedirectResponse
    {
        $result = $this->growth->sendStagedReminder($cart, auth('admin')->id(), $request->boolean('force'));

        return back()->with($result['queued'] ? 'success' : 'error', $result['reason']);
    }

    /**
     * Panel retensi (abandoned, voucher ultah, banner segmen, flash reminder).
     * Untuk integrator: daftarkan route GET sendiri bila dibutuhkan.
     * Memakai ulang view campaigns.index agar tanpa file blade baru.
     */
    public function retention(Request $request): View
    {
        return view('admin.campaigns.index', $this->campaigns->index(
            (int) $request->query('page', 1),
            trim((string) $request->query('search', '')),
            (string) $request->query('status', ''),
            (string) $request->query('type', ''),
        ) + ['retention' => $this->growth->retentionOverview()]);
    }

    public function referrals(Request $request): View
    {
        return view('admin.referrals', $this->growth->referrals(
            (int) $request->query('page', 1),
            trim((string) $request->query('search', '')),
        ));
    }

    public function affiliates(Request $request): View
    {
        return view('admin.affiliates', $this->growth->affiliates(
            (int) $request->query('page', 1),
            (string) $request->query('status', ''),
            trim((string) $request->query('search', '')),
        ));
    }

    public function updateAffiliates(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', Rule::exists('affiliates', 'id')],
            'status' => ['required', Rule::in(['pending', 'active', 'rejected', 'suspended'])],
        ]);

        $before = Affiliate::query()->whereIn('id', $validated['ids'])->pluck('status', 'id')->all();

        Affiliate::query()->whereIn('id', $validated['ids'])->update([
            'status' => (string) $validated['status'],
            'approved_at' => $validated['status'] === 'active' ? now() : null,
        ]);

        app(AuditLogger::class)->log('affiliate.status_changed', null, ['status' => $before], ['status' => $validated['status']], auth('admin')->id());

        return back()->with('success', count($validated['ids']).' affiliate diperbarui menjadi '.$validated['status'].'.');
    }

    public function notifications(Request $request): View
    {
        return view('admin.notifications.index', $this->growth->notificationCentre(
            (int) $request->query('page', 1),
            (string) $request->query('category', ''),
            (string) $request->query('read', ''),
        ) + ['devices' => $this->growth->deviceCount()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCampaign(Request $request, ?Campaign $campaign = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in(array_keys(CampaignService::TYPES))],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(array_keys(CampaignService::STATUSES))],
            'discount_type' => ['nullable', Rule::in(['percentage', 'flat'])],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:10000000000'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'per_user_limit' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', Rule::exists('products', 'id')],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', Rule::exists('categories', 'id')],
            'segment_ids' => ['nullable', 'array'],
            'segment_ids.*' => ['integer', Rule::exists('customer_segments', 'id')],
            'rules' => ['nullable', 'array'],
        ]);

        $rules = $this->rules->normalise($validated['rules'] ?? []);
        [$cleanRules, $errors] = $this->rules->validate($rules);

        if ($errors !== []) {
            throw new \Illuminate\Validation\ValidationException(
                $request,
                ['rules' => array_values($errors)],
            );
        }

        $validated['rules'] = $cleanRules;

        if (($cleanRules['product_ids'] ?? []) === [] && ($cleanRules['category_ids'] ?? []) === []
            && ($validated['product_ids'] ?? []) === [] && ($validated['category_ids'] ?? []) === []) {
            $validated['rules']['require_scope'] = true;
        }

        return $validated;
    }

    private function nullableFloat(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
