<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\LicenseClient::class);
    }

    public function boot(): void
    {
        RateLimiter::for('payment-webhook', fn (Request $request) => Limit::perMinute(120)->by(($request->route('provider')?->id ?? 'unknown').'|'.$request->ip()));
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
        // Storefront read/write guards (controllers reference these via
        // throttle:<name>; defining them here keeps web + api throttling
        // consistent and avoids silent fallback to the default limiter).
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('track-order', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        // NOTE: api:auth / api:write / api:search live in
        // RateLimitRegistry (colon form, registered from routes/api.php).
        // Do not re-register comma-named duplicates here — the throttle
        // middleware splits parameters on commas, so only the colon form
        // resolves to the intended limiter.
        \Illuminate\Support\Facades\View::composer('*', function ($view) {
            // Unit/feature tests boot the full app: skip the SystemSetting DB
            // hit entirely so sqlite :memory: suites never depend on project
            // migrations. Cached branding still applies in http/console.
            if (app()->runningUnitTests()) {
                $view->with('whitelabel', self::defaultBranding());

                return;
            }

            $settings = \Illuminate\Support\Facades\Cache::remember('whitelabel_branding', 3600, function () {
                $themePrimary = \App\Models\SystemSetting::get('theme_primary_color')
                    ?: \App\Models\SystemSetting::get('brand_color')
                    ?: '#4F46E5';

                $themeDark = \App\Models\SystemSetting::get('theme_primary_dark')
                    ?: \App\Models\SystemSetting::get('brand_color_dark')
                    ?: self::darkenHex($themePrimary, 0.75);

                $borderRadius = \App\Models\SystemSetting::get('theme_border_radius', '14');
                $fontFamily = \App\Models\SystemSetting::get('theme_font_family', 'Inter');
                $sidebarWidth = \App\Models\SystemSetting::get('theme_sidebar_width', '250');
                $topbarHeight = \App\Models\SystemSetting::get('theme_topbar_height', '60');
                $darkMode = \App\Models\SystemSetting::get('theme_dark_mode_default', false);
                $showLang = \App\Models\SystemSetting::get('theme_show_language_switcher', '1');
                $logoText = \App\Models\SystemSetting::get('theme_logo_text');

                return [
                    'logo' => \App\Models\SystemSetting::get('logo_url'),
                    'favicon' => \App\Models\SystemSetting::get('favicon_url'),
                    'brandColor' => $themePrimary,
                    'brandColorDark' => $themeDark,
                    'appName' => $logoText ?: \App\Models\SystemSetting::get('app_name', config('app.name')),
                    'borderRadius' => $borderRadius,
                    'fontFamily' => $fontFamily,
                    'sidebarWidth' => $sidebarWidth,
                    'topbarHeight' => $topbarHeight,
                    'darkMode' => $darkMode,
                    'showLang' => $showLang,
                ];
            });
            $view->with('whitelabel', $settings);
        });
    }

    protected static function darkenHex(string $hex, float $factor): string
    {
        $hex = ltrim(trim($hex), '#');
        if (! preg_match('/\A[0-9a-fA-F]{3}([0-9a-fA-F]{3})?\z/', $hex)) {
            return '#4338ca';
        }
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $factor = min(1.0, max(0.0, $factor));
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return '#' . sprintf('%02x%02x%02x',
            max(0, (int)($r * $factor)),
            max(0, (int)($g * $factor)),
            max(0, (int)($b * $factor))
        );
    }

    /**
     * Branding defaults used when the DB-backed settings lookup is skipped
     * (unit tests) or yields nothing. Mirrors the shape of the cached array.
     *
     * @return array<string, mixed>
     */
    protected static function defaultBranding(): array
    {
        return [
            'logo' => null,
            'favicon' => null,
            'brandColor' => '#4F46E5',
            'brandColorDark' => '#4338ca',
            'appName' => config('app.name'),
            'borderRadius' => '14',
            'fontFamily' => 'Inter',
            'sidebarWidth' => '250',
            'topbarHeight' => '60',
            'darkMode' => false,
            'showLang' => '1',
        ];
    }
}
