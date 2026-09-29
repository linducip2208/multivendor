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
}
