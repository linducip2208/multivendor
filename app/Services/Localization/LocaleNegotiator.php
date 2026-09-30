<?php

declare(strict_types=1);

namespace App\Services\Localization;

use App\Models\Language;
use Illuminate\Http\Request;

/**
 * Negosiasi locale. Prioritas: session > user > browser (Accept-Language) > default.
 */
class LocaleNegotiator
{
    public function __construct(private LanguageService $languages)
    {
    }

    public function negotiate(
        ?string $sessionLocale = null,
        ?string $userLocale = null,
        ?string $acceptLanguageHeader = null,
        ?string $default = null
    ): string {
        $default ??= $this->languages->defaultCode();
        $available = $this->languages->activeCodes();

        foreach ([$sessionLocale, $userLocale] as $candidate) {
            $matched = $this->match($candidate, $available);
            if ($matched !== null) {
                return $matched;
            }
        }

        foreach ($this->parseAcceptLanguage((string) $acceptLanguageHeader) as $candidate) {
            $matched = $this->match($candidate, $available);
            if ($matched !== null) {
                return $matched;
            }
        }

        $matched = $this->match($default, $available);

        return $matched ?? 'en';
    }

    public function negotiateFromRequest(Request $request, ?string $default = null): string
    {
        $sessionLocale = null;
        try {
            $sessionLocale = $request->hasSession() ? $request->session()->get('locale') : null;
        } catch (\Throwable) {
            $sessionLocale = null;
        }

        $userLocale = null;
        try {
            $user = $request->user();
            $userLocale = $user?->locale ?? $user?->preferred_locale ?? null;
        } catch (\Throwable) {
            $userLocale = null;
        }

        return $this->negotiate(
            is_string($sessionLocale) ? $sessionLocale : null,
            is_string($userLocale) ? $userLocale : null,
            $request->header('Accept-Language'),
            $default
        );
    }

    /**
     * @param  list<string>  $available
     */
    public function match(?string $candidate, array $available): ?string
    {
        if ($candidate === null || trim($candidate) === '') {
            return null;
        }

        $candidate = Language::canonicalize($candidate);
        $lowerAvailable = [];
        foreach ($available as $code) {
            $lowerAvailable[strtolower(Language::canonicalize($code))] = $code;
        }

        // 1. Persis (case-insensitive).
        if (isset($lowerAvailable[strtolower($candidate)])) {
            return $lowerAvailable[strtolower($candidate)];
        }

        // 2. Basis bahasa (id-ID -> id).
        $base = Language::baseCode($candidate);
        if (isset($lowerAvailable[$base])) {
            return $lowerAvailable[$base];
        }

        // 3. Varian tersedia yang satu basis (id -> id-ID).
        foreach ($lowerAvailable as $normalized => $original) {
            if (Language::baseCode($normalized) === $base) {
                return $original;
            }
        }

        return null;
    }

    /**
     * @return list<string> kode terurut berdasarkan q-value, mis. ['id-ID','id','en']
     */
    public function parseAcceptLanguage(string $header): array
    {
        $header = trim($header);
        if ($header === '') {
            return [];
        }

        $scored = [];
        foreach (explode(',', $header) as $part) {
            $segments = explode(';', trim($part));
            $code = trim($segments[0]);
            if ($code === '' || $code === '*') {
                continue;
            }

            $quality = 1.0;
            foreach (array_slice($segments, 1) as $param) {
                $param = trim($param);
                if (str_starts_with($param, 'q=')) {
                    $quality = max(0.0, min(1.0, (float) substr($param, 2)));
                }
            }

            $scored[] = ['code' => Language::canonicalize($code), 'q' => $quality];
        }

        usort($scored, fn (array $a, array $b) => $b['q'] <=> $a['q']);

        return array_values(array_unique(array_column($scored, 'code')));
    }
}
