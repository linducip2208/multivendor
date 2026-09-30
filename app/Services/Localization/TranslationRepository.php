<?php

declare(strict_types=1);

namespace App\Services\Localization;

use App\Models\Language;
use App\Models\TranslationGroup;
use App\Models\TranslationKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repositori terjemahan database-driven dengan cache + fallback.
 *
 * Chain: id-ID -> id -> en (umum: locale -> base -> en).
 * Key format: "namespace.key.sisa" (titik pertama = namespace).
 */
class TranslationRepository
{
    public const NAMESPACES = TranslationKey::NAMESPACES;

    public const DEFAULT_LOCALE = 'en';

    public function normalizeLocale(string $locale): string
    {
        $locale = trim($locale) !== '' ? $locale : self::DEFAULT_LOCALE;

        return Language::canonicalize($locale);
    }

    /**
     * @return list<string> mis. ['id-ID','id','en']
     */
    public function fallbackChain(string $locale): array
    {
        $locale = $this->normalizeLocale($locale);
        $chain = [$locale];

        $base = Language::baseCode($locale);
        if ($base !== '' && $base !== strtolower($locale) && ! in_array($base, $chain, true)) {
            $chain[] = $base;
        }

        // Aturan kontrak: varian Indonesia selalu jatuh ke 'id' lalu 'en'.
        if (in_array($base, ['id'], true) && ! in_array('id', $chain, true)) {
            $chain[] = 'id';
        }

        if (! in_array(self::DEFAULT_LOCALE, $chain, true)) {
            $chain[] = self::DEFAULT_LOCALE;
        }

        return array_values(array_unique($chain));
    }

    /**
     * @param  array<string, string|int|float>  $replacements  ['name' => 'Budi'] untuk ":name"
     */
    public function get(string $fullKey, string $locale, array $replacements = [], ?string $default = null): string
    {
        $parts = TranslationKey::split($fullKey);
        $namespace = strtolower($parts['namespace']);
        $key = $parts['key'];

        foreach ($this->fallbackChain($locale) as $candidate) {
            $value = $this->lookup($namespace, $key, $candidate);
            if ($value !== null && $value !== '') {
                return $this->applyReplacements($value, $replacements);
            }
        }

        app(MissingDetector::class)->logMiss($this->normalizeLocale($locale), $namespace, $key);

        if ($default !== null) {
            return $this->applyReplacements($default, $replacements);
        }

        return $this->applyReplacements($fullKey, $replacements);
    }

    public function set(string $locale, string $namespace, string $key, ?string $value, array $meta = []): void
    {
        $locale = $this->normalizeLocale($locale);
        $namespace = strtolower(trim($namespace));
        $key = trim($key);

        if (! TranslationKey::isValidNamespace($namespace)) {
            throw new \InvalidArgumentException("Namespace '{$namespace}' tidak diizinkan.");
        }
        if ($key === '') {
            throw new \InvalidArgumentException('Key terjemahan tidak boleh kosong.');
        }

        $keyId = $this->ensureKey($namespace, $key, $meta);
        $languageId = $this->languageIdFor($locale);

        $row = [
            'locale' => $locale,
            'group' => $namespace,
            'key' => $key,
            'value' => $value,
        ];

        if (Schema::hasColumn('translations', 'translation_key_id')) {
            $row['translation_key_id'] = $keyId;
        }
        if (Schema::hasColumn('translations', 'language_id')) {
            $row['language_id'] = $languageId;
        }
        if (Schema::hasColumn('translations', 'status')) {
            $row['status'] = (string) ($meta['status'] ?? 'published');
        }
        if (Schema::hasColumn('translations', 'is_verified')) {
            $row['is_verified'] = (bool) ($meta['is_verified'] ?? false);
        }
        if (Schema::hasColumn('translations', 'updated_by')) {
            $row['updated_by'] = $meta['updated_by'] ?? null;
        }

        DB::table('translations')->updateOrInsert(
            ['locale' => $locale, 'group' => $namespace, 'key' => $key],
            $row + ['created_at' => now(), 'updated_at' => now()]
        );

        $this->forgetLocale($locale);
        $this->forgetLocale(self::DEFAULT_LOCALE);
    }

    /**
     * @return array<string, string> key penuh => teks
     */
    public function allForLocale(string $locale, ?string $namespace = null): array
    {
        $locale = $this->normalizeLocale($locale);
        $cacheKey = $this->cacheKey($locale, $namespace);

        /** @var array<string, string> $cached */
        $cached = Cache::remember($cacheKey, 3600, function () use ($locale, $namespace) {
            $query = DB::table('translations')->where('locale', $locale);
            if ($namespace !== null) {
                $query->where('group', strtolower($namespace));
            }
            $rows = $query->get(['group', 'key', 'value']);

            $out = [];
            foreach ($rows as $row) {
                if ($row->value === null || $row->value === '') {
                    continue;
                }
                $out[$row->group.'.'.$row->key] = (string) $row->value;
            }

            return $out;
        });

        return $cached;
    }

    /**
     * Laporan cakupan per namespace + total.
     *
     * @return array{locale: string, reference: string, total_keys: int, translated: int, percent: float, per_namespace: array<string, array{total: int, translated: int, percent: float}>}
     */
    public function coverage(string $locale, string $reference = self::DEFAULT_LOCALE): array
    {
        $locale = $this->normalizeLocale($locale);
        $reference = $this->normalizeLocale($reference);

        $refRows = DB::table('translations')
            ->where('locale', $reference)
            ->whereNotNull('value')->where('value', '!=', '')
            ->get(['group', 'key']);

        // Bila referensi kosong (fresh DB), pakai translation_keys sebagai acuan.
        if ($refRows->isEmpty() && Schema::hasTable('translation_keys')) {
            $refRows = TranslationKey::query()->get()->map(fn (TranslationKey $k) => (object) [
                'group' => $k->namespace,
                'key' => $k->key,
            ]);
        }

        $target = DB::table('translations')
            ->where('locale', $locale)
            ->whereNotNull('value')->where('value', '!=', '')
            ->get(['group', 'key'])
            ->map(fn ($r) => $r->group."\0".$r->key)
            ->flip();

        $perNamespace = [];
        foreach ($refRows as $row) {
            $ns = (string) $row->group;
            $perNamespace[$ns] ??= ['total' => 0, 'translated' => 0];
            $perNamespace[$ns]['total']++;
            if (isset($target[$row->group."\0".$row->key])) {
                $perNamespace[$ns]['translated']++;
            }
        }
        ksort($perNamespace);

        foreach ($perNamespace as $ns => $stat) {
            $perNamespace[$ns]['percent'] = $stat['total'] > 0
                ? round(($stat['translated'] / $stat['total']) * 100, 2)
                : 100.0;
        }

        $total = array_sum(array_column($perNamespace, 'total'));
        $translated = array_sum(array_column($perNamespace, 'translated'));

        return [
            'locale' => $locale,
            'reference' => $reference,
            'total_keys' => $total,
            'translated' => $translated,
            'percent' => $total > 0 ? round(($translated / $total) * 100, 2) : 100.0,
            'per_namespace' => $perNamespace,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function exportJson(string $locale, ?string $namespace = null): array
    {
        return $this->allForLocale($locale, $namespace);
    }

    public function exportCsv(string $locale, ?string $namespace = null): string
    {
        $rows = $this->allForLocale($locale, $namespace);

        $lines = ['namespace,key,locale,value'];
        foreach ($rows as $full => $value) {
            $parts = TranslationKey::split($full);
            $lines[] = $this->csvRow([$parts['namespace'], $parts['key'], $this->normalizeLocale($locale), $value]);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<string, string>  $data  ["common.save" => "Simpan"]
     */
    public function importJson(string $locale, array $data): int
    {
        $count = 0;
        foreach ($data as $fullKey => $value) {
            $parts = TranslationKey::split((string) $fullKey);
            $this->set($locale, $parts['namespace'], $parts['key'], (string) $value);
            $count++;
        }

        return $count;
    }

    public function importCsv(string $locale, string $csv): int
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csv));
        if ($lines === false || $lines === []) {
            return 0;
        }

        $header = array_map('strtolower', str_getcsv(array_shift($lines)));
        $hasHeader = in_array('namespace', $header, true) && in_array('key', $header, true);
        if (! $hasHeader) {
            array_unshift($lines, implode(',', $header));
        }

        $count = 0;
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line);
            if (count($cols) < 2) {
                continue;
            }
            // Format: namespace,key,locale?,value?
            $namespace = strtolower(trim($cols[0]));
            $key = trim($cols[1]);
            $value = $cols[3] ?? $cols[2] ?? '';
            $this->set($locale, $namespace, $key, (string) $value);
            $count++;
        }

        return $count;
    }

    public function forgetLocale(string $locale): void
    {
        $locale = $this->normalizeLocale($locale);
        Cache::forget($this->cacheKey($locale, null));
        foreach (self::NAMESPACES as $ns) {
            Cache::forget($this->cacheKey($locale, $ns));
        }
    }

    public function forgetAll(): void
    {
        foreach (['id', 'id-ID', 'en', self::DEFAULT_LOCALE] as $locale) {
            $this->forgetLocale($locale);
        }
        app(LanguageService::class)->forgetCache();
    }

    private function lookup(string $namespace, string $key, string $locale): ?string
    {
        $map = $this->allForLocale($locale, $namespace);

        return $map[$namespace.'.'.$key] ?? null;
    }

    /**
     * @param  array<string, string|int|float>  $replacements
     */
    private function applyReplacements(string $value, array $replacements): string
    {
        foreach ($replacements as $name => $replace) {
            $value = str_replace(':'.$name, (string) $replace, $value);
        }

        return $value;
    }

    private function cacheKey(string $locale, ?string $namespace): string
    {
        return 'i18n:'.$locale.':'.($namespace ?? '*');
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function ensureKey(string $namespace, string $key, array $meta = []): ?int
    {
        if (! Schema::hasTable('translation_keys')) {
            return null;
        }

        $groupId = null;
        if (Schema::hasTable('translation_groups')) {
            $groupId = TranslationGroup::query()->where('slug', $namespace)->value('id');
            if ($groupId === null) {
                try {
                    $groupId = TranslationGroup::query()->create([
                        'slug' => $namespace,
                        'description' => 'Auto-created namespace '.$namespace,
                    ])->id;
                } catch (\Throwable) {
                    $groupId = null;
                }
            }
        }

        try {
            $model = TranslationKey::query()->firstOrCreate(
                ['namespace' => $namespace, 'key' => $key],
                [
                    'group_id' => $groupId,
                    'description' => $meta['description'] ?? null,
                    'default_text' => isset($meta['default_text']) ? (string) $meta['default_text'] : null,
                ]
            );

            return (int) $model->id;
        } catch (\Throwable) {
            return null;
        }
    }

    private function languageIdFor(string $locale): ?int
    {
        if (! Schema::hasTable('languages')) {
            return null;
        }

        try {
            return Language::query()->where('code', $locale)->value('id')
                ?? Language::query()->where('code', Language::baseCode($locale))->value('id');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  list<string>  $row
     */
    private function csvRow(array $row): string
    {
        $escaped = array_map(function (string $cell): string {
            if (str_contains($cell, ',') || str_contains($cell, '"') || str_contains($cell, "\n")) {
                return '"'.str_replace('"', '""', $cell).'"';
            }

            return $cell;
        }, $row);

        return implode(',', $escaped);
    }
}
