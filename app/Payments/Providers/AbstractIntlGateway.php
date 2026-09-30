<?php

declare(strict_types=1);

namespace App\Payments\Providers;

use App\Payments\PaymentDeclinedException;
use App\Payments\PaymentException;
use App\Payments\PaymentGatewayInterface;
use App\Payments\PaymentMethod;
use App\Payments\PaymentTimeoutException;
use App\Payments\UnsupportedCountryException;
use App\Payments\UnsupportedCurrencyException;
use App\Payments\WebhookVerificationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Basis HTTP-native untuk gateway internasional / Shared HTTP-native base.
 *
 * - TANPA SDK: hanya Laravel HTTP client (Illuminate\Support\Facades\Http).
 * - Kredensial dibaca dari config('services.<gateway>') + env().
 * - ConnectionException / HTTP 408,429,502,503,504 → PaymentTimeoutException.
 * - Pesan error selalu BI/EN lewat exception existing di App\Payments.
 */
abstract class AbstractIntlGateway implements PaymentGatewayInterface
{
    /** Mata uang tanpa desimal (minor = major). */
    private const ZERO_DECIMAL = [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW',
        'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    protected int $timeoutSeconds = 15;

    /** True bila kredensial lengkap (gateway aktif). */
    abstract public function isEnabled(): bool;

    /** @return PendingRequest */
    protected function http(): PendingRequest
    {
        return Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'multivendor-payments/1.0']);
    }

    /**
     * Jalankan callable HTTP; ubah gangguan jaringan menjadi timeout.
     *
     * @template T
     *
     * @param callable(): T $call
     * @return T
     */
    protected function send(callable $call, string $referenceId)
    {
        try {
            return $call();
        } catch (ConnectionException $e) {
            throw new PaymentTimeoutException(
                $referenceId,
                ['gateway' => $this->getName()],
                $e
            );
        }
    }

    /** Lempar timeout / kegagalan generik untuk respons HTTP non-2xx. */
    protected function failForStatus(Response $response, string $referenceId, string $label): never
    {
        $status = $response->status();

        if (in_array($status, [408, 425, 429, 502, 503, 504], true)) {
            throw new PaymentTimeoutException(
                $referenceId,
                ['gateway' => $this->getName(), 'http_status' => $status]
            );
        }

        $snippet = substr(trim((string) $response->body()), 0, 300);

        throw new PaymentException(
            "{$label} menolak permintaan (HTTP {$status}). / {$label} rejected the request (HTTP {$status}).",
            "{$label} menolak permintaan.",
            "{$label} rejected the request.",
            ['gateway' => $this->getName(), 'http_status' => $status, 'body' => $snippet]
        );
    }

    /** Lempar PaymentDeclinedException BI/EN untuk penolakan issuer/gateway. */
    protected function declined(string $referenceId, array $context = []): never
    {
        throw new PaymentDeclinedException(
            $referenceId,
            ['gateway' => $this->getName()] + $context
        );
    }

    /** Pastikan gateway aktif; bila kredensial kosong → PaymentException BI/EN. */
    protected function ensureEnabled(string $label): void
    {
        if (! $this->isEnabled()) {
            throw new PaymentException(
                "Kredensial {$label} belum dikonfigurasi; gateway nonaktif. / {$label} credentials are not configured; gateway is disabled.",
                "Kredensial {$label} belum dikonfigurasi.",
                "{$label} credentials are not configured.",
                ['gateway' => $this->getName()]
            );
        }
    }

    /** Guard currency/country/method jujur sebelum memanggil API. */
    protected function guardContext(array $payload): void
    {
        if (isset($payload['currency']) && ! $this->supportsCurrency((string) $payload['currency'])) {
            throw new UnsupportedCurrencyException((string) $payload['currency'], $this->getName());
        }
        if (isset($payload['country']) && ! $this->supportsCountry((string) $payload['country'])) {
            throw new UnsupportedCountryException((string) $payload['country'], $this->getName());
        }
        $method = $payload['method'] ?? $payload['payment_method'] ?? null;
        if ($method !== null && ! $this->supportsPaymentMethod((string) $method)) {
            $normalized = PaymentMethod::normalize((string) $method);

            throw new PaymentException(
                "Metode {$normalized} tidak didukung oleh {$this->getName()}. / Method {$normalized} is not supported by {$this->getName()}.",
                "Metode pembayaran {$normalized} tidak didukung.",
                "Payment method {$normalized} is not supported.",
                ['method' => $normalized, 'gateway' => $this->getName()]
            );
        }
    }

    protected function webhookFailure(string $reason): never
    {
        throw new WebhookVerificationException($reason, ['gateway' => $this->getName()]);
    }

    protected static function toMinor(float $amount, string $currency): int
    {
        if (in_array(strtoupper($currency), self::ZERO_DECIMAL, true)) {
            return (int) round($amount);
        }

        return (int) round($amount * 100);
    }

    protected static function fromMinor(int $minor, string $currency): float
    {
        if (in_array(strtoupper($currency), self::ZERO_DECIMAL, true)) {
            return (float) $minor;
        }

        return $minor / 100;
    }

    /** @param array<string,mixed> $headers */
    protected static function header(array $headers, string $name): ?string
    {
        $want = strtolower($name);
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === $want) {
                return is_string($value) ? $value : (string) $value;
            }
        }

        return null;
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    protected static function sorted(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = self::sorted($value);
            }
        }
        ksort($payload);

        return $payload;
    }

    /** JSON kanonis untuk penandatanganan webhook. */
    protected static function canonicalJson(array $payload): string
    {
        return (string) json_encode(self::sorted($payload), JSON_UNESCAPED_SLASHES);
    }
}
