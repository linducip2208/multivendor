<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Workflow konten per bahasa: draft/review/published/scheduled.
 *
 * ID: dipakai untuk page (subject_type=page, subject_key=slug) dan blog
 * (blog_post_id). Status utama tetap di kolom `status` tabel terjemahan
 * existing (blog_post_translations / content_translations); state
 * `review` + `scheduled_at` disimpan sebagai overlay SystemSetting
 * `cms_workflow_{scope}_{id}_{locale}` agar tanpa migrasi baru.
 * Fallback chain: locale -> base -> id -> en (lihat Translatable).
 *
 * EN: Per-locale content workflow. Core status lives in the existing
 * translation tables; review/schedule overlay lives in SystemSetting.
 */
class ContentWorkflowService
{
    public const STATES = ['draft', 'review', 'published', 'scheduled'];

    public const LOCALES = ['id', 'en'];

    public function normalizeState(string $state): string
    {
        $state = strtolower(trim($state));

        return in_array($state, self::STATES, true) ? $state : 'draft';
    }

    public function normalizeLocale(string $locale): string
    {
        $locale = strtolower(trim($locale));

        return in_array($locale, self::LOCALES, true) ? $locale : 'id';
    }

    /**
     * Status efektif satu konten per bahasa.
     *
     * @return array{state: string, status: string, scheduled_at: string|null, visible: bool}
     */
    public function status(string $scope, int|string $subject, string $locale): array
    {
        $locale = $this->normalizeLocale($locale);
        $overlay = $this->overlay($scope, $subject, $locale);
        $rowStatus = $this->rowStatus($scope, $subject, $locale);

        $state = $this->normalizeState((string) ($overlay['state'] ?? $rowStatus));
        $scheduledAt = isset($overlay['scheduled_at']) && trim((string) $overlay['scheduled_at']) !== ''
            ? (string) $overlay['scheduled_at']
            : null;

        // Scheduled yang waktunya sudah lewat dianggap published (terbit otomatis).
        if ($state === 'scheduled' && $scheduledAt !== null) {
            try {
                if (strtotime($scheduledAt) <= time()) {
                    $state = 'published';
                }
            } catch (\Throwable) {
            }
        }

        return [
            'state' => $state,
            'status' => $state === 'published' ? 'published' : 'draft',
            'scheduled_at' => $scheduledAt,
            'visible' => $state === 'published',
        ];
    }

    /**
     * Terapkan workflow: tulis status ke tabel terjemahan existing
     * (draft=>draft, lainnya=>published agar storefront lama tetap jalan)
     * + overlay review/scheduled_at.
     */
    public function transition(string $scope, int|string $subject, string $locale, string $state, ?string $scheduledAt = null, ?int $actorId = null): array
    {
        $locale = $this->normalizeLocale($locale);
        $state = $this->normalizeState($state);

        if ($state === 'scheduled') {
            if ($scheduledAt === null || trim($scheduledAt) === '' || strtotime($scheduledAt) === false) {
                throw new \InvalidArgumentException('Jadwal terbit (scheduled_at) wajib berupa tanggal valid untuk state scheduled. / A valid scheduled_at is required.');
            }
            $scheduledAt = date('Y-m-d H:i:s', (int) strtotime($scheduledAt));
        } else {
            $scheduledAt = $state === 'published' ? null : $scheduledAt;
            if ($scheduledAt !== null && trim($scheduledAt) !== '') {
                $scheduledAt = date('Y-m-d H:i:s', (int) strtotime($scheduledAt));
            } else {
                $scheduledAt = null;
            }
        }

        $this->writeRowStatus($scope, $subject, $locale, $state === 'published' || $state === 'scheduled' ? 'published' : 'draft');

        SystemSetting::set(
            $this->overlayKey($scope, $subject, $locale),
            json_encode([
                'state' => $state,
                'scheduled_at' => $scheduledAt,
                'updated_at' => now()->format('Y-m-d H:i:s'),
                'actor_id' => $actorId,
            ], JSON_UNESCAPED_UNICODE)
        );

        return $this->status($scope, $subject, $locale);
    }

    /**
     * Daftar status semua locale untuk satu konten (copy BI/EN).
     *
     * @return array<string, array{state: string, status: string, scheduled_at: string|null, visible: bool}>
     */
    public function listFor(string $scope, int|string $subject): array
    {
        $out = [];
        foreach (self::LOCALES as $locale) {
            $out[$locale] = $this->status($scope, $subject, $locale);
        }

        return $out;
    }

    /**
     * Konten scheduled yang sudah jatuh tempo (untuk scheduler/integrator).
     *
     * @return list<array{key: string, scope: string, subject: string, locale: string, scheduled_at: string}>
     */
    public function dueScheduled(): array
    {
        $due = [];
        try {
            $rows = SystemSetting::query()->where('key', 'like', 'cms_workflow\_%')->get(['key', 'value']);
            foreach ($rows as $row) {
                $payload = is_string($row->value) ? json_decode($row->value, true) : null;
                if (! is_array($payload) || ($payload['state'] ?? '') !== 'scheduled') {
                    continue;
                }
                $at = (string) ($payload['scheduled_at'] ?? '');
                if ($at === '' || strtotime($at) === false || strtotime($at) > time()) {
                    continue;
                }
                $parts = explode('_', (string) $row->key);
                // cms_workflow_{scope}_{subject...}_{locale}
                $locale = end($parts);
                $scope = $parts[2] ?? '';
                $subject = implode('_', array_slice($parts, 3, -1));
                $due[] = ['key' => (string) $row->key, 'scope' => $scope, 'subject' => $subject, 'locale' => $locale, 'scheduled_at' => $at];
            }
        } catch (\Throwable) {
        }

        return $due;
    }

    /** @return array<string, mixed> */
    private function overlay(string $scope, int|string $subject, string $locale): array
    {
        try {
            $raw = SystemSetting::get($this->overlayKey($scope, $subject, $locale), '');
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function overlayKey(string $scope, int|string $subject, string $locale): string
    {
        $subject = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $subject) ?: 'x';

        return 'cms_workflow_'.preg_replace('/[^a-z]/', '', strtolower($scope)).'_'.$subject.'_'.$locale;
    }

    private function rowStatus(string $scope, int|string $subject, string $locale): string
    {
        try {
            if ($scope === 'blog' && is_numeric($subject) && Schema::hasTable('blog_post_translations')) {
                $row = DB::table('blog_post_translations')
                    ->where('blog_post_id', (int) $subject)->where('locale', $locale)->first();
                if ($row && in_array((string) ($row->status ?? ''), ['draft', 'review', 'published', 'scheduled'], true)) {
                    return (string) $row->status;
                }
            }
            if (Schema::hasTable('content_translations')) {
                $q = DB::table('content_translations')->where('subject_type', $scope)->where('locale', $locale);
                if (is_numeric($subject) && $scope !== 'page') {
                    $q->where('subject_id', (int) $subject);
                } else {
                    $q->where('subject_key', (string) $subject);
                }
                $row = $q->first();
                if ($row && in_array((string) ($row->status ?? ''), ['draft', 'review', 'published', 'scheduled'], true)) {
                    return (string) $row->status;
                }
            }
        } catch (\Throwable) {
        }

        return 'published';
    }

    private function writeRowStatus(string $scope, int|string $subject, string $locale, string $status): void
    {
        try {
            if ($scope === 'blog' && is_numeric($subject) && Schema::hasTable('blog_post_translations')) {
                DB::table('blog_post_translations')
                    ->where('blog_post_id', (int) $subject)->where('locale', $locale)
                    ->update(['status' => $status, 'updated_at' => now()]);
            } elseif (Schema::hasTable('content_translations')) {
                $q = DB::table('content_translations')->where('subject_type', $scope)->where('locale', $locale);
                if (is_numeric($subject) && $scope !== 'page') {
                    $q->where('subject_id', (int) $subject);
                } else {
                    $q->where('subject_key', (string) $subject);
                }
                $q->update(['status' => $status, 'updated_at' => now()]);
            }
        } catch (\Throwable) {
        }
    }
}
