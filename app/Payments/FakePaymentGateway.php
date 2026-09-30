<?php

declare(strict_types=1);

namespace App\Payments;

/**
 * Fake provider untuk test/lokal / Fake provider for tests & local dev.
 *
 * Mendukung semua operasi + sign/verify webhook (HMAC-SHA256) + deteksi replay
 * + simulasi timeout. TIDAK melakukan panggilan jaringan apa pun.
 */
final class FakePaymentGateway implements PaymentGatewayInterface
{
    private string $name;
    private string $secret;
    /** @var string[] */
    private array $currencies;
    /** @var string[] */
    private array $countries;
    /** @var string[] */
    private array $methods;
    private bool $failCharge;
    private bool $failNextCharge = false;
    private ?float $timeoutAboveAmount;
    private int $timestampTolerance;

    public int $chargeCount = 0;
    public int $refundCount = 0;
    /** @var array<string, array> idempotencyKey => result */
    public array $chargedKeys = [];
    /** @var string[] event ids yang pernah diproses (replay guard) */
    private static array $seenEvents = [];

    /**
     * @param string[]|null $currencies
     * @param string[]|null $countries
     * @param string[]|null $methods
     */
    public function __construct(
        string $name = 'fake',
        string $secret = 'test-secret',
        ?array $currencies = null,
        ?array $countries = null,
        ?array $methods = null,
        bool $failCharge = false,
        ?float $timeoutAboveAmount = null,
        int $timestampTolerance = 300
    ) {
        $this->name = $name;
        $this->secret = $secret;
        $this->currencies = array_map('strtoupper', $currencies ?? ['IDR', 'USD']);
        $this->countries = array_map('strtoupper', $countries ?? ['ID', 'US']);
        $this->methods = array_map([PaymentMethod::class, 'normalize'], $methods ?? PaymentMethod::all());
        $this->failCharge = $failCharge;
        $this->timeoutAboveAmount = $timeoutAboveAmount;
        $this->timestampTolerance = $timestampTolerance;
    }

    public static function resetReplayGuard(): void
    {
        self::$seenEvents = [];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    /** Paksa charge berikutnya gagal (untuk menguji fallback router). */
    public function failNextCharge(bool $fail = true): self
    {
        $this->failNextCharge = $fail;

        return $this;
    }

    public function initialize(array $payload): array
    {
        $reference = (string) ($payload['reference_id'] ?? 'fake-init-'.bin2hex(random_bytes(4)));

        return [
            'reference_id' => $reference,
            'status' => 'pending',
            'redirect_url' => 'https://fake-gateway.test/pay/'.$reference,
            'raw' => ['gateway' => $this->name, 'payload' => $payload],
        ];
    }

    public function authorize(array $payload): array
    {
        $reference = (string) ($payload['reference_id'] ?? 'fake-auth-'.bin2hex(random_bytes(4)));

        return [
            'reference_id' => $reference,
            'status' => 'authorized',
            'raw' => ['gateway' => $this->name],
        ];
    }

    public function capture(string $referenceId, array $options = []): array
    {
        return [
            'reference_id' => $referenceId,
            'status' => 'captured',
            'raw' => ['gateway' => $this->name, 'options' => $options],
        ];
    }

    public function charge(array $payload, ?string $idempotencyKey = null): array
    {
        if ($idempotencyKey !== null && isset($this->chargedKeys[$idempotencyKey])) {
            return $this->chargedKeys[$idempotencyKey];
        }

        if ($this->failNextCharge || $this->failCharge) {
            $this->failNextCharge = false;
            throw new PaymentDeclinedException(
                (string) ($payload['reference_id'] ?? ''),
                ['gateway' => $this->name]
            );
        }

        $amount = (float) ($payload['amount'] ?? 0);
        if ($this->timeoutAboveAmount !== null && $amount > $this->timeoutAboveAmount) {
            throw new PaymentTimeoutException(
                (string) ($payload['reference_id'] ?? ''),
                ['gateway' => $this->name, 'amount' => $amount]
            );
        }

        $this->chargeCount++;
        $reference = (string) ($payload['reference_id'] ?? 'fake-chg-'.bin2hex(random_bytes(4)));
        $result = [
            'reference_id' => $reference,
            'status' => 'paid',
            'amount' => $amount,
            'currency' => strtoupper((string) ($payload['currency'] ?? 'IDR')),
            'raw' => ['gateway' => $this->name],
        ];

        if ($idempotencyKey !== null) {
            $this->chargedKeys[$idempotencyKey] = $result;
        }

        return $result;
    }

    public function refund(string $referenceId, float $amount, array $options = []): array
    {
        $this->refundCount++;

        return [
            'reference_id' => $referenceId,
            'status' => 'refunded',
            'amount' => $amount,
            'raw' => ['gateway' => $this->name, 'options' => $options],
        ];
    }

    public function void(string $referenceId, array $options = []): array
    {
        return [
            'reference_id' => $referenceId,
            'status' => 'voided',
            'raw' => ['gateway' => $this->name, 'options' => $options],
        ];
    }

    /**
     * Buat webhook bertanda tangan untuk pengujian.
     *
     * @return array{payload:array,headers:array}
     */
    public function signWebhook(array $event, ?string $eventId = null, ?int $timestamp = null): array
    {
        $payload = $event + [
            'gateway' => $this->name,
            'event_id' => $eventId ?? 'evt-'.bin2hex(random_bytes(8)),
        ];
        $ts = $timestamp ?? time();
        ksort($payload);
        $signature = hash_hmac('sha256', json_encode($payload).'.'.$ts, $this->secret);

        return [
            'payload' => $payload,
            'headers' => [
                'X-Signature' => $signature,
                'X-Timestamp' => (string) $ts,
                'X-Event-Id' => $payload['event_id'],
            ],
        ];
    }

    public function verify(array $payload, array $headers): bool
    {
        $signature = $headers['X-Signature'] ?? $headers['x-signature'] ?? null;
        $timestamp = $headers['X-Timestamp'] ?? $headers['x-timestamp'] ?? null;
        if (! is_string($signature) || $timestamp === null) {
            return false;
        }
        if (abs(time() - (int) $timestamp) > $this->timestampTolerance) {
            return false;
        }
        $sorted = $payload;
        ksort($sorted);
        $expected = hash_hmac('sha256', json_encode($sorted).'.'.$timestamp, $this->secret);

        return hash_equals($expected, $signature);
    }

    public function handleWebhook(array $payload, array $headers): array
    {
        if (! $this->verify($payload, $headers)) {
            throw new WebhookVerificationException('tanda tangan tidak valid / invalid signature', [
                'gateway' => $this->name,
            ]);
        }

        $eventId = (string) ($payload['event_id'] ?? $headers['X-Event-Id'] ?? $headers['x-event-id'] ?? '');
        if ($eventId !== '' && isset(self::$seenEvents[$eventId])) {
            return [
                'event_id' => $eventId,
                'reference_id' => (string) ($payload['reference_id'] ?? ''),
                'status' => (string) ($payload['status'] ?? 'paid'),
                'amount' => isset($payload['amount']) ? (float) $payload['amount'] : null,
                'duplicate' => true,
                'raw' => $payload,
            ];
        }
        if ($eventId !== '') {
            self::$seenEvents[$eventId] = true;
        }

        return [
            'event_id' => $eventId,
            'reference_id' => (string) ($payload['reference_id'] ?? ''),
            'status' => (string) ($payload['status'] ?? 'paid'),
            'amount' => isset($payload['amount']) ? (float) $payload['amount'] : null,
            'duplicate' => false,
            'raw' => $payload,
        ];
    }

    public function getStatus(string $referenceId): array
    {
        return [
            'reference_id' => $referenceId,
            'status' => 'paid',
            'raw' => ['gateway' => $this->name],
        ];
    }

    public function supportsCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), $this->currencies, true);
    }

    public function supportsCountry(string $country): bool
    {
        return in_array(strtoupper($country), $this->countries, true);
    }

    public function supportsPaymentMethod(string $method): bool
    {
        return in_array(PaymentMethod::normalize($method), $this->methods, true);
    }
}
