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
        // Pratinjau via ?theme_preview= (hanya untuk admin login).
        try {
            $preview = (string) (request()->query('theme_preview', ''));
            if ($preview !== '' && auth('admin')->check()) {
                $codes = array_map(static fn (array $t): string => $t['code'], $this->available());
                if (in_array($preview, $codes, true)) {
                    $path = resource_path('views/themes/'.$preview.'/'.$view.'.blade.php');
                    $nested = resource_path('views/themes/'.$preview.'/'.str_replace('.', '/', $view).'.blade.php');
                    if (is_file($path) || is_file($nested)) {
                        View::addNamespace('theme_preview', dirname(is_file($path) ? $path : $nested));
                        return 'theme_preview::'.basename(is_file($path) ? $path : $nested, '.blade.php');
                    }
                }
            }
        } catch (\Throwable) {
        }

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

    /* ── ADITIF deepening: preview/duplicate/rollback/scheduled + validasi ── */

    /** Validasi tema: theme.json + view esensial. @return array{ok: bool, errors: list<string>} */
    public function validate(string $theme): array
    {
        $errors = [];
        $theme = trim($theme);
        if ($theme === '' || str_contains($theme, '..') || str_contains($theme, '/')) {
            return ['ok' => false, 'errors' => ['Kode tema tidak valid.']];
        }
        if ($theme === self::DEFAULT) {
            return ['ok' => true, 'errors' => []];
        }
        $base = resource_path('views/themes/'.$theme);
        if (! is_dir($base)) {
            return ['ok' => false, 'errors' => ["Folder tema \"{$theme}\" tidak ditemukan."]];
        }
        $manifest = $base.'/theme.json';
        if (! is_file($manifest)) {
            $errors[] = 'theme.json hilang (nama + versi wajib).';
        } else {
            $data = json_decode((string) @file_get_contents($manifest), true);
            if (! is_array($data) || trim((string) ($data['name'] ?? '')) === '') {
                $errors[] = 'theme.json wajib berisi "name".';
            }
        }
        // Esensial lunak: peringatkan bila tak ada override satupun.
        $blades = glob($base.'/*.blade.php') ?: [];
        if ($blades === [] && glob($base.'/*/*.blade.php') === []) {
            $errors[] = 'Belum ada override blade — tema tidak mengubah apa pun (boleh diabaikan untuk token/warna saja).';
        }

        // Keras bila ada directive berbahaya di override.
        foreach (array_slice(array_merge(glob($base.'/*.blade.php') ?: [], glob($base.'/*/*.blade.php') ?: []), 0, 50) as $file) {
            $content = (string) @file_get_contents($file);
            if (preg_match('/@php\s*\(\s*exec|@php\s*\(\s*shell_exec|passthru\s*\(/i', $content)) {
                $errors[] = 'Blokir: '.basename((string) $file).' mengandung eksekusi shell.';
            }
        }

        $hard = array_filter($errors, fn (string $e): bool => str_starts_with($e, 'Blokir:') || str_contains($e, 'tidak ditemukan') || str_contains($e, 'tidak valid') || str_contains($e, '"name"'));
        if ($hard !== [] && count($hard) === count($errors)) {
            return ['ok' => false, 'errors' => $errors];
        }

        return ['ok' => $hard === [], 'errors' => $errors];
    }

    /** Snapshot pengaturan tema aktif (rollback). @return array<string, mixed> */
    public function snapshot(?string $label = null, ?int $actorId = null): array
    {
        $entry = [
            'at' => now()->format('Y-m-d H:i:s'),
            'actor_id' => $actorId,
            'label' => mb_substr(trim((string) ($label ?? 'manual')), 0, 120),
            'theme' => $this->active(),
            'settings' => $this->dumpThemeSettings(),
        ];
        $history = $this->snapshots();
        array_unshift($history, $entry);
        $this->storeSnapshots(array_slice($history, 0, 10));

        return $entry;
    }

    /** @return list<array<string, mixed>> */
    public function snapshots(): array
    {
        try {
            $raw = \App\Models\SystemSetting::get('theme_snapshots_json', '');
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];

            return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Rollback ke snapshot index. */
    public function rollback(int $index, ?int $actorId = null): string
    {
        $history = $this->snapshots();
        if (! isset($history[$index]) || ! is_array($history[$index])) {
            throw new \InvalidArgumentException('Snapshot tidak ditemukan.');
        }
        $snap = $history[$index];
        $this->snapshot('auto-sebelum-rollback', $actorId);
        $theme = (string) ($snap['theme'] ?? self::DEFAULT);
        $check = $this->validate($theme);
        if (! $check['ok']) {
            throw new \InvalidArgumentException('Rollback dibatalkan: '.implode(' ', $check['errors']));
        }
        $this->activate($theme);
        foreach ((array) ($snap['settings'] ?? []) as $key => $value) {
            try {
                \App\Models\SystemSetting::set((string) $key, $value === null ? null : (string) $value);
            } catch (\Throwable) {
            }
        }

        return $theme;
    }

    /** Duplikat tema (folder copy + theme.json name baru). */
    public function duplicate(string $from, string $to, ?string $newName = null): string
    {
        $from = trim($from);
        $to = trim(preg_replace('/[^A-Za-z0-9_\-]/', '-', $to) ?? '');
        if ($from === '' || $to === '' || str_contains($to, '..')) {
            throw new \InvalidArgumentException('Kode tema asal/tujuan tidak valid.');
        }
        $src = resource_path('views/themes/'.$from);
        $dst = resource_path('views/themes/'.$to);
        if (! is_dir($src) || is_dir($dst)) {
            throw new \InvalidArgumentException('Tema asal hilang atau tujuan sudah ada.');
        }
        @mkdir($dst, 0755, true);
        foreach (glob($src.'/*') ?: [] as $file) {
            if (is_file($file)) {
                @copy($file, $dst.'/'.basename($file));
            }
        }
        $manifest = $dst.'/theme.json';
        $data = is_file($manifest) ? (json_decode((string) @file_get_contents($manifest), true) ?: []) : [];
        $data['name'] = trim((string) ($newName ?? ($data['name'] ?? $from).' (salinan)'));
        @file_put_contents($manifest, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $to;
    }

    /**
     * Pratinjau: resolve view tanpa mengaktifkan (kembalikan nama view
     * yang akan dipakai bila tema $theme aktif).
     */
    public function previewResolve(string $theme, string $view): string
    {
        $theme = trim($theme);
        if ($theme === '' || $theme === self::DEFAULT) {
            return $view;
        }
        $path = resource_path('views/themes/'.$theme.'/'.$view.'.blade.php');
        $pathNested = resource_path('views/themes/'.$theme.'/'.str_replace('.', '/', $view).'.blade.php');
        if (is_file($path) || is_file($pathNested)) {
            return 'theme-preview::'.$view;
        }

        return $view;
    }

    /** Jadwalkan aktivasi tema (diproses scheduler/integrator). */
    public function scheduleActivation(string $theme, string $at, ?int $actorId = null): array
    {
        $check = $this->validate($theme);
        if (! $check['ok']) {
            throw new \InvalidArgumentException('Tema tidak valid: '.implode(' ', $check['errors']));
        }
        $ts = strtotime($at);
        if ($ts === false) {
            throw new \InvalidArgumentException('Jadwal aktivasi tidak valid.');
        }
        $entry = ['theme' => $theme, 'at' => date('Y-m-d H:i:s', $ts), 'actor_id' => $actorId, 'created_at' => now()->format('Y-m-d H:i:s')];
        \App\Models\SystemSetting::set('theme_scheduled_activation', json_encode($entry, JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    /** Ambil + terapkan aktivasi terjadwal bila waktunya tiba. */
    public function runScheduledActivation(): ?string
    {
        try {
            $raw = \App\Models\SystemSetting::get('theme_scheduled_activation', '');
            $entry = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
            if (! is_array($entry) || ($entry['theme'] ?? '') === '' || ($entry['at'] ?? '') === '') {
                return null;
            }
            if (strtotime((string) $entry['at']) > time()) {
                return null;
            }
            $this->snapshot('auto-sebelum-aktivasi-terjadwal');
            $this->activate((string) $entry['theme']);
            \App\Models\SystemSetting::set('theme_scheduled_activation', null);

            return (string) $entry['theme'];
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string, string|null> */
    private function dumpThemeSettings(): array
    {
        $out = [];
        $keys = ['theme_primary_color', 'theme_primary_dark', 'theme_border_radius', 'theme_font_family',
            'theme_sidebar_width', 'theme_topbar_height', 'theme_logo_text', 'theme_favicon',
            'theme_title_id', 'theme_title_en', 'theme_tagline_id', 'theme_tagline_en', self::ACTIVE_KEY];
        foreach ($keys as $key) {
            try {
                $out[$key] = \App\Models\SystemSetting::get($key);
            } catch (\Throwable) {
                $out[$key] = null;
            }
        }

        return $out;
    }

    /** @param  list<array<string, mixed>>  $history */
    private function storeSnapshots(array $history): void
    {
        try {
            \App\Models\SystemSetting::set('theme_snapshots_json', json_encode($history, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable) {
        }
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
