<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Form builder + submissions (newsletter, kontak, dsb).
 *
 * ID: Definisi form + submission memakai tabel cms_forms /
 * cms_form_submissions bila migrasi sudah jalan; fallback SystemSetting
 * (cms_forms_json / cms_form_submissions_json) agar service tetap bisa
 * diuji tanpa DB baru. Field: text, email, textarea, select, checkbox.
 *
 * EN: Form builder with DB-first, SystemSetting-fallback storage.
 */
class CmsFormService
{
    public const FIELD_TYPES = ['text', 'email', 'textarea', 'select', 'checkbox'];

    /** @return list<array<string, mixed>> */
    public function forms(): array
    {
        try {
            if (Schema::hasTable('cms_forms')) {
                return DB::table('cms_forms')->orderBy('key')->get()
                    ->map(fn ($r) => [
                        'id' => (int) $r->id,
                        'key' => (string) $r->key,
                        'title' => (string) $r->title,
                        'fields' => $this->decodeFields((string) ($r->fields_json ?? '[]')),
                        'is_active' => (bool) $r->is_active,
                    ])->all();
            }
        } catch (\Throwable) {
        }

        try {
            $raw = SystemSetting::get('cms_forms_json', '');
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];

            return is_array($decoded) ? array_values($decoded) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param  list<array<string, mixed>>  $fields */
    public function saveForm(string $key, string $title, array $fields, bool $isActive = true): array
    {
        $key = strtolower(trim(preg_replace('/[^A-Za-z0-9_\-]/', '-', $key) ?? ''));
        if ($key === '') {
            throw new \InvalidArgumentException('Kunci form tidak valid. / Invalid form key.');
        }
        $fields = $this->normalizeFields($fields);
        if ($fields === []) {
            throw new \InvalidArgumentException('Form wajib punya minimal 1 field. / At least one field is required.');
        }

        try {
            if (Schema::hasTable('cms_forms')) {
                DB::table('cms_forms')->updateOrInsert(
                    ['key' => mb_substr($key, 0, 80)],
                    ['title' => mb_substr(trim($title) !== '' ? $title : $key, 0, 160),
                        'fields_json' => json_encode($fields, JSON_UNESCAPED_UNICODE),
                        'is_active' => $isActive, 'updated_at' => now(),
                        'created_at' => now()],
                );
                $row = DB::table('cms_forms')->where('key', $key)->first();

                return ['id' => (int) ($row->id ?? 0), 'key' => $key, 'title' => $title, 'fields' => $fields, 'is_active' => $isActive];
            }
        } catch (\Throwable) {
        }

        $forms = [];
        foreach ($this->forms() as $form) {
            $forms[(string) $form['key']] = $form;
        }
        $forms[$key] = ['id' => 0, 'key' => $key, 'title' => $title, 'fields' => $fields, 'is_active' => $isActive];
        SystemSetting::set('cms_forms_json', json_encode(array_values($forms), JSON_UNESCAPED_UNICODE));

        return $forms[$key];
    }

    public function findForm(string $key): ?array
    {
        foreach ($this->forms() as $form) {
            if ((string) $form['key'] === $key) {
                return $form;
            }
        }

        return null;
    }

    /**
     * Validasi payload submission terhadap definisi field.
     *
     * @param  array<string, mixed>  $payload
     * @return array{valid: bool, errors: array<string, string>, clean: array<string, mixed>}
     */
    public function validateSubmission(string $key, array $payload): array
    {
        $form = $this->findForm($key);
        if ($form === null || empty($form['is_active'])) {
            return ['valid' => false, 'errors' => ['form' => 'Form tidak ditemukan atau nonaktif.'], 'clean' => []];
        }
        $errors = [];
        $clean = [];
        foreach ((array) ($form['fields'] ?? []) as $field) {
            $name = (string) ($field['name'] ?? '');
            $type = (string) ($field['type'] ?? 'text');
            $required = ! empty($field['required']);
            $value = $payload[$name] ?? null;
            $str = is_array($value) ? implode(', ', array_map('strval', $value)) : trim((string) ($value ?? ''));

            if ($required && $str === '') {
                $errors[$name] = 'Wajib diisi.';
                continue;
            }
            if ($str === '') {
                $clean[$name] = null;
                continue;
            }
            if ($type === 'email' && ! filter_var($str, FILTER_VALIDATE_EMAIL)) {
                $errors[$name] = 'Email tidak valid.';
                continue;
            }
            if (mb_strlen($str) > 5000) {
                $errors[$name] = 'Maksimal 5000 karakter.';
                continue;
            }
            if ($type === 'select' && ! empty($field['options']) && ! in_array($str, (array) $field['options'], true)) {
                $errors[$name] = 'Pilihan tidak valid.';
                continue;
            }
            $clean[$name] = mb_substr($str, 0, 5000);
        }

        return ['valid' => $errors === [], 'errors' => $errors, 'clean' => $clean];
    }

    /**
     * Simpan submission (sudah tervalidasi) + kembalikan ID.
     *
     * @param  array<string, mixed>  $clean
     */
    public function submit(string $key, array $clean): int
    {
        try {
            if (Schema::hasTable('cms_form_submissions')) {
                $formId = null;
                try {
                    $formId = DB::table('cms_forms')->where('key', $key)->value('id');
                } catch (\Throwable) {
                }

                return (int) DB::table('cms_form_submissions')->insertGetId([
                    'form_key' => mb_substr($key, 0, 80),
                    'form_id' => $formId,
                    'payload_json' => json_encode($clean, JSON_UNESCAPED_UNICODE),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        } catch (\Throwable) {
        }

        $all = $this->submissions($key);
        $id = count($all) + 1;
        $all[] = ['id' => $id, 'form_key' => $key, 'payload' => $clean, 'created_at' => now()->format('Y-m-d H:i:s')];
        try {
            $raw = SystemSetting::get('cms_form_submissions_json', '');
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            $decoded = is_array($decoded) ? $decoded : [];
            $decoded[$key] = $all;
            SystemSetting::set('cms_form_submissions_json', json_encode($decoded, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable) {
        }

        return $id;
    }

    /** @return list<array<string, mixed>> */
    public function submissions(string $key, int $limit = 100): array
    {
        try {
            if (Schema::hasTable('cms_form_submissions')) {
                return DB::table('cms_form_submissions')->where('form_key', $key)
                    ->orderByDesc('id')->limit(max(1, min(500, $limit)))->get()
                    ->map(fn ($r) => [
                        'id' => (int) $r->id,
                        'form_key' => (string) $r->form_key,
                        'payload' => json_decode((string) ($r->payload_json ?? '{}'), true) ?: [],
                        'created_at' => (string) $r->created_at,
                    ])->all();
            }
        } catch (\Throwable) {
        }

        try {
            $raw = SystemSetting::get('cms_form_submissions_json', '');
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];

            return is_array($decoded[$key] ?? null) ? array_slice(array_reverse($decoded[$key]), 0, $limit) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function deleteForm(string $key): void
    {
        try {
            if (Schema::hasTable('cms_forms')) {
                DB::table('cms_forms')->where('key', $key)->delete();
            }
        } catch (\Throwable) {
        }
        try {
            $forms = [];
            foreach ($this->forms() as $form) {
                if ((string) $form['key'] !== $key) {
                    $forms[(string) $form['key']] = $form;
                }
            }
            SystemSetting::set('cms_forms_json', json_encode(array_values($forms), JSON_UNESCAPED_UNICODE));
        } catch (\Throwable) {
        }
    }

    /**
     * @param  mixed  $fields
     * @return list<array<string, mixed>>
     */
    private function normalizeFields(mixed $fields): array
    {
        if (! is_array($fields)) {
            return [];
        }
        $out = [];
        foreach (array_values($fields) as $field) {
            if (! is_array($field)) {
                continue;
            }
            $name = strtolower(trim(preg_replace('/[^A-Za-z0-9_]/', '_', (string) ($field['name'] ?? '')) ?? ''));
            $type = strtolower(trim((string) ($field['type'] ?? 'text')));
            if ($name === '' || ! in_array($type, self::FIELD_TYPES, true)) {
                continue;
            }
            $options = [];
            if ($type === 'select') {
                foreach (array_slice((array) ($field['options'] ?? []), 0, 20) as $opt) {
                    $opt = trim((string) (is_array($opt) ? ($opt['value'] ?? $opt['label'] ?? '') : $opt));
                    if ($opt !== '') {
                        $options[] = mb_substr($opt, 0, 120);
                    }
                }
            }
            $out[] = [
                'name' => mb_substr($name, 0, 60),
                'label' => mb_substr(trim((string) ($field['label'] ?? $name)), 0, 120),
                'type' => $type,
                'required' => ! empty($field['required']),
                'options' => $options,
            ];
            if (count($out) >= 20) {
                break;
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function decodeFields(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $this->normalizeFields($decoded) : [];
    }
}
