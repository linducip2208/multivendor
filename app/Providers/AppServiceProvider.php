<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\LicenseClient::class);
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\View::composer('*', function ($view) {
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
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return '#' . sprintf('%02x%02x%02x',
            max(0, (int)($r * $factor)),
            max(0, (int)($g * $factor)),
            max(0, (int)($b * $factor))
        );
    }
}
