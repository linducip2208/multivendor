<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\PlatformFeature;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\Translation;
use App\Services\AuditLogger;
use App\Support\Currency;
use App\Support\Feature;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Platform settings.
 *
 * Every action validates, writes through `SystemSetting::set()` and then
 * invalidates the cache of whatever actually reads that key. Roles and
 * permissions are the one exception: they are real rows in `roles` /
 * `permissions` / `permission_role`, not opaque setting strings.
 */
class CmsController extends Controller
{
    public const OFFLINE_METHODS = [
        'bank_transfer' => 'Transfer Bank',
        'cod' => 'Cash on Delivery',
        'manual' => 'Pembayaran Manual',
    ];

    public const EMAIL_TEMPLATES = [
        'order_confirmation' => 'Konfirmasi Pesanan',
        'order_shipped' => 'Pesanan Dikirim',
        'order_delivered' => 'Pesanan Sampai',
        'order_canceled' => 'Pesanan Dibatalkan',
        'welcome' => 'Email Selamat Datang',
        'password_reset' => 'Atur Ulang Kata Sandi',
        'invoice' => 'Faktur',
        'withdraw_approved' => 'Penarikan Disetujui',
        'vendor_registration' => 'Pendaftaran Vendor',
    ];

    public const PAGES = [
        'about' => 'Tentang Kami',
        'terms' => 'Syarat & Ketentuan',
        'privacy' => 'Kebijakan Privasi',
        'return' => 'Kebijakan Pengembalian',
        'faq' => 'Pertanyaan Umum',
    ];

    public const ROLE_MODULES = [
        'vendors' => 'Vendor',
        'products' => 'Produk',
        'categories' => 'Kategori',
        'brands' => 'Brand',
        'orders' => 'Pesanan',
        'coupons' => 'Kupon',
        'flashdeals' => 'Flash Deal',
        'blog' => 'Blog',
        'banners' => 'Banner',
        'customers' => 'Pelanggan',
        'delivery-men' => 'Kurir',
        'reports' => 'Laporan',
        'settings' => 'Pengaturan',
        'providers' => 'Integrasi',
    ];

    public const ROLE_ACTIONS = [
        'view' => 'Lihat',
        'create' => 'Tambah',
        'edit' => 'Edit',
        'delete' => 'Hapus',
    ];

    public const MENUS = [
        'main' => 'Menu Utama',
        'footer' => 'Menu Footer',
        'sidebar' => 'Sidebar Katalog',
    ];

    public function offlinePayment(): View
    {
        $methods = [];

        foreach (self::OFFLINE_METHODS as $key => $label) {
            $methods[] = [
                'key' => $key,
                'label' => $label,
                'active' => (string) SystemSetting::get('payment_'.$key.'_active', '0') === '1',
                'details' => (string) SystemSetting::get('payment_'.$key.'_details', ''),
                'instructions' => (string) SystemSetting::get('payment_'.$key.'_instructions', ''),
            ];
        }

        return view('admin.offline-payment.index', [
            'methods' => $methods,
            'requires_proof' => (string) SystemSetting::get('payment_offline_requires_proof', '0') === '1',
            'expiry_hours' => (int) SystemSetting::get('payment_offline_expiry_hours', 24),
        ]);
    }

    public function updateOfflinePayment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'methods' => ['required', 'array'],
            'methods.*.active' => ['nullable', 'boolean'],
            'methods.*.details' => ['nullable', 'string', 'max:500'],
            'methods.*.instructions' => ['nullable', 'string', 'max:2000'],
            'requires_proof' => ['nullable', 'boolean'],
            'expiry_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
        ]);

        foreach (self::OFFLINE_METHODS as $key => $_) {
            $entry = $validated['methods'][$key] ?? [];
            SystemSetting::set('payment_'.$key.'_active', ! empty($entry['active']) ? '1' : '0');
            SystemSetting::set('payment_'.$key.'_details', $entry['details'] ?? null);
            SystemSetting::set('payment_'.$key.'_instructions', $entry['instructions'] ?? null);
        }

        SystemSetting::set('payment_offline_requires_proof', $request->boolean('requires_proof') ? '1' : '0');
        SystemSetting::set('payment_offline_expiry_hours', (string) (int) ($validated['expiry_hours'] ?? 24));

        $this->flush();

        return back()->with('success', 'Metode pembayaran offline disimpan.');
    }

    public function emailTemplates(): View
    {
        $templates = [];

        foreach (self::EMAIL_TEMPLATES as $key => $label) {
            $templates[] = [
                'key' => $key,
                'label' => $label,
                'subject' => (string) SystemSetting::get('email_'.$key.'_subject', ''),
                'body' => (string) SystemSetting::get('email_'.$key.'_body', ''),
                'configured' => (string) SystemSetting::get('email_'.$key.'_subject', '') !== '',
            ];
        }

        return view('admin.email-templates.index', [
            'templates' => $templates,
            'variables' => ['{{name}}', '{{email}}', '{{order_number}}', '{{total}}', '{{shop_name}}', '{{tracking_number}}', '{{store_name}}'],
        ]);
    }

    public function updateEmailTemplates(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', Rule::in(array_keys(self::EMAIL_TEMPLATES))],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $key = (string) $validated['key'];

        SystemSetting::set('email_'.$key.'_subject', (string) $validated['subject']);
        SystemSetting::set('email_'.$key.'_body', (string) $validated['body']);

        app(AuditLogger::class)->log('email_template.updated', null, [], ['key' => $key], auth('admin')->id());

        return back()->with('success', 'Template '.self::EMAIL_TEMPLATES[$key].' disimpan.');
    }

    public function pages(): View
    {
        $pages = [];

        foreach (self::PAGES as $key => $label) {
            $pages[] = [
                'key' => $key,
                'label' => $label,
                'content' => (string) SystemSetting::get('page_'.$key, ''),
                'configured' => (string) SystemSetting::get('page_'.$key, '') !== '',
                'blocks' => $this->pageBlocks($key),
            ];
        }

        return view('admin.pages.index', ['pages' => $pages, 'versions' => $this->pageVersions()]);
    }

    public function updatePages(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pages' => ['required', 'array'],
            'pages.*' => ['nullable', 'string', 'max:100000'],
            'blocks' => ['nullable', 'array'],
            'blocks.*' => ['nullable', 'string', 'max:200000'],
        ]);

        $before = [];
        foreach (self::PAGES as $key => $_) {
            $before[$key] = mb_substr((string) SystemSetting::get('page_'.$key, ''), 0, 200);
        }
        foreach (self::PAGES as $key => $_) {
            SystemSetting::set('page_'.$key, $validated['pages'][$key] ?? null);
        }
        $blocksByKey = $this->persistPageBlocks((array) ($validated['blocks'] ?? []));
        $this->snapshotPageVersions($validated['pages'], $blocksByKey);

        $this->flush();

        $after = [];
        foreach (self::PAGES as $key => $_) {
            $after[$key] = mb_substr((string) SystemSetting::get('page_'.$key, ''), 0, 200);
        }
        app(AuditLogger::class)->log('cms.pages_updated', null, $before, $after, auth('admin')->id());

        return back()->with('success', 'Halaman statis disimpan. Riwayat 10 versi terakhir tersimpan otomatis.');
    }

    /**
     * Versioning halaman statis: 10 snapshot terakhir di system_settings.
     *
     * @return list<array{at: string, actor_id: int|null, pages: array}>
     */
    public function pageVersions(): array
    {
        try {
            $raw = SystemSetting::get('cms_page_versions', '');
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];

            return is_array($decoded) ? array_values(array_filter($decoded, fn (mixed $v): bool => is_array($v) && isset($v['at']))) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Pulihkan satu versi halaman dari riwayat (10 snapshot terakhir).
     * Di-wiring ke POST admin.pages.restore (lihat catatan integrasi).
     */
    public function restorePageVersion(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'index' => ['required', 'integer', 'min:0', 'max:9'],
        ]);

        $history = $this->pageVersions();
        $index = (int) $validated['index'];
        if (! isset($history[$index]) || ! is_array($history[$index]['pages'] ?? null)) {
            return back()->withErrors(['index' => 'Versi riwayat tidak ditemukan.']);
        }

        $snapshot = $history[$index];
        $before = [];
        foreach (self::PAGES as $key => $_) {
            $before[$key] = mb_substr((string) SystemSetting::get('page_'.$key, ''), 0, 200);
        }

        $pages = [];
        foreach (self::PAGES as $key => $_) {
            $value = $snapshot['pages'][$key] ?? null;
            $pages[$key] = is_string($value) ? mb_substr($value, 0, 100000) : null;
            SystemSetting::set('page_'.$key, $pages[$key]);
        }

        $blocksByKey = [];
        foreach (self::PAGES as $key => $_) {
            $rawBlocks = $snapshot['blocks'][$key] ?? null;
            if (is_array($rawBlocks)) {
                $normalized = \App\Services\Cms\PageBlockRenderer::normalize($rawBlocks);
                $blocksByKey[$key] = $normalized;
                SystemSetting::set('page_blocks_'.$key, json_encode($normalized, JSON_UNESCAPED_UNICODE));
            } else {
                $blocksByKey[$key] = $this->pageBlocks($key);
            }
        }

        $this->snapshotPageVersions($pages, $blocksByKey);
        $this->flush();

        $after = [];
        foreach (self::PAGES as $key => $_) {
            $after[$key] = mb_substr((string) SystemSetting::get('page_'.$key, ''), 0, 200);
        }
        app(AuditLogger::class)->log('cms.pages_restored', null, $before, $after + ['restored_index' => $index, 'restored_at' => (string) ($snapshot['at'] ?? '')], auth('admin')->id());

        return back()->with('success', 'Versi tanggal '.($snapshot['at'] ?? '—').' dipulihkan dan snapshot baru dibuat.');
    }

    /**
     * Blok page builder tersimpan (kanonis, aman untuk editor).
     *
     * @return list<array<string, mixed>>
     */
    private function pageBlocks(string $key): array
    {
        return \App\Services\Cms\PageBlockRenderer::blocksFor($key);
    }

    /**
     * Simpan blok per halaman dari field `blocks[{key}]` (string JSON).
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, list<array<string, mixed>>>
     */
    private function persistPageBlocks(array $raw): array
    {
        $out = [];
        foreach (self::PAGES as $key => $_) {
            if (! array_key_exists($key, $raw)) {
                $out[$key] = $this->pageBlocks($key);
                continue;
            }
            $value = $raw[$key];
            if ($value === null || (is_string($value) && trim($value) === '')) {
                $out[$key] = [];
                SystemSetting::set('page_blocks_'.$key, json_encode([], JSON_UNESCAPED_UNICODE));
                continue;
            }
            $normalized = \App\Services\Cms\PageBlockRenderer::normalize(is_string($value) ? $value : (array) $value);
            $out[$key] = $normalized;
            SystemSetting::set('page_blocks_'.$key, json_encode($normalized, JSON_UNESCAPED_UNICODE));
        }

        return $out;
    }

    /** @param array<string, mixed> $pages */
    private function snapshotPageVersions(array $pages, array $blocksByKey = []): void
    {
        try {
            $history = $this->pageVersions();
            if ($blocksByKey === []) {
                foreach (self::PAGES as $key => $_) {
                    $blocksByKey[$key] = $this->pageBlocks($key);
                }
            }
            $excerpt = function (mixed $v): string {
                if (is_array($v)) {
                    $v = json_encode($v, JSON_UNESCAPED_UNICODE) ?: '';
                }

                return mb_substr((string) $v, 0, 5000);
            };
            array_unshift($history, ['at' => now()->format('Y-m-d H:i:s'), 'actor_id' => auth('admin')->id(), 'pages' => array_map($excerpt, $pages), 'blocks' => array_map(fn ($blocks): array => array_slice(is_array($blocks) ? $blocks : [], 0, 30), $blocksByKey)]);
            SystemSetting::set('cms_page_versions', json_encode(array_slice($history, 0, 10), JSON_UNESCAPED_UNICODE));
        } catch (\Throwable) {
        }
    }

    public function menus(): View
    {
        $menus = [];

        foreach (self::MENUS as $key => $label) {
            $items = SystemSetting::get('menu_'.$key);
            $decoded = is_string($items) ? json_decode($items, true) : (is_array($items) ? $items : []);
            $decoded = is_array($decoded) ? $decoded : [];

            $menus[] = [
                'key' => $key,
                'label' => $label,
                'items' => array_map(fn (mixed $item): array => [
                    'label' => is_array($item) ? (string) ($item['label'] ?? '') : (string) $item,
                    'url' => is_array($item) ? (string) ($item['url'] ?? '') : '',
                    'target' => is_array($item) && ($item['target'] ?? '_self') === '_blank' ? '_blank' : '_self',
                    'children' => is_array($item) && isset($item['children']) && is_array($item['children'])
                        ? array_values(array_filter(array_map(fn (mixed $child): array => [
                            'label' => is_array($child) ? (string) ($child['label'] ?? '') : '',
                            'url' => is_array($child) ? (string) ($child['url'] ?? '') : '',
                            'target' => is_array($child) && ($child['target'] ?? '_self') === '_blank' ? '_blank' : '_self',
                        ], $item['children']), fn (array $child): bool => $child['label'] !== '' || $child['url'] !== ''))
                        : [],
                ], $decoded),
            ];
        }

        return view('admin.menus.index', [
            'menus' => $menus,
            'presets' => $this->menuPresets(),
        ]);
    }

    public function updateMenus(Request $request): RedirectResponse
    {
        $urlRule = function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value) || ! \App\Services\Cms\MenuRenderer::safeUrl(trim($value))) {
                $fail('URL harus relatif (/tentang-kami, #bagian) atau http(s) absolut.');
            }
        };

        $validated = $request->validate([
            'key' => ['required', Rule::in(array_keys(self::MENUS))],
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.label' => ['required', 'string', 'max:80'],
            'items.*.url' => ['required', 'string', 'max:500', $urlRule],
            'items.*.target' => ['nullable', Rule::in(['_self', '_blank'])],
            'items.*.children' => ['nullable', 'array', 'max:50'],
            'items.*.children.*.label' => ['required_with:items.*.children', 'string', 'max:80'],
            'items.*.children.*.url' => ['required_with:items.*.children', 'string', 'max:500', $urlRule],
            'items.*.children.*.target' => ['nullable', Rule::in(['_self', '_blank'])],
        ]);

        $count = count((array) ($validated['items'] ?? []));
        foreach ((array) ($validated['items'] ?? []) as $item) {
            $count += count((array) ($item['children'] ?? []));
        }
        if ($count > 50) {
            return back()->withErrors(['items' => 'Total item menu (induk + anak) maksimal 50.'])->withInput();
        }

        $clean = function (array $item): array {
            return [
                'label' => trim((string) $item['label']),
                'url' => trim((string) $item['url']),
                'target' => (string) ($item['target'] ?? '_self') === '_blank' ? '_blank' : '_self',
            ];
        };

        $items = [];
        foreach (array_values((array) ($validated['items'] ?? [])) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $row = $clean($item);
            if ($row['label'] === '' && $row['url'] === '') {
                continue;
            }
            $children = [];
            foreach (array_values((array) ($item['children'] ?? [])) as $child) {
                if (! is_array($child)) {
                    continue;
                }
                $childRow = $clean($child);
                if ($childRow['label'] === '' && $childRow['url'] === '') {
                    continue;
                }
                $children[] = $childRow;
            }
            if ($children !== []) {
                $row['children'] = array_slice($children, 0, 50);
            }
            $items[] = $row;
        }

        SystemSetting::set('menu_'.(string) $validated['key'], json_encode(array_slice($items, 0, 50), JSON_UNESCAPED_UNICODE));

        // Invalidasi cache menu + branding tanpa memanggil SystemSetting::flush()
        // (metode tersebut tidak ada di model dan akan fatal bila dipanggil).
        Cache::forget('whitelabel_branding');
        \App\Services\Cms\MenuRenderer::flush((string) $validated['key']);
        try {
            Currency::flush();
            Feature::flush();
        } catch (\Throwable) {
        }

        return back()->with('success', 'Menu '.self::MENUS[$validated['key']].' disimpan dengan '.count($items).' item.');
    }

    public function contacts(): View
    {
        return view('admin.contacts.index', [
            'settings' => [
                'contact_address' => (string) SystemSetting::get('contact_address', ''),
                'contact_email' => (string) SystemSetting::get('contact_email', ''),
                'contact_phone' => (string) SystemSetting::get('contact_phone', ''),
                'contact_whatsapp' => (string) SystemSetting::get('contact_whatsapp', ''),
                'contact_facebook' => (string) SystemSetting::get('contact_facebook', ''),
                'contact_instagram' => (string) SystemSetting::get('contact_instagram', ''),
                'contact_tiktok' => (string) SystemSetting::get('contact_tiktok', ''),
                'contact_hours' => (string) SystemSetting::get('contact_hours', ''),
            ],
        ]);
    }

    public function updateContacts(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'contact_address' => ['nullable', 'string', 'max:500'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_whatsapp' => ['nullable', 'string', 'max:40'],
            'contact_facebook' => ['nullable', 'string', 'max:120'],
            'contact_instagram' => ['nullable', 'string', 'max:120'],
            'contact_tiktok' => ['nullable', 'string', 'max:120'],
            'contact_hours' => ['nullable', 'string', 'max:120'],
        ]);

        $this->persist($validated);

        return back()->with('success', 'Kontak toko disimpan.');
    }

    public function helpTopics(): View
    {
        $topics = [];

        for ($i = 1; $i <= 10; $i++) {
            $topics[] = [
                'position' => $i,
                'title' => (string) SystemSetting::get('help_topic_'.$i.'_title', ''),
                'body' => (string) SystemSetting::get('help_topic_'.$i.'_body', ''),
            ];
        }

        return view('admin.help-topics.index', ['topics' => $topics]);
    }

    public function updateHelpTopics(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'topics' => ['required', 'array', 'min:1', 'max:10'],
            'topics.*.title' => ['nullable', 'string', 'max:160'],
            'topics.*.body' => ['nullable', 'string', 'max:5000'],
        ]);

        foreach (range(1, 10) as $position) {
            $entry = $validated['topics'][$position] ?? [];
            SystemSetting::set('help_topic_'.$position.'_title', $entry['title'] ?? null);
            SystemSetting::set('help_topic_'.$position.'_body', $entry['body'] ?? null);
        }

        $this->flush();

        return back()->with('success', 'Topik bantuan disimpan.');
    }

    public function language(): View
    {
        $keys = [
            'Dashboard', 'Products', 'Categories', 'Brands', 'Cart', 'Checkout', 'Orders', 'Login',
            'Register', 'Logout', 'Vendors', 'Customers', 'Coupons', 'Flash Deals', 'Settings',
            'Reports', 'Profile', 'Save', 'Cancel', 'Delete', 'Edit', 'Create', 'Search', 'Filter',
            'Status', 'Actions', 'Total', 'Price', 'Stock', 'Quantity', 'Add to Cart', 'Buy Now',
            'Wishlist', 'Compare', 'Support Tickets', 'Language', 'Currency',
        ];

        $rows = [];
        foreach ($keys as $key) {
            $rows[] = [
                'key' => $key,
                'id' => (string) (SystemSetting::get('lang_id_'.$key, $key) ?? $key),
                'en' => (string) (SystemSetting::get('lang_en_'.$key, $key) ?? $key),
            ];
        }

        return view('admin.language.index', [
            'rows' => $rows,
            'locales' => ['id' => 'Bahasa Indonesia', 'en' => 'English'],
            'default_locale' => (string) SystemSetting::get('default_locale', 'id'),
        ]);
    }

    public function updateLanguage(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'default_locale' => ['required', Rule::in(['id', 'en'])],
            'locales' => ['required', 'array'],
            'locales.id' => ['required', 'array', 'min:1'],
            'locales.id.*' => ['required', 'string', 'max:200'],
            'locales.en' => ['required', 'array', 'min:1'],
            'locales.en.*' => ['required', 'string', 'max:200'],
        ]);

        SystemSetting::set('default_locale', (string) $validated['default_locale']);

        $keys = array_keys($validated['locales']['id']);
        foreach ($keys as $key) {
            SystemSetting::set('lang_id_'.$key, (string) $validated['locales']['id'][$key]);
            SystemSetting::set('lang_en_'.$key, (string) $validated['locales']['en'][$key]);
        }

        $this->flush();

        return back()->with('success', count($keys).' kunci bahasa disimpan untuk 2 locale.');
    }

    public function currency(): View
    {
        $currency = [
            'currency_code' => (string) SystemSetting::get('currency_code', 'IDR'),
            'currency_symbol' => (string) SystemSetting::get('currency_symbol', 'Rp'),
            'symbol_position' => (string) SystemSetting::get('symbol_position', 'left'),
            'decimal_point' => (int) SystemSetting::get('decimal_point', 0),
            'thousand_separator' => (string) SystemSetting::get('thousand_separator', '.'),
            'decimal_separator' => (string) SystemSetting::get('decimal_separator', ','),
        ];

        return view('admin.currency.index', [
            'settings' => $currency,
            'preview' => Currency::format(1250000.0),
        ]);
    }

    public function updateCurrency(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'currency_code' => ['required', 'string', 'size:3', 'alpha'],
            'currency_symbol' => ['required', 'string', 'max:5'],
            'symbol_position' => ['required', Rule::in(['left', 'right'])],
            'decimal_point' => ['required', 'integer', 'min:0', 'max:4'],
            'thousand_separator' => ['required', 'string', 'max:2'],
            'decimal_separator' => ['required', 'string', 'max:2'],
        ]);

        $this->persist($validated);
        Currency::flush();

        return back()->with('success', 'Format mata uang disimpan. Semua nilai kini dirender ulang.');
    }

    public function translation(Request $request): View
    {
        $query = Translation::query();

        if (($search = trim((string) $request->query('search', ''))) !== '') {
            $query->where(fn ($q) => $q->where('key', 'like', '%'.$search.'%')->orWhere('value', 'like', '%'.$search.'%'));
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 30;
        $total = (int) (clone $query)->count();

        $rows = $query->orderBy('group')->orderBy('key')->forPage($page, $perPage)->get()
            ->map(fn (Translation $translation): array => [
                'id' => (int) $translation->id,
                'key' => (string) $translation->key,
                'group' => (string) $translation->group,
                'locale' => (string) $translation->locale,
                'value' => (string) $translation->value,
            ])
            ->all();

        $groups = Translation::query()->distinct()->orderBy('group')->pluck('group')
            ->map(fn (string $group): array => ['value' => $group, 'label' => Str::headline($group)])->all();

        return view('admin.translation.index', [
            'rows' => $rows,
            'groups' => $groups,
            'locales' => ['id', 'en'],
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    public function updateTranslation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:160', 'regex:/^[A-Za-z0-9_\.\-]+$/'],
            'group' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'values' => ['required', 'array', 'min:1'],
            'values.*' => ['required', 'string', 'max:1000'],
        ]);

        $saved = 0;
        foreach ($validated['values'] as $locale => $value) {
            Translation::updateOrCreate(
                ['locale' => (string) $locale, 'group' => (string) $validated['group'], 'key' => (string) $validated['key']],
                ['value' => (string) $value],
            );
            $saved++;
        }

        $this->flush();

        return back()->with('success', $saved.' terjemahan disimpan untuk "'.$validated['key'].'".');
    }

    public function inhouseShop(): View
    {
        return view('admin.inhouse-shop.index', [
            'settings' => [
                'inhouse_shop_active' => (string) SystemSetting::get('inhouse_shop_active', '0') === '1',
                'inhouse_shop_name' => (string) SystemSetting::get('inhouse_shop_name', ''),
                'inhouse_shop_description' => (string) SystemSetting::get('inhouse_shop_description', ''),
                'inhouse_shop_commission' => (float) SystemSetting::get('inhouse_shop_commission', 0),
            ],
        ]);
    }

    public function updateInhouseShop(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'inhouse_shop_active' => ['nullable', 'boolean'],
            'inhouse_shop_name' => ['required', 'string', 'max:160'],
            'inhouse_shop_description' => ['nullable', 'string', 'max:1000'],
            'inhouse_shop_commission' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        SystemSetting::set('inhouse_shop_active', $request->boolean('inhouse_shop_active') ? '1' : '0');
        SystemSetting::set('inhouse_shop_name', (string) $validated['inhouse_shop_name']);
        SystemSetting::set('inhouse_shop_description', $validated['inhouse_shop_description'] ?? null);
        SystemSetting::set('inhouse_shop_commission', (string) (float) ($validated['inhouse_shop_commission'] ?? 0));

        $this->flush();

        return back()->with('success', 'Pengaturan toko inhouse disimpan.');
    }

    public function vendorSettings(): View
    {
        return view('admin.vendor-settings.index', [
            'settings' => [
                'vendor_registration_open' => (string) SystemSetting::get('vendor_registration_open', '1') === '1',
                'vendor_auto_approve' => (string) SystemSetting::get('vendor_auto_approve', '0') === '1',
                'vendor_default_commission' => (float) SystemSetting::get('vendor_default_commission', 5),
                'vendor_min_withdraw' => (float) SystemSetting::get('vendor_min_withdraw', 50000),
                'vendor_payout_days' => (int) SystemSetting::get('vendor_payout_days', 7),
                'vendor_min_products' => (int) SystemSetting::get('vendor_min_products', 0),
            ],
        ]);
    }

    public function updateVendorSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'vendor_registration_open' => ['nullable', 'boolean'],
            'vendor_auto_approve' => ['nullable', 'boolean'],
            'vendor_default_commission' => ['required', 'numeric', 'min:0', 'max:100'],
            'vendor_min_withdraw' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'vendor_payout_days' => ['required', 'integer', 'min:0', 'max:90'],
            'vendor_min_products' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        SystemSetting::set('vendor_registration_open', $request->boolean('vendor_registration_open') ? '1' : '0');
        SystemSetting::set('vendor_auto_approve', $request->boolean('vendor_auto_approve') ? '1' : '0');
        SystemSetting::set('vendor_default_commission', (string) (float) $validated['vendor_default_commission']);
        SystemSetting::set('vendor_min_withdraw', (string) (float) $validated['vendor_min_withdraw']);
        SystemSetting::set('vendor_payout_days', (string) (int) $validated['vendor_payout_days']);
        SystemSetting::set('vendor_min_products', (string) (int) $validated['vendor_min_products']);

        $this->flush();

        return back()->with('success', 'Pengaturan vendor disimpan.');
    }

    public function smsGateway(): View
    {
        return view('admin.sms-gateway.index', [
            'settings' => [
                'sms_provider' => (string) SystemSetting::get('sms_provider', 'none'),
                'sms_sender_id' => (string) SystemSetting::get('sms_sender_id', ''),
                'sms_has_api_key' => (string) SystemSetting::get('sms_api_key', '') !== '',
                'sms_has_api_secret' => (string) SystemSetting::get('sms_api_secret', '') !== '',
                'sms_template_order' => (string) SystemSetting::get('sms_template_order', ''),
                'sms_template_otp' => (string) SystemSetting::get('sms_template_otp', ''),
            ],
            'providers' => ['none' => 'Nonaktif', 'twilio' => 'Twilio', 'nexmo' => 'Vonage / Nexmo', 'zenziva' => 'Zenziva'],
        ]);
    }

    public function updateSmsGateway(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sms_provider' => ['required', Rule::in(['none', 'twilio', 'nexmo', 'zenziva'])],
            'sms_sender_id' => ['nullable', 'string', 'max:40'],
            'sms_api_key' => ['nullable', 'string', 'max:200'],
            'sms_api_secret' => ['nullable', 'string', 'max:200'],
            'sms_template_order' => ['nullable', 'string', 'max:500'],
            'sms_template_otp' => ['nullable', 'string', 'max:500'],
        ]);

        SystemSetting::set('sms_provider', (string) $validated['sms_provider']);
        SystemSetting::set('sms_sender_id', $validated['sms_sender_id'] ?? null);
        SystemSetting::set('sms_template_order', $validated['sms_template_order'] ?? null);
        SystemSetting::set('sms_template_otp', $validated['sms_template_otp'] ?? null);

        if (($validated['sms_api_key'] ?? '') !== '') {
            SystemSetting::set('sms_api_key', (string) $validated['sms_api_key']);
        }

        if (($validated['sms_api_secret'] ?? '') !== '') {
            SystemSetting::set('sms_api_secret', (string) $validated['sms_api_secret']);
        }

        app(AuditLogger::class)->log('sms_gateway.updated', null, [], ['provider' => $validated['sms_provider']], auth('admin')->id());

        $this->flush();

        return back()->with('success', 'Konfigurasi SMS gateway disimpan. Kredensial lama dipertahankan bila kolom dikosongkan.');
    }

    public function thirdParty(): View
    {
        return view('admin.third-party.index', [
            'recaptcha' => [
                'site_key' => (string) SystemSetting::get('recaptcha_site_key', ''),
                'enabled' => (string) SystemSetting::get('recaptcha_enabled', '0') === '1',
                'has_secret' => (string) SystemSetting::get('recaptcha_secret_key', '') !== '',
            ],
            'map' => [
                'api_key' => (string) SystemSetting::get('map_api_key', ''),
                'provider' => (string) SystemSetting::get('map_provider', 'google'),
            ],
            'social' => [
                'whatsapp_number' => (string) SystemSetting::get('whatsapp_number', ''),
                'whatsapp_message' => (string) SystemSetting::get('whatsapp_message', ''),
                'fb_page_id' => (string) SystemSetting::get('fb_page_id', ''),
            ],
            'analytics' => [
                'ga_id' => (string) SystemSetting::get('ga_id', ''),
                'fb_pixel_id' => (string) SystemSetting::get('fb_pixel_id', ''),
                'gtm_id' => (string) SystemSetting::get('gtm_id', ''),
            ],
        ]);
    }

    public function updateThirdParty(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'recaptcha_site_key' => ['nullable', 'string', 'max:200'],
            'recaptcha_enabled' => ['nullable', 'boolean'],
            'recaptcha_secret_key' => ['nullable', 'string', 'max:200'],
            'map_api_key' => ['nullable', 'string', 'max:200'],
            'map_provider' => ['nullable', Rule::in(['google', 'mapbox', 'openstreetmap'])],
            'whatsapp_number' => ['nullable', 'string', 'max:40'],
            'whatsapp_message' => ['nullable', 'string', 'max:255'],
            'fb_page_id' => ['nullable', 'string', 'max:120'],
            'ga_id' => ['nullable', 'string', 'max:60'],
            'fb_pixel_id' => ['nullable', 'string', 'max:60'],
            'gtm_id' => ['nullable', 'string', 'max:60'],
        ]);

        $this->persist([
            'recaptcha_site_key' => $validated['recaptcha_site_key'] ?? null,
            'map_api_key' => $validated['map_api_key'] ?? null,
            'map_provider' => $validated['map_provider'] ?? null,
            'whatsapp_number' => $validated['whatsapp_number'] ?? null,
            'whatsapp_message' => $validated['whatsapp_message'] ?? null,
            'fb_page_id' => $validated['fb_page_id'] ?? null,
            'ga_id' => $validated['ga_id'] ?? null,
            'fb_pixel_id' => $validated['fb_pixel_id'] ?? null,
            'gtm_id' => $validated['gtm_id'] ?? null,
        ]);

        SystemSetting::set('recaptcha_enabled', $request->boolean('recaptcha_enabled') ? '1' : '0');

        if (($validated['recaptcha_secret_key'] ?? '') !== '') {
            SystemSetting::set('recaptcha_secret_key', (string) $validated['recaptcha_secret_key']);
        }

        $this->flush();

        return back()->with('success', 'Integrasi pihak ketiga disimpan.');
    }

    public function roles(): View
    {
        $roles = Role::query()->with('permissions:id,slug,module')->orderBy('id')->get()
            ->map(fn (Role $role): array => $this->roleRow($role))
            ->all();

        return view('admin.roles.index', [
            'roles' => $roles,
            'modules' => self::ROLE_MODULES,
            'actions' => self::ROLE_ACTIONS,
            'matrix' => $this->permissionMatrix(),
            'menuCoverage' => $this->menuCoverage(),
        ]);
    }

    public function updateRoles(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'roles' => ['required', 'array', 'max:60'],
            'roles.*.name' => ['required', 'string', 'max:60'],
            'roles.*.id' => ['required', 'integer', 'exists:roles,id'],
            'roles.*.system' => ['nullable', 'boolean'],
            'roles.*.permissions' => ['nullable', 'array', 'max:400'],
            'roles.*.permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);

        $touched = 0;

        foreach ($validated['roles'] as $entry) {
            $role = Role::query()->findOrFail((int) $entry['id']);
            $before = ['name' => $role->name, 'permissions' => $role->permissions()->pluck('permissions.id')->all()];

            $role->forceFill(['name' => (string) $entry['name']])->save();

            $role->permissions()->sync(array_map('intval', (array) ($entry['permissions'] ?? [])));
            $touched++;
            app(AuditLogger::class)->log('role.updated', $role, $before, [
                'name' => $role->name,
                'permissions' => array_map('intval', (array) ($entry['permissions'] ?? [])),
            ], auth('admin')->id());
        }

        Permissions::flush();
        $this->flush();

        app(AuditLogger::class)->log('roles.updated', null, [], ['roles' => $touched], auth('admin')->id());

        return back()->with('success', $touched.' peran diperbarui beserta hak aksesnya.');
    }

    public function permissions(): View
    {
        $permissions = Permission::query()
            ->with('roles:id,name')
            ->orderBy('module')
            ->orderBy('slug')
            ->get()
            ->map(fn (Permission $permission): array => [
                'id' => (int) $permission->id,
                'name' => (string) $permission->name,
                'slug' => (string) $permission->slug,
                'module' => (string) $permission->module,
                'description' => (string) ($permission->description ?? ''),
                'group' => (string) $permission->group,
                'roles' => $permission->roles->pluck('name')->all(),
            ])
            ->all();

        $grouped = [];
        foreach ($permissions as $permission) {
            $grouped[$permission['module']][] = $permission;
        }

        return view('admin.permissions.index', [
            'grouped' => $grouped,
            'total' => count($permissions),
            'modules' => array_keys($grouped),
        ]);
    }

    public function updatePermissions(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:400'],
            'ids.*' => ['required', 'integer', 'exists:permissions,id'],
            'name' => ['nullable', 'string', 'max:100'],
        ]);

        if (($validated['name'] ?? '') !== '') {
            Permission::query()->whereIn('id', $validated['ids'])->update(['name' => (string) $validated['name']]);
        }

        Permissions::flush();

        return back()->with('success', count($validated['ids']).' izin diperbarui.');
    }

    /**
     * Data penargetan banner per segmen untuk panel admin.
     * Untuk integrator: daftarkan route GET sendiri bila dibutuhkan.
     * Memakai ulang view banners.index agar tanpa file blade baru.
     *
     * @return array<string, mixed>
     */
    public function bannerSegmentData(): array
    {
        $retensi = app(\App\Services\Marketing\RetentionService::class);

        try {
            $segments = \App\Models\CustomerSegment::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($s): array => ['id' => (int) $s->id, 'name' => (string) $s->name])
                ->all();
        } catch (\Throwable) {
            $segments = [];
        }

        return [
            'segmentMap' => $retensi->bannerSegmentMap(),
            'segmentOptions' => $segments,
        ];
    }

    public function index(): View
    {
        return view('admin.bundles.index', $this->bundlePayload());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'product_ids' => ['required', 'array', 'min:1', 'max:50'],
            'product_ids.*' => ['integer', Rule::exists('products', 'id')],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $bundle = DB::transaction(function () use ($validated): \App\Models\ProductBundle {
            $bundle = \App\Models\ProductBundle::create([
                'title' => (string) $validated['title'],
                'discount_percentage' => (float) ($validated['discount_percentage'] ?? 10),
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);

            $bundle->products()->sync(array_map('intval', $validated['product_ids']));

            return $bundle;
        });

        app(AuditLogger::class)->log('bundle.created', $bundle, [], ['title' => $bundle->title], auth('admin')->id());

        return back()->with('success', 'Bundle "'.$bundle->title.'" dibuat.');
    }

    public function destroy(Request $request, int $bundle): RedirectResponse
    {
        $model = \App\Models\ProductBundle::query()->findOrFail($bundle);

        $snapshot = ['title' => (string) $model->title];
        $model->delete();

        app(AuditLogger::class)->log('bundle.deleted', null, $snapshot, [], auth('admin')->id());

        return back()->with('success', 'Bundle dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function bundlePayload(): array
    {
        return [
            'bundles' => \App\Models\ProductBundle::query()
                ->withCount('products')
                ->orderByDesc('id')
                ->get()
                ->map(fn (\App\Models\ProductBundle $bundle): array => [
                    'id' => (int) $bundle->id,
                    'title' => (string) $bundle->title,
                    'discount_percentage' => (float) $bundle->discount_percentage,
                    'is_active' => (bool) $bundle->is_active,
                    'product_count' => (int) $bundle->products_count,
                    'created_at' => (string) ($bundle->created_at?->format('Y-m-d') ?? ''),
                ])
                ->all(),
            'productOptions' => \App\Models\Product::query()
                ->where('status', 'approved')
                ->orderBy('name')
                ->limit(500)
                ->get(['id', 'name', 'price'])
                ->map(fn (\App\Models\Product $product): array => [
                    'id' => (int) $product->id,
                    'name' => (string) $product->name,
                    'price_formatted' => Currency::format((float) $product->price),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function permissionMatrix(): array
    {
        $matrix = [];

        foreach (self::ROLE_MODULES as $moduleKey => $_) {
            foreach (self::ROLE_ACTIONS as $actionKey => $_) {
                $matrix[$moduleKey.'.'.$actionKey] = (string) SystemSetting::get('role_'.$moduleKey.'_'.$actionKey, '0') === '1';
            }
        }

        return $matrix;
    }

    /**
     * Cakupan izin granular per menu navigasi admin.
     * Tiap item navigasi dipetakan ke permission yang melindunginya.
     *
     * @return list<array{label: string, route: string, permission: string|null}>
     */
    private function menuCoverage(): array
    {
        $out = [];
        try {
            foreach ((array) config('navigation', []) as $group) {
                foreach ((array) ($group['items'] ?? []) as $item) {
                    $out[] = [
                        'label' => (string) (($group['label'] ?? '').' / '.($item['label'] ?? '')),
                        'route' => (string) ($item['route'] ?? ''),
                        'permission' => isset($item['permission']) ? (string) $item['permission'] : null,
                    ];
                }
            }
        } catch (\Throwable) {
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function roleRow(Role $role): array
    {
        return [
            'id' => (int) $role->id,
            'name' => (string) $role->name,
            'slug' => (string) $role->slug,
            'description' => (string) ($role->description ?? ''),
            'is_system' => (bool) $role->is_system,
            'is_default' => (bool) $role->is_default,
            'permission_count' => $role->permissions->count(),
            'permission_ids' => $role->permissions->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'permissions' => $role->permissions
                ->map(fn (Permission $permission): array => [
                    'id' => (int) $permission->id,
                    'slug' => (string) $permission->slug,
                    'module' => (string) $permission->module,
                ])
                ->all(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function menuPresets(): array
    {
        return collect(['main', 'footer', 'sidebar'])
            ->map(fn (string $key): array => ['value' => $key, 'label' => self::MENUS[$key]])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function persist(array $values): void
    {
        foreach ($values as $key => $value) {
            SystemSetting::set($key, $value === null || $value === '' ? null : (string) $value);
        }

        $this->flush();
    }

    private function flush(): void
    {
        Cache::forget('whitelabel_branding');
        SystemSetting::flush();
        Currency::flush();
        Feature::flush();
    }
}
