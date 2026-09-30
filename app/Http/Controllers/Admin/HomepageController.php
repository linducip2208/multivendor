<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Backoffice\HomepageAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomepageController extends Controller
{
    public function __construct(private readonly HomepageAdminService $homepage) {}

    public function index(): View
    {
        return view('admin.homepage.index', $this->homepage->overview() + [
            'versions' => $this->homepage->versions(),
            'popups' => $this->homepage->popups(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sections' => ['required', 'array', 'min:1', 'max:40'],
            'sections.*.code' => ['required', 'string', 'max:60'],
            'sections.*.title' => ['nullable', 'string', 'max:160'],
            'sections.*.subtitle' => ['nullable', 'string', 'max:255'],
            'sections.*.is_enabled' => ['nullable', 'boolean'],
            'sections.*.devices' => ['nullable', 'string', 'in:all,desktop,mobile'],
            'sections.*.limit' => ['nullable', 'integer', 'min:1', 'max:24'],
            'sections.*.settings' => ['nullable', 'array'],
            'sections.*.starts_at' => ['nullable', 'date'],
            'sections.*.ends_at' => ['nullable', 'date', 'after_or_equal:sections.*.starts_at'],
        ]);

        $sections = [];
        foreach ($validated['sections'] as $entry) {
            $sections[] = [
                'code' => (string) $entry['code'],
                'title' => (string) ($entry['title'] ?? ''),
                'subtitle' => (string) ($entry['subtitle'] ?? ''),
                'is_enabled' => (bool) ($entry['is_enabled'] ?? false),
                'devices' => (string) ($entry['devices'] ?? 'all'),
                'settings' => array_merge(
                    is_array($entry['settings'] ?? null) ? $entry['settings'] : [],
                    isset($entry['limit']) && $entry['limit'] !== null ? ['limit' => (int) $entry['limit']] : [],
                ),
                'starts_at' => $entry['starts_at'] ?? null,
                'ends_at' => $entry['ends_at'] ?? null,
            ];
        }

        $overview = $this->homepage->save($sections, auth('admin')->id());

        return back()->with('success', 'Homepage disimpan. '.$overview['enabled_count'].' dari '.$overview['total_count'].' section aktif.');
    }

    public function preview(Request $request): JsonResponse
    {
        $only = (string) $request->query('section', '');

        return response()->json([
            'success' => true,
            'data' => $this->homepage->preview($only !== '' ? $only : null),
        ]);
    }

    /* ── ADITIF popup builder (integrator yang wiring route) ── */

    /**
     * Simpan popup baru (POST admin/homepage/popups).
     * Untuk integrator: daftarkan route POST sendiri bila dibutuhkan.
     */
    public function storePopup(Request $request): RedirectResponse
    {
        $this->homepage->savePopup($this->validatePopup($request), null, auth('admin')->id());

        return back()->with('success', 'Popup disimpan dan siap tayang di storefront.');
    }

    /**
     * Perbarui popup (PUT admin/homepage/popups/{id}).
     * Untuk integrator: daftarkan route PUT sendiri bila dibutuhkan.
     */
    public function updatePopup(Request $request, int $id): RedirectResponse
    {
        $this->homepage->savePopup($this->validatePopup($request), $id, auth('admin')->id());

        return back()->with('success', 'Popup diperbarui.');
    }

    /**
     * Alihkan aktif/nonaktif popup (POST admin/homepage/popups/{id}/toggle).
     * Untuk integrator: daftarkan route POST sendiri bila dibutuhkan.
     */
    public function togglePopup(int $id): RedirectResponse
    {
        $next = $this->homepage->togglePopup($id, auth('admin')->id());

        return back()->with('success', $next ? 'Popup diaktifkan.' : 'Popup dinonaktifkan.');
    }

    /**
     * Hapus popup (DELETE admin/homepage/popups/{id}).
     * Untuk integrator: daftarkan route DELETE sendiri bila dibutuhkan.
     */
    public function destroyPopup(int $id): RedirectResponse
    {
        $this->homepage->deletePopup($id, auth('admin')->id());

        return back()->with('success', 'Popup dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePopup(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body_html' => ['nullable', 'string', 'max:10000'],
            'image' => ['nullable', 'string', 'max:500'],
            'button_text' => ['nullable', 'string', 'max:80'],
            'button_link' => ['nullable', 'string', 'max:500'],
            'targeting' => ['nullable', 'string', 'in:all,home,product,cart,checkout'],
            'delay_seconds' => ['nullable', 'integer', 'min:0', 'max:60'],
            'cap_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
