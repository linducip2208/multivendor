<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\LicenseClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\View::composer('*', function ($view) {
            $settings = \Illuminate\Support\Facades\Cache::remember('whitelabel_branding', 3600, function () {
                $brandColor = \App\Models\SystemSetting::get('brand_color', '#4F46E5');
                return [
                    'logo' => \App\Models\SystemSetting::get('logo_url'),
                    'favicon' => \App\Models\SystemSetting::get('favicon_url'),
                    'brandColor' => $brandColor,
                    'brandColorDark' => self::darkenHex($brandColor, 0.75),
                    'appName' => \App\Models\SystemSetting::get('app_name', config('app.name')),
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
