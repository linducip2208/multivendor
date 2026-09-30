<?php

declare(strict_types=1);

namespace App\Services\Geo;

use App\Models\Country;
use Illuminate\Support\Facades\Schema;

/**
 * Resolusi negara database-driven: currency, bahasa, hints pembayaran & pengiriman.
 *
 * Seluruh data berasal dari tabel `countries`. Fallback netral (USD/en/UTC)
 * dipakai hanya bila tabel kosong / negara tidak dikenal — bukan hardcode
 * aturan negara tertentu.
 */
class CountryService
{
    /** Default netral untuk fallback (bukan aturan negara spesifik). */
    public const FALLBACK = [
        'currency_code' => 'USD',
        'locale' => 'en',
        'timezone' => 'UTC',
        'payment_hints' => [],
        'shipping_hints' => [],
    ];

    public function resolve(?string $iso2): ?Country
    {
        $iso2 = strtoupper(trim((string) $iso2));

        if ($iso2 === '' || ! $this->tablesReady()) {
            return null;
        }

        return Country::query()
            ->where('iso2', $iso2)
            ->where('is_active', true)
            ->first();
    }

    public function resolveOrFail(?string $iso2): Country
    {
        $country = $this->resolve($iso2);

        abort_if($country === null, 404, "Negara [{$iso2}] tidak dikenal.");

        return $country;
    }

    public function defaultCountry(): ?Country
    {
        if (! $this->tablesReady()) {
            return null;
        }

        return Country::query()->where('is_active', true)->orderBy('id')->first();
    }

    /**
     * Konteks presentasi + operasional untuk satu negara, dengan fallback
     * berlapis: negara diminta → negara default (baris pertama aktif) → netral.
     *
     * @return array{country:?Country,iso2:?string,currency_code:string,locale:string,language:string,timezone:string,payment_hints:array,shipping_hints:array,is_fallback:bool}
     */
    public function contextFor(?string $iso2): array
    {
        $country = $this->resolve($iso2) ?? $this->defaultCountry();

        if ($country === null) {
            return [
                'country' => null,
                'iso2' => $iso2 ? strtoupper($iso2) : null,
                'currency_code' => self::FALLBACK['currency_code'],
                'locale' => self::FALLBACK['locale'],
                'language' => $this->languageFromLocale(self::FALLBACK['locale']),
                'timezone' => self::FALLBACK['timezone'],
                'payment_hints' => [],
                'shipping_hints' => [],
                'is_fallback' => true,
            ];
        }

        return [
            'country' => $country,
            'iso2' => $country->iso2,
            'currency_code' => (string) $country->currency_code,
            'locale' => (string) $country->locale,
            'language' => $this->languageFromLocale((string) $country->locale),
            'timezone' => (string) $country->timezone,
            'payment_hints' => $country->payment_hints ?? [],
            'shipping_hints' => $country->shipping_hints ?? [],
            'is_fallback' => $iso2 === null || strtoupper((string) $iso2) !== $country->iso2,
        ];
    }

    public function currencyFor(?string $iso2): string
    {
        return $this->contextFor($iso2)['currency_code'];
    }

    public function localeFor(?string $iso2): string
    {
        return $this->contextFor($iso2)['locale'];
    }

    public function languageFor(?string $iso2): string
    {
        return $this->contextFor($iso2)['language'];
    }

    public function timezoneFor(?string $iso2): string
    {
        return $this->contextFor($iso2)['timezone'];
    }

    /** @return array<int|string,mixed> */
    public function paymentHintsFor(?string $iso2): array
    {
        return $this->contextFor($iso2)['payment_hints'];
    }

    /** @return array<int|string,mixed> */
    public function shippingHintsFor(?string $iso2): array
    {
        return $this->contextFor($iso2)['shipping_hints'];
    }

    public function languageFromLocale(string $locale): string
    {
        $locale = strtolower(str_replace('-', '_', trim($locale)));

        if ($locale === '') {
            return 'en';
        }

        return explode('_', $locale)[0];
    }

    private function tablesReady(): bool
    {
        try {
            return Schema::hasTable('countries');
        } catch (\Throwable) {
            return false;
        }
    }
}
