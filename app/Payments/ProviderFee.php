<?php

declare(strict_types=1);

namespace App\Payments;

use App\Models\Provider;

/**
 * Biaya provider: persen + flat dari katalog/config, deterministik.
 * Sumber: ProviderCatalog (fee_percent/fee_flat) dioverride config provider
 * (config.fee_percent / config.fee_flat bila ada). Tanpa tebakan.
 */
final class ProviderFee
{
    /**
     * @return array{fee:float,percent:float,flat:float,currency:string,source:string}
     */
    public static function for(Provider $provider, float $amount, string $currency = 'IDR'): array
    {
        $amount = max(0.0, $amount);
        $currency = strtoupper(trim($currency)) ?: 'IDR';
        $config = is_array($provider->config) ? $provider->config : [];

        $percent = isset($config['fee_percent']) && is_numeric($config['fee_percent'])
            ? (float) $config['fee_percent']
            : null;
        $flat = isset($config['fee_flat']) && is_numeric($config['fee_flat'])
            ? (float) $config['fee_flat']
            : null;
        $source = 'config';

        if ($percent === null || $flat === null) {
            $catalog = ProviderCatalog::find((string) ($config['gateway'] ?? $provider->api_format ?? ''));
            // ProviderCatalog::find memakai code (midtrans/xendit/...) sedangkan
            // api_format memakai (midtrans-snap/...); coba padankan keduanya.
            if ($catalog === null) {
                foreach (ProviderCatalog::all() as $entry) {
                    if (strtolower((string) ($entry['api_format'] ?? '')) === strtolower((string) $provider->api_format)) {
                        $catalog = $entry;
                        break;
                    }
                }
            }
            if ($catalog !== null) {
                $percent ??= (float) $catalog['fee_percent'];
                $flat ??= (float) $catalog['fee_flat'];
                $source = 'catalog';
            } else {
                $percent ??= 0.0;
                $flat ??= 0.0;
                $source = 'default';
            }
        }

        $fee = round($amount * max(0.0, $percent) / 100 + max(0.0, $flat), 2);

        return ['fee' => $fee, 'percent' => $percent, 'flat' => $flat, 'currency' => $currency, 'source' => $source];
    }
}
