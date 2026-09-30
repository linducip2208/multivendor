<?php

declare(strict_types=1);

namespace App\Payments\Providers;

use App\Models\Provider;
use App\Payments\FakePaymentGateway;
use App\Payments\PaymentException;
use App\Payments\PaymentGatewayInterface;
use App\Payments\PaymentMethod;
use App\Payments\UnsupportedCountryException;
use App\Payments\UnsupportedCurrencyException;
use App\Payments\WebhookVerificationException;
use App\Services\Payment\PaymentAdapterInterface;
use App\Services\Payment\TripayClosedAdapter;

/**
 * Adaptor Tripay (closed payment) ke kontrak App\Payments / Tripay adapter.
 *
 * Delegasi read-only ke App\Services\Payment\TripayClosedAdapter bila
 * Provider tersedia; tanpa Provider memakai FakePaymentGateway (stub).
 * Alur closed (VA/QRIS/e-wallet) satu langkah: authorize()/capture()
 * TIDAK didukung.
 */
final class TripayGateway implements PaymentGatewayInterface
{
    public const CODE = 'tripay';

    /** @var string[] */
    private const CURRENCIES = ['IDR'];

    /** @var string[] */
    private const COUNTRIES = ['ID'];

    /**
     * Jujur sesuai TripayClosedAdapter::getChannels():
     * BCAVA/BNIVA/BRIVA/MANDIRIVA=VA, QRIS, GOPAY/OVO/DANA/SHOPEEPAY=e-wallet.
     * Retail/CC/bank-transfer generik TIDAK diklaim.
     *
     * @var string[]
     */
    private const METHODS = [
        PaymentMethod::VIRTUAL_ACCOUNT,
        PaymentMethod::E_WALLET,
        PaymentMethod::QRIS,
    ];

    private ?PaymentAdapterInterface $adapter;

    private FakePaymentGateway $stub;

    public function __construct(
        ?Provider $provider = null,
        ?PaymentAdapterInterface $adapter = null,
        ?FakePaymentGateway $stub = null
    ) {
        $this->adapter = $adapter ?? ($provider !== null ? new TripayClosedAdapter($provider) : null);
        $this->stub = $stub ?? new FakePaymentGateway(
            name: self::CODE,
            currencies: self::CURRENCIES,
            countries: self::COUNTRIES,
            methods: self::METHODS
        );
    }

    public function getName(): string
    {
        return self::CODE;
    }

    public function initialize(array $payload): array
    {
        $this->guardContext($payload);

        if ($this->adapter === null) {
            return $this->stub->initialize($payload);
        }

        $orderId = (string) ($payload['reference_id'] ?? $payload['order_id'] ?? 'trp-'.bin2hex(random_bytes(4)));
        $result = $this->adapter->createTransaction([
            'order_id' => $orderId,
            'amount' => $payload['amount'] ?? 0,
            'channel' => $payload['channel'] ?? $payload['payment_method'] ?? 'BRIVA',
            'customer' => $payload['customer'] ?? [],
            'items' => $payload['items'] ?? [],
            'success_url' => $payload['success_url'] ?? '',
            'callback_url' => $payload['callback_url'] ?? '',
        ]);

        if (($result['success'] ?? false) !== true) {
            throw $this->failure($result, 'Tripay');
        }

        $out = [
            'reference_id' => $orderId,
            'status' => 'pending',
            'raw' => $result['raw'] ?? $result,
        ];
        if (isset($result['redirect_url'])) {
            $out['redirect_url'] = $result['redirect_url'];
        }

        return $out;
    }

    public function authorize(array $payload): array
    {
        throw new PaymentException(
            'Tripay tidak mendukung otorisasi dua langkah. / Tripay does not support two-step authorization.',
            'Tripay tidak mendukung otorisasi dua langkah.',
            'Tripay does not support two-step authorization.',
            ['gateway' => self::CODE]
        );
    }

    public function capture(string $referenceId, array $options = []): array
    {
        throw new PaymentException(
            'Tripay tidak mendukung capture terpisah. / Tripay does not support separate capture.',
            'Tripay tidak mendukung capture terpisah.',
            'Tripay does not support separate capture.',
            ['gateway' => self::CODE, 'reference_id' => $referenceId]
        );
    }

    public function charge(array $payload, ?string $idempotencyKey = null): array
    {
        $this->guardContext($payload);

        if ($this->adapter === null) {
            return $this->stub->charge($payload, $idempotencyKey);
        }

        $init = $this->initialize($payload);

        return $init + [
            'amount' => (float) ($payload['amount'] ?? 0),
            'currency' => strtoupper((string) ($payload['currency'] ?? 'IDR')),
        ];
    }

    public function refund(string $referenceId, float $amount, array $options = []): array
    {
        if ($this->adapter === null) {
            return $this->stub->refund($referenceId, $amount, $options);
        }

        $result = $this->adapter->refund($referenceId, $amount, $options);

        if (($result['success'] ?? false) !== true) {
            throw $this->failure($result, 'Tripay');
        }

        return [
            'reference_id' => $referenceId,
            'status' => strtolower((string) ($result['status'] ?? 'refunded')),
            'amount' => $amount,
            'raw' => $result['raw'] ?? $result,
        ];
    }

    public function void(string $referenceId, array $options = []): array
    {
        if ($this->adapter === null) {
            return $this->stub->void($referenceId, $options);
        }

        $result = $this->adapter->cancel($referenceId);

        if (($result['success'] ?? false) !== true) {
            throw $this->failure($result, 'Tripay');
        }

        return [
            'reference_id' => $referenceId,
            'status' => strtolower((string) ($result['status'] ?? 'voided')),
            'raw' => $result['raw'] ?? $result,
        ];
    }

    public function verify(array $payload, array $headers): bool
    {
        if ($this->adapter === null) {
            return $this->stub->verify($payload, $headers);
        }

        return $this->adapter->verifyCallback([
            'body' => $payload,
            'headers' => self::lowerHeaders($headers),
            'raw' => json_encode($payload, JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function handleWebhook(array $payload, array $headers): array
    {
        if ($this->adapter === null) {
            return $this->stub->handleWebhook($payload, $headers);
        }

        if (! $this->verify($payload, $headers)) {
            throw new WebhookVerificationException(
                'Tanda tangan webhook Tripay tidak valid. / Invalid Tripay webhook signature.',
                ['gateway' => self::CODE]
            );
        }

        $status = strtolower((string) ($payload['status'] ?? ''));
        $resolved = match ($status) {
            'paid', 'settlement' => 'paid',
            'expired' => 'expired',
            'failed', 'unpaid' => 'failed',
            'refund', 'refunded' => 'refunded',
            default => 'pending',
        };

        $amount = $payload['total_fee'] ?? $payload['amount'] ?? null;

        return [
            'event_id' => (string) ($payload['reference'] ?? $payload['merchant_ref'] ?? ''),
            'reference_id' => (string) ($payload['merchant_ref'] ?? $payload['reference'] ?? ''),
            'status' => $resolved,
            'amount' => is_numeric($amount) ? (float) $amount : null,
            'raw' => $payload,
        ];
    }

    public function getStatus(string $referenceId): array
    {
        if ($this->adapter === null) {
            return $this->stub->getStatus($referenceId);
        }

        $result = $this->adapter->getTransactionStatus($referenceId);

        if (($result['success'] ?? false) !== true) {
            throw $this->failure($result, 'Tripay');
        }

        $data = is_array($result['data'] ?? null) ? $result['data'] : [];

        return [
            'reference_id' => $referenceId,
            'status' => strtolower((string) ($data['status'] ?? 'pending')),
            'raw' => $data !== [] ? $data : $result,
        ];
    }

    public function supportsCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), self::CURRENCIES, true);
    }

    public function supportsCountry(string $country): bool
    {
        return in_array(strtoupper($country), self::COUNTRIES, true);
    }

    public function supportsPaymentMethod(string $method): bool
    {
        return in_array(PaymentMethod::normalize($method), self::METHODS, true);
    }

    /** @param array<string,mixed> $payload */
    private function guardContext(array $payload): void
    {
        if (isset($payload['currency']) && ! $this->supportsCurrency((string) $payload['currency'])) {
            throw new UnsupportedCurrencyException((string) $payload['currency'], self::CODE);
        }
        if (isset($payload['country']) && ! $this->supportsCountry((string) $payload['country'])) {
            throw new UnsupportedCountryException((string) $payload['country'], self::CODE);
        }
        $method = $payload['method'] ?? $payload['payment_method'] ?? null;
        if ($method !== null && ! $this->supportsPaymentMethod((string) $method)) {
            throw new PaymentException(
                'Metode '.PaymentMethod::normalize((string) $method).' tidak didukung oleh '.self::CODE
                .'. / Method '.PaymentMethod::normalize((string) $method).' is not supported by '.self::CODE.'.',
                'Metode pembayaran '.PaymentMethod::normalize((string) $method).' tidak didukung.',
                'Payment method '.PaymentMethod::normalize((string) $method).' is not supported.',
                ['method' => PaymentMethod::normalize((string) $method), 'gateway' => self::CODE]
            );
        }
    }

    /** @param array<string,mixed> $result */
    private function failure(array $result, string $label): PaymentException
    {
        $code = (string) ($result['code'] ?? 'gateway_rejected');
        $messageId = (string) ($result['message'] ?? $label.' menolak permintaan.');
        $messageEn = $code === 'unreachable'
            ? $label.' is unreachable.'
            : $label.' rejected the request.';

        return new PaymentException(
            $messageId.' / '.$messageEn,
            $messageId,
            $messageEn,
            ['gateway' => self::CODE, 'code' => $code]
        );
    }

    /** @param array<string,mixed> $headers @return array<string,mixed> */
    private static function lowerHeaders(array $headers): array
    {
        $lowered = [];
        foreach ($headers as $key => $value) {
            $lowered[strtolower((string) $key)] = $value;
        }

        return $lowered;
    }
}
