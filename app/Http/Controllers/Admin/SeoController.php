<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PseoPage;
use App\Models\Redirect;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
use App\Services\Seo\PseoService;
use App\Services\Seo\SitemapStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SeoController extends Controller
{
    public function __construct(
        private readonly PseoService $pseo,
        private readonly SitemapStatusService $sitemaps,
    ) {}

    public function index(): View
    {
        return view('admin.seo.index', [
            'settings' => $this->settings(),
            'counts' => $this->counts(),
            'sitemap' => $this->sitemaps->status(),
            'topPages' => $this->topPages(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'seo_title' => ['nullable', 'string', 'max:120'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'seo_keywords' => ['nullable', 'string', 'max:255'],
            'seo_og_image' => ['nullable', 'url', 'max:500'],
            'seo_robots' => ['nullable', 'string', 'in:index,follow,noindex,follow,noindex,nofollow'],
            'seo_canonical_host' => ['nullable', 'string', 'max:160'],
            'seo_verification_google' => ['nullable', 'string', 'max:255'],
            'seo_verification_bing' => ['nullable', 'string', 'max:255'],
            'seo_organization_name' => ['nullable', 'string', 'max:120'],
            'seo_organization_logo' => ['nullable', 'url', 'max:500'],
            'seo_twitter_site' => ['nullable', 'string', 'max:60'],
            'seo_indexnow_key' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($validated as $key => $value) {
            SystemSetting::set($key, $value === '' ? null : (string) $value);
        }

        \Cache::forget('whitelabel_branding');

        app(AuditLogger::class)->log('seo.updated', null, [], ['keys' => array_keys($validated)], auth('admin')->id());

        return back()->with('success', 'Pengaturan SEO disimpan.');
    }

    public function sitemaps(): View
    {
        return view('admin.seo.sitemaps', [
            'status' => $this->sitemaps->status(),
            'sitemaps' => $this->sitemaps->list(),
            'robots' => $this->sitemaps->robotsPreview(),
        ]);
    }

    public function redirects(Request $request): View
    {
        $query = Redirect::query();

        if (($search = trim((string) $request->query('search', ''))) !== '') {
            $query->where(fn ($q) => $q->where('from_path', 'like', '%'.$search.'%')->orWhere('to_path', 'like', '%'.$search.'%'));
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 25;
        $total = (int) (clone $query)->count();

        $rows = $query->orderByDesc('id')->forPage($page, $perPage)->get()
            ->map(fn (Redirect $redirect): array => [
                'id' => (int) $redirect->id,
                'from_path' => (string) $redirect->from_path,
                'to_path' => (string) $redirect->to_path,
                'status_code' => (int) $redirect->status_code,
                'is_active' => (bool) $redirect->is_active,
                'hit_count' => (int) $redirect->hit_count,
                'last_hit_at' => (string) ($redirect->last_hit_at?->format('Y-m-d H:i') ?? ''),
            ])
            ->all();

        return view('admin.seo.redirects', [
            'rows' => $rows,
            'total_hits' => (int) Redirect::query()->sum('hit_count'),
            'active_count' => (int) Redirect::query()->where('is_active', true)->count(),
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    public function storeRedirect(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_path' => ['required', 'string', 'max:255', 'regex:/^\/[^\s]*$/', Rule::unique('redirects', 'from_path')],
            'to_path' => ['required', 'string', 'max:255', 'regex:/^\/[^\s]*$|^(https?:\/\/)/'],
            'status_code' => ['required', 'integer', Rule::in([301, 302, 307, 308])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validated['from_path'] === $validated['to_path']) {
            return back()->with('error', 'Path sumber dan tujuan tidak boleh sama.')->withInput();
        }

        $redirect = Redirect::create([
            'from_path' => (string) $validated['from_path'],
            'to_path' => (string) $validated['to_path'],
            'status_code' => (int) $validated['status_code'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'hit_count' => 0,
        ]);

        app(AuditLogger::class)->log('redirect.created', $redirect, [], ['from' => $redirect->from_path], auth('admin')->id());

        return back()->with('success', 'Redirect '.($redirect->is_active ? '' : '(nonaktif) ').'dari '.$redirect->from_path.' dibuat.');
    }

    public function destroyRedirect(Redirect $redirect): RedirectResponse
    {
        $snapshot = ['from' => (string) $redirect->from_path, 'to' => (string) $redirect->to_path];
        $redirect->delete();

        app(AuditLogger::class)->log('redirect.deleted', null, $snapshot, [], auth('admin')->id());

        return back()->with('success', 'Redirect dihapus.');
    }

    public function pseo(): View
    {
        return view('admin.seo.pseo', [
            'overview' => $this->pseo->overview(),
            'pages' => $this->pseo->listPages(
                (int) request()->query('page', 1),
                (string) request()->query('state', ''),
                (string) request()->query('template', ''),
            ),
        ]);
    }

    public function updatePseo(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'quality_threshold' => ['required', 'integer', 'min:0', 'max:100'],
            'min_products' => ['required', 'integer', 'min:1', 'max:200'],
            'products_per_page' => ['required', 'integer', 'min:6', 'max:48'],
            'auto_publish' => ['nullable', 'boolean'],
        ]);

        $this->pseo->applySettings($validated);

        app(AuditLogger::class)->log('pseo.settings_updated', null, [], $validated, auth('admin')->id());

        return back()->with('success', 'Pengaturan PSEO disimpan. Ambang skor kini '.$validated['quality_threshold'].'.');
    }

    public function generatePseo(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'template_code' => ['required', Rule::in(array_keys(PseoService::TEMPLATES))],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        if (! $this->pseo->isEnabled()) {
            return back()->with('error', 'PSEO dinonaktifkan. Aktifkan terlebih dahulu pada pengaturan PSEO.');
        }

        $this->pseo->ensureTemplates();

        $result = $this->pseo->generate(
            (string) $validated['template_code'],
            (int) ($validated['limit'] ?? 50),
            auth('admin')->id(),
        );

        return back()->with(
            'success',
            $result['created'] === 0 && $result['updated'] === 0
                ? 'Tidak ada kandidat baru yang memenuhi ambang minimal produk. '. $result['skipped'].' kandidat dilewati karena sudah ada atau tidak cukup produk.'
                : $result['created'].' halaman dibuat, '.$result['updated'].' diperbarui, '.$result['skipped'].' dilewati. Semua halaman baru berstatus noindex sampai ditinjau.',
        );
    }

    public function reviewPseoPage(PseoPage $page): RedirectResponse
    {
        $this->pseo->review($page, auth('admin')->id());

        return back()->with(
            'success',
            'Halaman ditandai sudah ditinjau dengan skor '.$page->quality_score.'. Still noindex sampai diterbitkan.',
        );
    }

    public function publishPseoPage(PseoPage $page): RedirectResponse
    {
        $threshold = $this->pseo->threshold();

        if ((int) $page->quality_score < $threshold) {
            return back()->with('error', 'Skor '.$page->quality_score.' di bawah ambang '.$threshold.'. Perbaiki dulu killer di halaman ini.');
        }

        if ((int) $page->product_count < $this->pseo->minProducts()) {
            return back()->with('error', 'Halaman hanya punya '.$page->product_count.' produk nyata, ambang minimum '.$this->pseo->minProducts().'.');
        }

        if ($page->reviewed_at === null) {
            $this->pseo->review($page, auth('admin')->id());
        }

        $this->pseo->publish($page->refresh(), auth('admin')->id());

        return back()->with('success', 'Halaman diterbitkan dengan indexability=index.');
    }

    public function destroyPseoPage(PseoPage $page): RedirectResponse
    {
        $this->pseo->delete($page, auth('admin')->id());

        return back()->with('success', 'Halaman PSEO dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        $keys = [
            'seo_title', 'seo_description', 'seo_keywords', 'seo_og_image', 'seo_robots',
            'seo_canonical_host', 'seo_verification_google', 'seo_verification_bing',
            'seo_organization_name', 'seo_organization_logo', 'seo_twitter_site', 'seo_indexnow_key',
        ];

        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = (string) (SystemSetting::get($key) ?? '');
        }

        $settings['seo_robots'] = $settings['seo_robots'] !== '' ? $settings['seo_robots'] : 'index,follow';
        $settings['seo_canonical_host'] = $settings['seo_canonical_host'] !== '' ? $settings['seo_canonical_host'] : (string) config('app.url');

        return $settings;
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        return [
            'products' => (int) \App\Models\Product::query()->where('status', 'approved')->count(),
            'categories' => (int) \App\Models\Category::query()->where('status', true)->count(),
            'brands' => (int) \App\Models\Brand::query()->where('status', true)->count(),
            'shops' => (int) \App\Models\Shop::query()->where('status', 'active')->count(),
            'posts' => $this->countPosts(),
            'pseo_pages' => (int) PseoPage::query()->count(),
            'pseo_published' => (int) PseoPage::query()->where('state', 'published')->count(),
            'redirects' => (int) Redirect::query()->count(),
        ];
    }

    private function countPosts(): int
    {
        try {
            return (int) \App\Models\BlogPost::query()->where('is_published', true)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topPages(): array
    {
        return PseoPage::query()
            ->orderByDesc('quality_score')
            ->limit(5)
            ->get(['title', 'url', 'quality_score', 'state', 'indexability', 'product_count'])
            ->map(fn (PseoPage $page): array => [
                'title' => (string) $page->title,
                'url' => (string) $page->url,
                'quality_score' => (int) $page->quality_score,
                'state' => (string) $page->state,
                'indexability' => (string) $page->indexability,
                'product_count' => (int) $page->product_count,
            ])
            ->all();
    }
}
