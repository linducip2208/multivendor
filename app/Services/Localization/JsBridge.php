<?php

declare(strict_types=1);

namespace App\Services\Localization;

/**
 * Jembatan string terjemahan untuk JavaScript (window.__trans).
 *
 * Aman XSS: payload selalu di-encode dengan flag HEX (TAG/AMP/APOS/QUOT)
 * dan dirender via satu <script> tag dengan tipe application/json mindset —
 * tidak ada interpolasi mentah ke JS.
 */
class JsBridge
{
    /**
     * @param array<string,string> $messages
     * @return array{locale:string,fallback:string,available:list<string>,messages:array<string,string>}
     */
    public static function payload(?string $locale = null, array $messages = []): array
    {
        $locale ??= (string) app()->getLocale();

        try {
            $languages = app(LanguageService::class);
            $available = $languages->activeCodes();
            $fallback = (string) config('app.fallback_locale', 'id');
        } catch (\Throwable) {
            $available = ['id', 'en'];
            $fallback = (string) config('app.fallback_locale', 'id');
        }

        $clean = [];
        foreach ($messages as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $clean[mb_substr($key, 0, 120)] = mb_substr($value, 0, 2000);
            }
        }

        return [
            'locale' => $locale,
            'fallback' => $fallback,
            'available' => array_values($available),
            'messages' => $clean,
        ];
    }

    /**
     * @param array<string,string> $messages
     */
    public static function scriptTag(?string $locale = null, array $messages = [], string $variable = '__trans'): string
    {
        $variable = preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*$/', $variable) === 1 ? $variable : '__trans';
        $json = json_encode(
            self::payload($locale, $messages),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        if (! is_string($json) || $json === '') {
            $json = '{}';
        }

        return '<script>window.'.$variable.' = '.$json.';</script>';
    }
}
