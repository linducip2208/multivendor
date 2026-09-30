<?php

declare(strict_types=1);

namespace App\Services\Localization;

use App\Models\Language;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Registri bahasa unlimited + flag RTL.
 */
class LanguageService
{
    public const CACHE_KEY = 'i18n:languages:active';

    public const RTL_CODES = ['ar', 'fa', 'he', 'ur', 'ps', 'dv', 'yi', 'ku'];

    /**
     * @return Collection<int, Language>
     */
    public function active(): Collection
    {
        if (! Schema::hasTable('languages')) {
            return collect($this->defaults())->map(fn (array $row) => new Language($row));
        }

        /** @var Collection<int, Language> $rows */
        $rows = Cache::remember(self::CACHE_KEY, 3600, fn () => Language::query()
            ->active()->ordered()->get());

        if ($rows->isEmpty()) {
            return collect($this->defaults())->map(fn (array $row) => new Language($row));
        }

        return $rows;
    }

    /**
     * @return list<string> kode aktif, mis. ['id','en','ar']
     */
    public function activeCodes(): array
    {
        return $this->active()->map(fn (Language $l) => (string) $l->code)->values()->all();
    }

    public function defaultCode(): string
    {
        $active = $this->active();
        $default = $active->firstWhere('is_default', true);

        if ($default instanceof Language) {
            return (string) $default->code;
        }

        if ($active->contains(fn (Language $l) => $l->code === 'id')) {
            return 'id';
        }

        return 'en';
    }

    public function find(string $code): ?Language
    {
        $code = Language::canonicalize($code);

        return $this->active()->first(fn (Language $l) => Language::canonicalize((string) $l->code) === $code);
    }

    public function isRtl(string $code): bool
    {
        $found = $this->find($code);

        if ($found instanceof Language) {
            return $found->isRtl();
        }

        return in_array(Language::baseCode($code), self::RTL_CODES, true);
    }

    public function direction(string $code): string
    {
        return $this->isRtl($code) ? 'rtl' : 'ltr';
    }

    /**
     * Daftarkan bahasa baru (unlimited). Aditif: tidak menonaktifkan yang lain.
     */
    public function register(string $code, string $name, ?string $nativeName = null, array $options = []): Language
    {
        $code = Language::canonicalize($code);
        $base = Language::baseCode($code);
        $rtl = (bool) ($options['is_rtl'] ?? in_array($base, self::RTL_CODES, true));

        $language = Language::updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'native_name' => $nativeName ?? $name,
                'direction' => $rtl ? 'rtl' : 'ltr',
                'is_rtl' => $rtl,
                'is_active' => (bool) ($options['is_active'] ?? true),
                'is_default' => (bool) ($options['is_default'] ?? false),
                'sort_order' => (int) ($options['sort_order'] ?? 0),
            ]
        );

        $this->forgetCache();

        return $language;
    }

    public function setActive(string $code, bool $active): void
    {
        Language::query()->where('code', Language::canonicalize($code))->update(['is_active' => $active]);
        $this->forgetCache();
    }

    /* ── ADITIF deepening: kelola bahasa + default + coverage ringkas ── */

    /** Tambah/aktifkan bahasa (validasi kode BCP-47 sederhana). */
    public function addLanguage(string $code, string $name, array $options = []): Language
    {
        $code = Language::canonicalize(trim($code));
        if (! preg_match('/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})?$/', $code)) {
            throw new \InvalidArgumentException("Kode bahasa \"{$code}\" tidak valid (cth. id, en, ar, ms).");
        }
        if (trim($name) === '') {
            throw new \InvalidArgumentException('Nama bahasa wajib diisi.');
        }

        return $this->register($code, trim($name), $options['native_name'] ?? null, $options);
    }

    /** Jadikan default (satu-satunya is_default). */
    public function setDefault(string $code): Language
    {
        $code = Language::canonicalize($code);
        $language = Language::query()->where('code', $code)->first();
        if (! $language) {
            throw new \InvalidArgumentException("Bahasa \"{$code}\" belum terdaftar.");
        }
        Language::query()->update(['is_default' => false]);
        $language->forceFill(['is_default' => true, 'is_active' => true])->save();
        $this->forgetCache();

        return $language->refresh();
    }

    /** Semua bahasa terdaftar (aktif + nonaktif) untuk panel admin. @return list<array<string, mixed>> */
    public function allManaged(): array
    {
        try {
            $rows = Language::query()->ordered()->get();
            if ($rows->isEmpty()) {
                return [];
            }

            return $rows->map(fn (Language $l): array => [
                'code' => (string) $l->code,
                'name' => (string) $l->name,
                'native_name' => (string) ($l->native_name ?? $l->name),
                'direction' => $this->direction((string) $l->code),
                'is_rtl' => $this->isRtl((string) $l->code),
                'is_active' => (bool) $l->is_active,
                'is_default' => (bool) $l->is_default,
                'sort_order' => (int) $l->sort_order,
            ])->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Ringkasan coverage string UI per locale aktif (pakai
     * TranslationRepository::coverage + MissingDetector::missingKeys).
     *
     * @return array<string, array{percent: float, translated: int, total: int, missing: int}>
     */
    public function coverageSummary(string $reference = 'en'): array
    {
        $out = [];
        try {
            $repo = app(TranslationRepository::class);
            $detector = app(MissingDetector::class);
            foreach ($this->activeCodes() as $code) {
                $coverage = $repo->coverage($code, $reference);
                $out[$code] = [
                    'percent' => (float) $coverage['percent'],
                    'translated' => (int) $coverage['translated'],
                    'total' => (int) $coverage['total_keys'],
                    'missing' => count($detector->missingKeys($code, $reference)),
                ];
            }
        } catch (\Throwable) {
        }

        return $out;
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function defaults(): array
    {
        return [
            ['code' => 'id', 'name' => 'Indonesian', 'native_name' => 'Bahasa Indonesia', 'direction' => 'ltr', 'is_rtl' => false, 'is_active' => true, 'is_default' => true, 'sort_order' => 0],
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'is_rtl' => false, 'is_active' => true, 'is_default' => false, 'sort_order' => 1],
        ];
    }
}
