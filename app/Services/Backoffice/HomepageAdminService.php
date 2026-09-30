<?php

declare(strict_types=1);

namespace App\Services\Backoffice;

use App\Models\HomepageSection;
use App\Services\AuditLogger;
use App\Services\Catalog\HomePageService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Homepage composition admin.
 *
 * The registry in {@see HomePageService::registry()} is the source of truth for
 * which sections exist; this service only decides order, visibility, title and
 * per-section settings, all of which live in `homepage_sections`.
 */
final class HomepageAdminService
{
    public const DEVICE_MODES = ['all', 'desktop', 'mobile'];

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $registry = collect(HomePageService::registry())->keyBy('code');

        $stored = [];
        try {
            $stored = HomepageSection::query()->get()->keyBy('code')->all();
        } catch (\Throwable) {
            $stored = [];
        }

        $order = 0;
        $rows = [];

        foreach ($registry as $code => $definition) {
            $row = $stored[$code] ?? null;
            $settings = $row !== null && is_array($row->settings) ? $row->settings : [];

            $rows[] = [
                'code' => (string) $code,
                'title' => (string) (($row?->title ?: $definition['title']) ?? $code),
                'registry_title' => (string) ($definition['title'] ?? $code),
                'icon' => (string) ($definition['icon'] ?? 'layers'),
                'subtitle' => (string) ($row?->subtitle ?? ''),
                'is_enabled' => $row === null ? true : (bool) $row->is_enabled,
                'sort_order' => $row === null ? $order : (int) $row->sort_order,
                'devices' => $row === null ? 'all' : (string) $row->devices,
                'settings' => $settings,
                'limit' => (int) ($settings['limit'] ?? 10),
                'starts_at' => (string) ($row?->starts_at?->format('Y-m-d H:i') ?? ''),
                'ends_at' => (string) ($row?->ends_at?->format('Y-m-d H:i') ?? ''),
                'scheduled' => $row?->starts_at !== null || $row?->ends_at !== null,
                'persisted' => $row !== null,
            ];

            if ($row === null) {
                $order++;
            } else {
                $order = max($order, (int) $row->sort_order + 1);
            }
        }

        usort($rows, function (array $a, array $b): int {
            return $a['sort_order'] <=> $b['sort_order'] ?: strcmp($a['title'], $b['title']);
        });

        return [
            'rows' => $rows,
            'enabled_count' => count(array_filter($rows, fn (array $row): bool => $row['is_enabled'])),
            'total_count' => count($rows),
            'devices' => self::DEVICE_MODES,
        ];
    }

    /**
     * Persist the full ordering / configuration submitted by the editor.
     *
     * @param  list<array<string, mixed>>  $sections
     */
    public function save(array $sections, ?int $actorId): array
    {
        $registry = collect(HomePageService::registry())->keyBy('code');

        DB::transaction(function () use ($sections, $registry): void {
            foreach ($sections as $index => $section) {
                $code = (string) ($section['code'] ?? '');

                if ($code === '' || ! $registry->has($code)) {
                    continue;
                }

                $devices = in_array((string) ($section['devices'] ?? 'all'), self::DEVICE_MODES, true)
                    ? (string) $section['devices']
                    : 'all';

                $settings = $section['settings'] ?? [];
                if (! is_array($settings)) {
                    $settings = [];
                }

                if (isset($settings['limit']) && is_numeric($settings['limit'])) {
                    $settings['limit'] = max(1, min(24, (int) $settings['limit']));
                }

                HomepageSection::query()->updateOrCreate(
                    ['code' => $code],
                    [
                        'title' => trim((string) ($section['title'] ?? '')) !== ''
                            ? trim((string) $section['title'])
                            : (string) ($registry->get($code)['title'] ?? $code),
                        'subtitle' => trim((string) ($section['subtitle'] ?? '')) !== '' ? trim((string) $section['subtitle']) : null,
                        'is_enabled' => (bool) ($section['is_enabled'] ?? false),
                        'sort_order' => $index,
                        'settings' => $settings,
                        'devices' => $devices,
                        'starts_at' => ! empty($section['starts_at']) ? $section['starts_at'] : null,
                        'ends_at' => ! empty($section['ends_at']) ? $section['ends_at'] : null,
                    ],
                );
            }
        });

        $this->flush();

        app(AuditLogger::class)->log('homepage.saved', null, [], ['sections' => count($sections)], $actorId);
        $this->snapshotVersion($sections, $actorId);

        return $this->overview();
    }

    public function toggle(string $code, ?int $actorId): bool
    {
        $definition = collect(HomePageService::registry())->firstWhere('code', $code);

        if ($definition === null) {
            abort(422, 'Kode section tidak dikenal.');
        }

        $section = HomepageSection::query()->firstOrNew(['code' => $code]);
        $before = (bool) $section->is_enabled;
        $section->is_enabled = ! $before;

        if ($section->title === null) {
            $section->title = (string) ($definition['title'] ?? $code);
        }

        if ((int) $section->sort_order === 0 && ! $section->exists) {
            $section->sort_order = $this->nextOrder();
        }

        $section->save();

        $this->flush();

        app(AuditLogger::class)->log('homepage.section_toggled', $section, ['is_enabled' => $before], ['is_enabled' => $section->is_enabled], $actorId);

        return (bool) $section->is_enabled;
    }

    private function nextOrder(): int
    {
        return (int) (HomepageSection::query()->max('sort_order') ?? 0) + 1;
    }

    /**
     * Render the payload the storefront would receive, for the preview panel.
     *
     * @return array<string, mixed>
     */
    public function preview(?string $onlyCode = null): array
    {
        $service = app(HomePageService::class);
        $sections = $this->overview()['rows'];

        $out = [];

        foreach ($sections as $section) {
            if (! $section['is_enabled']) {
                continue;
            }

            if ($onlyCode !== null && $onlyCode !== '' && $onlyCode !== $section['code']) {
                continue;
            }

            try {
                $data = $service->data((string) $section['code'], $section['settings'], null);
            } catch (\Throwable $e) {
                $data = [];
            }

            $out[] = [
                'code' => (string) $section['code'],
                'title' => (string) $section['title'],
                'subtitle' => (string) $section['subtitle'],
                'icon' => (string) $section['icon'],
                'devices' => (string) $section['devices'],
                'summary' => $this->summarise($data),
                'empty' => $this->isEmpty($data),
            ];
        }

        return [
            'sections' => $out,
            'enabled_count' => count($out),
            'generated_at' => (string) now()->format('Y-m-d H:i'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{label: string, value: string}>
     */
    private function summarise(array $data): array
    {
        $summary = [];

        foreach ($data as $key => $value) {
            $summary[] = match (true) {
                is_countable($value) => ['label' => \Illuminate\Support\Str::headline((string) $key), 'value' => (string) count($value)],
                $value === null => ['label' => \Illuminate\Support\Str::headline((string) $key), 'value' => '-'],
                is_scalar($value) => ['label' => \Illuminate\Support\Str::headline((string) $key), 'value' => (string) $value],
                default => ['label' => \Illuminate\Support\Str::headline((string) $key), 'value' => 'tersedia'],
            };
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isEmpty(array $data): bool
    {
        foreach ($data as $value) {
            if (is_countable($value) && count($value) > 0) {
                return false;
            }

            if ($value instanceof \Illuminate\Support\Collection && $value->isNotEmpty()) {
                return false;
            }

            if (is_object($value)) {
                return false;
            }
        }

        return true;
    }

    public function flush(): void
    {
        Cache::forget(HomePageService::CACHE_KEY);
    }

    /* ── ADITIF popup builder (kelola popup konversi, tanpa ubah homepage) ── */

    /**
     * Daftar popup untuk panel admin (terbaru dulu).
     *
     * @return list<array<string, mixed>>
     */
    public function popups(): array
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('popups')) {
                return [];
            }

            return \Illuminate\Support\Facades\DB::table('popups')
                ->orderByDesc('id')
                ->limit(100)
                ->get()
                ->map(fn ($row): array => (array) $row)
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Simpan (buat/perbarui) satu popup. Isi HTML disanitasi via PopupService.
     *
     * @param  array<string, mixed>  $data
     */
    public function savePopup(array $data, ?int $id, ?int $actorId): int
    {
        $payload = [
            'title' => mb_substr(trim((string) ($data['title'] ?? '')), 0, 160),
            'body_html' => \App\Services\Cms\PopupService::sanitize((string) ($data['body_html'] ?? '')),
            'image' => ($v = trim((string) ($data['image'] ?? ''))) !== '' ? mb_substr($v, 0, 500) : null,
            'button_text' => ($v = trim((string) ($data['button_text'] ?? ''))) !== '' ? mb_substr($v, 0, 80) : null,
            'button_link' => ($v = trim((string) ($data['button_link'] ?? ''))) !== '' ? mb_substr($v, 0, 500) : null,
            'targeting' => in_array((string) ($data['targeting'] ?? 'all'), \App\Services\Cms\PopupService::TARGETINGS, true)
                ? (string) $data['targeting']
                : 'all',
            'delay_seconds' => max(0, min(60, (int) ($data['delay_seconds'] ?? 3))),
            'cap_days' => max(1, min(90, (int) ($data['cap_days'] ?? 7))),
            'starts_at' => ! empty($data['starts_at']) ? $data['starts_at'] : null,
            'ends_at' => ! empty($data['ends_at']) ? $data['ends_at'] : null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'updated_at' => now(),
        ];

        if ($id !== null && $id > 0) {
            \Illuminate\Support\Facades\DB::table('popups')->where('id', $id)->update($payload);
            $savedId = $id;
        } else {
            $payload['created_at'] = now();
            $savedId = (int) \Illuminate\Support\Facades\DB::table('popups')->insertGetId($payload);
        }

        \App\Services\Cms\PopupService::flush();
        app(AuditLogger::class)->log('popup.saved', null, ['id' => $id], ['id' => $savedId], $actorId);

        return $savedId;
    }

    public function togglePopup(int $id, ?int $actorId): bool
    {
        $row = \Illuminate\Support\Facades\DB::table('popups')->where('id', $id)->first();

        if ($row === null) {
            abort(404, 'Popup tidak ditemukan.');
        }

        $next = ! (bool) $row->is_active;
        \Illuminate\Support\Facades\DB::table('popups')
            ->where('id', $id)
            ->update(['is_active' => $next, 'updated_at' => now()]);

        \App\Services\Cms\PopupService::flush();
        app(AuditLogger::class)->log('popup.toggled', null, ['is_active' => (bool) $row->is_active], ['is_active' => $next], $actorId);

        return $next;
    }

    public function deletePopup(int $id, ?int $actorId): void
    {
        $row = \Illuminate\Support\Facades\DB::table('popups')->where('id', $id)->first();

        \Illuminate\Support\Facades\DB::table('popups')->where('id', $id)->delete();

        \App\Services\Cms\PopupService::flush();
        app(AuditLogger::class)->log('popup.deleted', null, $row !== null ? (array) $row : ['id' => $id], [], $actorId);
    }

    /**
     * Versioning homepage: simpan 10 snapshot terakhir di system_settings.
     */
    private function snapshotVersion(array $sections, ?int $actorId): void
    {
        try {
            $key = 'homepage_versions';
            $raw = \App\Models\SystemSetting::get($key, '');
            $history = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            if (! is_array($history)) {
                $history = [];
            }
            array_unshift($history, [
                'at' => now()->format('Y-m-d H:i:s'),
                'actor_id' => $actorId,
                'sections' => $sections,
            ]);
            \App\Models\SystemSetting::set($key, json_encode(array_slice($history, 0, 10), JSON_UNESCAPED_UNICODE));
        } catch (\Throwable) {
        }
    }

    /** @return list<array{at: string, actor_id: int|null, sections: array}> */
    public function versions(): array
    {
        try {
            $raw = \App\Models\SystemSetting::get('homepage_versions', '');
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            if (! is_array($decoded)) {
                return [];
            }

            return array_values(array_filter($decoded, fn (mixed $v): bool => is_array($v) && isset($v['at'])));
        } catch (\Throwable) {
            return [];
        }
    }
}
