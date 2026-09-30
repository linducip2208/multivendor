<?php

declare(strict_types=1);

namespace App\Shipping;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Basis capability-declared untuk provider pengiriman.
 *
 * - Kredensial dibaca dari config('services.shipping.<code>') + env.
 * - TANPA kredensial: semua operasi stub deterministik lokal (tanpa jaringan).
 * - DENGAN kredensial: memakai Laravel HTTP client (dapat di-fake via
 *   Http::fake() + Http::preventStrayRequests() di test). Timeout/gangguan
 *   jaringan -> ShippingException BI/EN (tidak pernah meledak mentah).
 */
abstract class AbstractShippingProvider implements ShippingProviderInterface
{
    protected int $timeoutSeconds = 10;

    /** Kode unik provider, mis. "dhl". */
    abstract protected function code(): string;

    /** Nama tampilan, mis. "DHL". */
    abstract protected function displayName(): string;

    /** Tarif dasar per kg (IDR) untuk stub deterministik. */
    abstract protected function baseRatePerKg(): float;

    /** Ongkos dasar (IDR) untuk stub deterministik. */
    abstract protected function baseFee(): float;

    /** Estimasi hari untuk stub deterministik. */
    abstract protected function etaDays(): int;

    /** Daftar layanan yang JUJUR didukung. */
    abstract protected function supportedServices(): array;

    /** Daftar negara ISO-2 yang JUJUR didukung ([] = semua). */
    protected function supportedCountries(): array
    {
        return [];
    }

    protected function maxWeightKg(): float
    {
        return 1000.0;
    }

    protected function supportsLabels(): bool
    {
        return true;
    }

    protected function supportsPickup(): bool
    {
        return true;
    }

    protected function supportsTracking(): bool
    {
        return true;
    }

    /** Endpoint HTTP bila kredensial lengkap (null = stub saja). */
    protected function endpoint(): ?string
    {
        return null;
    }

    public function getCode(): string
    {
        return $this->code();
    }

    public function getName(): string
    {
        return $this->displayName();
    }

    public function capabilities(): array
    {
        return [
            'code' => $this->code(),
            'name' => $this->displayName(),
            'countries' => $this->supportedCountries(),
            'services' => $this->supportedServices(),
            'max_weight_kg' => $this->maxWeightKg(),
            'tracking' => $this->supportsTracking(),
            'label' => $this->supportsLabels(),
            'pickup' => $this->supportsPickup(),
            'cancel' => true,
            'live_enabled' => $this->isEnabled(),
        ];
    }

    public function supportsCountry(string $country): bool
    {
        $list = $this->supportedCountries();

        return $list === [] || in_array(strtoupper(trim($country)), array_map('strtoupper', $list), true);
    }

    public function supportsService(string $service): bool
    {
        return in_array(strtolower(trim($service)), array_map('strtolower', $this->supportedServices()), true);
    }

    /** True bila kredensial lengkap sehingga panggilan HTTP diizinkan. */
    public function isEnabled(): bool
    {
        $cfg = $this->config();

        return ($cfg['api_key'] ?? '') !== '' && ($this->endpoint() !== null);
    }

    /** @return array{api_key:string,base_url:string} */
    protected function config(): array
    {
        $cfg = (array) config('services.shipping.'.$this->code(), []);

        return [
            'api_key' => (string) ($cfg['api_key'] ?? $cfg['key'] ?? ''),
            'base_url' => (string) ($cfg['base_url'] ?? $cfg['url'] ?? ($this->endpoint() ?? '')),
        ];
    }

    public function quote(array $shipment): array
    {
        $this->guardShipment($shipment);
        $service = strtolower((string) ($shipment['service'] ?? $this->supportedServices()[0]));
        $weight = max(0.1, (float) ($shipment['weight_kg'] ?? 1.0));

        if ($this->isEnabled()) {
            try {
                $response = Http::timeout($this->timeoutSeconds)
                    ->acceptJson()
                    ->withHeaders(['User-Agent' => 'multivendor-shipping/1.0'])
                    ->post(rtrim($this->config()['base_url'], '/').'/rates', [
                        'origin' => $shipment['origin'] ?? null,
                        'destination' => $shipment['destination'] ?? null,
                        'weight_kg' => $weight,
                        'service' => $service,
                    ]);
                if ($response->successful()) {
                    $data = $response->json();
                    if (is_array($data) && isset($data['amount'])) {
                        return [
                            'provider' => $this->code(),
                            'service' => $service,
                            'amount' => (float) $data['amount'],
                            'currency' => strtoupper((string) ($data['currency'] ?? 'IDR')),
                            'eta_days' => (int) ($data['eta_days'] ?? $this->etaDays()),
                            'raw' => $data,
                        ];
                    }
                }
                // Respons tak terduga -> jatuh ke stub deterministik (fail-open aman untuk tarif).
            } catch (ConnectionException $e) {
                throw new ShippingException(
                    'Layanan '.$this->displayName().' tidak terjangkau.',
                    $this->displayName().' service is unreachable.',
                    ['provider' => $this->code()],
                    $e
                );
            }
        }

        // Stub deterministik: tarif = base + perKg * berat (tanpa jaringan).
        $amount = round($this->baseFee() + $this->baseRatePerKg() * $weight, 2);

        return [
            'provider' => $this->code(),
            'service' => $service,
            'amount' => $amount,
            'currency' => 'IDR',
            'eta_days' => $this->etaDays(),
            'raw' => ['stub' => true, 'provider' => $this->code()],
        ];
    }

    public function createLabel(array $shipment): array
    {
        $this->guardShipment($shipment);
        if (! $this->supportsLabels()) {
            throw new ShippingException(
                $this->displayName().' tidak mendukung pembuatan label.',
                $this->displayName().' does not support label creation.',
                ['provider' => $this->code()]
            );
        }
        $seed = substr(sha1($this->code().json_encode($shipment)), 0, 12);

        return [
            'tracking_number' => strtoupper($this->code()).'-'.$seed,
            'label_url' => null,
            'status' => 'label_created',
            'raw' => ['stub' => true, 'provider' => $this->code()],
        ];
    }

    public function track(string $trackingNumber): array
    {
        $trackingNumber = trim($trackingNumber);
        if ($trackingNumber === '') {
            throw new ShippingException(
                'Nomor pelacakan wajib diisi.',
                'Tracking number is required.',
                ['provider' => $this->code()]
            );
        }
        if (! $this->supportsTracking()) {
            throw new ShippingException(
                $this->displayName().' tidak mendukung pelacakan.',
                $this->displayName().' does not support tracking.',
                ['provider' => $this->code()]
            );
        }

        return [
            'tracking_number' => $trackingNumber,
            'status' => 'in_transit',
            'history' => [
                ['status' => 'label_created', 'at' => now()->toIso8601String()],
                ['status' => 'in_transit', 'at' => now()->toIso8601String()],
            ],
            'raw' => ['stub' => true, 'provider' => $this->code()],
        ];
    }

    public function requestPickup(array $shipment): array
    {
        $this->guardShipment($shipment);
        if (! $this->supportsPickup()) {
            throw new ShippingException(
                $this->displayName().' tidak mendukung penjemputan.',
                $this->displayName().' does not support pickup.',
                ['provider' => $this->code()]
            );
        }
        $seed = 'PU-'.substr(sha1($this->code().json_encode($shipment).'pickup'), 0, 10);

        return ['pickup_id' => strtoupper($seed), 'status' => 'pickup_scheduled', 'raw' => ['stub' => true]];
    }

    public function cancel(string $trackingNumber): array
    {
        $trackingNumber = trim($trackingNumber);
        if ($trackingNumber === '') {
            throw new ShippingException(
                'Nomor pelacakan wajib diisi.',
                'Tracking number is required.',
                ['provider' => $this->code()]
            );
        }

        return ['tracking_number' => $trackingNumber, 'status' => 'cancelled', 'raw' => ['stub' => true]];
    }

    protected function guardShipment(array $shipment): void
    {
        $destination = strtoupper(trim((string) ($shipment['destination'] ?? '')));
        if ($destination !== '' && ! $this->supportsCountry($destination)) {
            throw new ShippingException(
                'Negara '.$destination.' tidak didukung oleh '.$this->displayName().'.',
                'Country '.$destination.' is not supported by '.$this->displayName().'.',
                ['provider' => $this->code(), 'country' => $destination]
            );
        }
        $service = (string) ($shipment['service'] ?? '');
        if ($service !== '' && ! $this->supportsService($service)) {
            throw new ShippingException(
                'Layanan '.$service.' tidak didukung oleh '.$this->displayName().'.',
                'Service '.$service.' is not supported by '.$this->displayName().'.',
                ['provider' => $this->code(), 'service' => $service]
            );
        }
        $weight = (float) ($shipment['weight_kg'] ?? 1.0);
        if ($weight <= 0 || $weight > $this->maxWeightKg()) {
            throw new ShippingException(
                'Berat kiriman di luar batas '.$this->displayName().'.',
                'Shipment weight is out of '.$this->displayName().' limits.',
                ['provider' => $this->code(), 'weight_kg' => $weight]
            );
        }
    }
}
