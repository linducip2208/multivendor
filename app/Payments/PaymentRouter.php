<?php

declare(strict_types=1);

namespace App\Payments;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Router pembayaran: aturan prioritas dari database + fallback aman.
 *
 * - Prioritas: tabel `providers` (type=payment, is_active) diurutkan
 *   sort_order ASC; kolom `config.gateway` menunjuk nama gateway di registry.
 * - Bila tabel kosong / tak ada yang cocok, dipakai urutan registrasi registry.
 * - Guard idempotency: charge dengan kunci yang sama TIDAK PERNAH menagih
 *   dua kali — hasil pertama dikembalikan dari cache.
 */
final class PaymentRouter
{
    public const IDEMPOTENCY_TTL_SECONDS = 86400;

    private GatewayRegistry $registry;

    /** Kandidat in-memory (dipakai bila DB tak tersedia, mis. unit test murni). */
    /** @var string[] */
    private array $memoryGateways = [];

    public function __construct(?GatewayRegistry $registry = null)
    {
        $this->registry = $registry ?? new GatewayRegistry();
    }

    public function registry(): GatewayRegistry
    {
        return $this->registry;
    }

    /** Daftarkan urutan kandidat in-memory sebagai cadangan dari DB. */
    public function useGateways(string ...$names): self
    {
        $this->memoryGateways = array_values(array_unique(array_map('strtolower', $names)));

        return $this;
    }

    /**
     * Kandidat gateway terurut sesuai prioritas database.
     *
     * @param array{currency?:string,country?:string,method?:string} $context
     * @return array<int, array{name:string,provider_id:?int}>
     */
    public function candidates(array $context = []): array
    {
        $currency = isset($context['currency']) ? strtoupper((string) $context['currency']) : null;
        $country = isset($context['country']) ? strtoupper((string) $context['country']) : null;
        $method = isset($context['method']) ? PaymentMethod::normalize((string) $context['method']) : null;

        $fromDb = $this->candidatesFromDatabase();
        $pool = $fromDb !== [] ? $fromDb : $this->fallbackPool();

        $filtered = [];
        foreach ($pool as $candidate) {
            try {
                $gateway = $this->registry->resolve($candidate['name']);
            } catch (PaymentException) {
                continue;
            }
            if ($currency !== null && ! $gateway->supportsCurrency($currency)) {
                continue;
            }
            if ($country !== null && ! $gateway->supportsCountry($country)) {
                continue;
            }
            if ($method !== null && ! $gateway->supportsPaymentMethod($method)) {
                continue;
            }
            $filtered[] = $candidate;
        }

        return $filtered;
    }

    /**
     * Pilih satu gateway terbaik; lempar error spesifik bila tak ada yang mampu.
     *
     * @param array{currency?:string,country?:string,method?:string} $context
     */
    public function route(array $context = []): PaymentGatewayInterface
    {
        $candidates = $this->candidates($context);
        if ($candidates !== []) {
            return $this->registry->resolve($candidates[0]['name']);
        }

        $this->throwMismatch($context);

        throw new PaymentException('Tidak ada gateway tersedia. / No gateway available.');
    }

    /**
     * Charge idempoten + fallback berurutan.
     *
     * Guard anti double-charge: bila $idempotencyKey pernah dipakai dan sukses,
     * hasil lama dikembalikan TANPA memanggil gateway lagi.
     *
     * @param array{currency?:string,country?:string,method?:string,amount?:float,reference_id?:string} $payload
     */
    public function charge(array $payload, ?string $idempotencyKey = null, array $context = []): array
    {
        $context += [
            'currency' => $payload['currency'] ?? null,
            'country' => $payload['country'] ?? null,
            'method' => $payload['method'] ?? $payload['payment_method'] ?? null,
        ];

        if ($idempotencyKey !== null) {
            $cached = $this->recall($idempotencyKey);
            if ($cached !== null) {
                return $cached + ['idempotent_replay' => true];
            }
            // Klaim atomik: kunci "processing" mencegah double-submit konkuren.
            if (! $this->claim($idempotencyKey)) {
                $cached = $this->recall($idempotencyKey);
                if ($cached !== null) {
                    return $cached + ['idempotent_replay' => true];
                }
                throw new PaymentException(
                    'Pembayaran sedang diproses untuk kunci ini. / Payment is already processing for this key.',
                    'Pembayaran sedang diproses.',
                    'Payment is already processing.'
                );
            }
        }

        $candidates = $this->candidates($context);
        if ($candidates === []) {
            if ($idempotencyKey !== null) {
                $this->release($idempotencyKey);
            }
            $this->throwMismatch($context);
        }

        $lastError = null;
        foreach ($candidates as $candidate) {
            $gateway = $this->registry->resolve($candidate['name']);
            try {
                $result = $gateway->charge($payload, $idempotencyKey);
                $result['gateway'] = $gateway->getName();
                if ($idempotencyKey !== null) {
                    $this->store($idempotencyKey, $result);
                }

                return $result;
            } catch (UnsupportedCurrencyException|UnsupportedCountryException|PaymentDeclinedException|PaymentTimeoutException|PaymentException $e) {
                $lastError = $e;
                continue; // fallback aman ke kandidat berikutnya
            }
        }

        if ($idempotencyKey !== null) {
            $this->release($idempotencyKey);
        }

        throw $lastError ?? new PaymentException('Semua gateway gagal. / All gateways failed.');
    }

    /** @return array<int, array{name:string,provider_id:?int}> */
    private function candidatesFromDatabase(): array
    {
        try {
            if (! Schema::hasTable('providers')) {
                return [];
            }
            $rows = \App\Models\Provider::query()
                ->where('type', 'payment')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'api_format', 'config']);
        } catch (\Throwable) {
            return [];
        }

        $candidates = [];
        foreach ($rows as $row) {
            $config = is_array($row->config) ? $row->config : [];
            $name = strtolower((string) ($config['gateway'] ?? $row->api_format ?? ''));
            if ($name === '' || ! $this->registry->has($name)) {
                continue;
            }
            $candidates[] = ['name' => $name, 'provider_id' => (int) $row->id];
        }

        return $candidates;
    }

    /** @return array<int, array{name:string,provider_id:?int}> */
    private function fallbackPool(): array
    {
        $names = $this->memoryGateways !== [] ? $this->memoryGateways : $this->registry->names();

        return array_map(
            static fn (string $name): array => ['name' => strtolower($name), 'provider_id' => null],
            $names
        );
    }

    /** @param array{currency?:mixed,country?:mixed,method?:mixed} $context */
    private function throwMismatch(array $context): never
    {
        $pool = $this->fallbackPool();
        // Bila pool kosong total, itu murni kesalahan konfigurasi.
        if ($pool === [] && $this->candidatesFromDatabase() === []) {
            throw new PaymentException(
                'Tidak ada gateway pembayaran terdaftar. / No payment gateway registered.',
                'Tidak ada gateway pembayaran terdaftar.',
                'No payment gateway registered.'
            );
        }

        $currency = isset($context['currency']) && $context['currency'] !== null ? strtoupper((string) $context['currency']) : null;
        $country = isset($context['country']) && $context['country'] !== null ? strtoupper((string) $context['country']) : null;
        $method = isset($context['method']) && $context['method'] !== null ? PaymentMethod::normalize((string) $context['method']) : null;

        // Cari alasan paling spesifik: currency > country > method.
        $names = array_map(static fn ($c): string => $c['name'], $pool);
        foreach ($names as $name) {
            if (! $this->registry->has($name)) {
                continue;
            }
            $gateway = $this->registry->resolve($name);
            if ($currency !== null && ! $gateway->supportsCurrency($currency)) {
                throw new UnsupportedCurrencyException($currency, $name);
            }
            if ($country !== null && ! $gateway->supportsCountry($country)) {
                throw new UnsupportedCountryException($country, $name);
            }
            if ($method !== null && ! $gateway->supportsPaymentMethod($method)) {
                throw new PaymentException(
                    "Metode {$method} tidak didukung oleh {$name}. / Method {$method} is not supported by {$name}.",
                    "Metode pembayaran {$method} tidak didukung.",
                    "Payment method {$method} is not supported.",
                    ['method' => $method, 'gateway' => $name]
                );
            }
        }

        throw new PaymentException(
            'Tidak ada gateway yang cocok. / No suitable gateway found.',
            'Tidak ada gateway yang cocok.',
            'No suitable gateway found.',
            ['context' => $context]
        );
    }

    private function cacheKey(string $idempotencyKey): string
    {
        return 'payments:idem:'.sha1($idempotencyKey);
    }

    /** @return array|null */
    private function recall(string $idempotencyKey): ?array
    {
        try {
            $value = Cache::get($this->cacheKey($idempotencyKey));
        } catch (\Throwable) {
            return null;
        }

        return is_array($value) && ($value['__done'] ?? false) === true ? ($value['result'] ?? null) : null;
    }

    private function claim(string $idempotencyKey): bool
    {
        try {
            return Cache::add($this->cacheKey($idempotencyKey), ['__done' => false], self::IDEMPOTENCY_TTL_SECONDS);
        } catch (\Throwable) {
            return true;
        }
    }

    private function store(string $idempotencyKey, array $result): void
    {
        try {
            Cache::put($this->cacheKey($idempotencyKey), ['__done' => true, 'result' => $result], self::IDEMPOTENCY_TTL_SECONDS);
        } catch (\Throwable) {
            // Cache opsional; FakePaymentGateway sendiri juga menyimpan per-key.
        }
    }

    private function release(string $idempotencyKey): void
    {
        try {
            Cache::forget($this->cacheKey($idempotencyKey));
        } catch (\Throwable) {
            // abaikan
        }
    }
}
