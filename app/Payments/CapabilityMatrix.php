<?php

declare(strict_types=1);

namespace App\Payments;

/**
 * Deklarasi kapabilitas per gateway / Per-gateway capability declaration.
 *
 * Sumber kebenaran untuk supportsCurrency/supportsCountry/supportsPaymentMethod
 * sehingga router bisa menolak lebih awal tanpa memanggil gateway.
 */
final class CapabilityMatrix
{
    /** @var array<string, array{currencies:string[],countries:string[],methods:string[]}> */
    private array $matrix = [];

    /**
     * @param string[] $currencies ISO 4217, mis. ["IDR","USD"]
     * @param string[] $countries  ISO 3166-1 alpha-2, mis. ["ID","US"]
     * @param string[] $methods    Nilai baku PaymentMethod::* (alias otomatis dinormalisasi)
     */
    public function define(string $gateway, array $currencies, array $countries, array $methods): self
    {
        $this->matrix[strtolower($gateway)] = [
            'currencies' => array_values(array_unique(array_map('strtoupper', $currencies))),
            'countries' => array_values(array_unique(array_map('strtoupper', $countries))),
            'methods' => array_values(array_unique(array_map([PaymentMethod::class, 'normalize'], $methods))),
        ];

        return $this;
    }

    /** @return array{currencies:string[],countries:string[],methods:string[]} */
    public function for(string $gateway): array
    {
        return $this->matrix[strtolower($gateway)]
            ?? ['currencies' => [], 'countries' => [], 'methods' => []];
    }

    public function supports(string $gateway, ?string $currency = null, ?string $country = null, ?string $method = null): bool
    {
        $caps = $this->for($gateway);

        if ($currency !== null && $caps['currencies'] !== [] && ! in_array(strtoupper($currency), $caps['currencies'], true)) {
            return false;
        }
        if ($country !== null && $caps['countries'] !== [] && ! in_array(strtoupper($country), $caps['countries'], true)) {
            return false;
        }
        if ($method !== null && $caps['methods'] !== [] && ! in_array(PaymentMethod::normalize($method), $caps['methods'], true)) {
            return false;
        }

        return true;
    }

    /** @return string[] */
    public function gateways(): array
    {
        return array_keys($this->matrix);
    }
}
