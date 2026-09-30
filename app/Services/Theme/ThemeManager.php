<?php

declare(strict_types=1);

namespace App\Services\Theme;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

/**
 * Manajer tema aktif + override view + settings multilingual dasar.
 *
 * ID: Tema aktif disimpan di setting "theme.active" (default "default").
 * Override view memakai namespace "theme" bila folder
 * resources/views/themes/<aktif> ada. Settings multilingual dasar memakai
 * kunci theme_title_<locale> / theme_tagline_<locale>.
 *
 * EN: Active theme is stored in the "theme.active" setting (default
 * "default"). View overrides use the "theme" namespace when the
 * resources/views/themes/<active> folder exists. Basic multilingual
 * settings use theme_title_<locale> / theme_tagline_<locale> keys.
 */
final class ThemeManager
{
    public const ACTIVE_KEY = 'theme.active';

    public const DEFAULT = 'default';

    /**
     * @return array<int, array{code:string,name:string,version:string,active:bool}>
     */
    public function available(): array
    {
        $themes = [[
            'code' => self::DEFAULT,
            'name' => 'Default',
            'version' => '1.0.0',
            'active' => $this->active() === self::DEFAULT,
        ]];

        $base = resource_path('views/themes');
        if (! is_dir($base)) {
            return $themes;
        }

        foreach (glob($base.'/*/theme.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (! is_array($data)) {
                continue;
            }
            $code = basename(dirname((string) $file));
            $themes[] = [
                'code' => $code,
                'name' => (string) ($data['name'] ?? $code),
                'version' => (string) ($data['version'] ?? '1.0.0'),
                'active' => $this->active() === $code,
            ];
        }

        return $themes;
    }

    public function active(): string
    {
        try {
            if (! Schema::hasTable('system_settings')) {
                return self::DEFAULT;
            }
            $value = \App\Models\SystemSetting::get(self::ACTIVE_KEY);

            return $value !== null && trim($value) !== '' ? trim($value) : self::DEFAULT;
        } catch (\Throwable) {
            return self::DEFAULT;
        }
    }

    public function activate(string $theme): void
    {
        $theme = trim($theme) !== '' ? trim($theme) : self::DEFAULT;
        $codes = array_map(static fn (array $t): string => $t['code'], $this->available());
        if (! in_array($theme, $codes, true) && $theme !== self::DEFAULT) {
            throw new \InvalidArgumentException("Tema \"{$theme}\" tidak tersedia. / Theme \"{$theme}\" is not available.");
        }

        try {
            \App\Models\SystemSetting::set(self::ACTIVE_KEY, $theme);
        } catch (\Throwable) {
        }
    }

    public function activePath(): ?string
    {
        $path = resource_path('views/themes/'.$this->active());
        if ($this->active() === self::DEFAULT || ! is_dir($path)) {
            return null;
        }

        return $path;
    }

    /**
     * Daftarkan namespace "theme" agar view bisa memakai theme::<view>.
     * Aman dipanggil berulang; no-op bila folder tema tidak ada.
     */
    public function register(): void
    {
        $path = $this->activePath();
        if ($path === null) {
            return;
        }

        try {
            View::addNamespace('theme', $path);
        } catch (\Throwable) {
        }
    }

    /**
     * Resolve view dengan override tema: kembalikan "theme::<view>" bila
     * ada, selain itu nama view asal. Tidak pernah melempar.
     */
    public function resolve(string $view): string
    {
        if ($this->activePath() === null) {
            return $view;
        }

        try {
            if (View::exists('theme::'.$view)) {
                return 'theme::'.$view;
            }
        } catch (\Throwable) {
        }

        return $view;
    }

    /**
     * Settings multilingual dasar tema aktif.
     *
     * @return array{locale:string,title:string,tagline:string}
     */
    public function settings(?string $locale = null): array
    {
        $locale = strtolower(substr((string) ($locale ?? app()->getLocale()), 0, 2));
        if (! in_array($locale, ['id', 'en'], true)) {
            $locale = 'id';
        }

        return [
            'locale' => $locale,
            'title' => (string) ($this->setting('theme_title_'.$locale) ?? $this->setting('theme_title_id') ?? ''),
            'tagline' => (string) ($this->setting('theme_tagline_'.$locale) ?? $this->setting('theme_tagline_id') ?? ''),
        ];
    }

    private function setting(string $key): ?string
    {
        try {
            if (! Schema::hasTable('system_settings')) {
                return null;
            }

            return \App\Models\SystemSetting::get($key);
        } catch (\Throwable) {
            return null;
        }
    }
}
