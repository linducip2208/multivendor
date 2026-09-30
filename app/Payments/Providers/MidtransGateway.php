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
use App\Services\Payment\SnapRedirectAdapter;

/**
 * Adaptor Midtrans ke kontrak App\Payments / Midtrans adapter.
 *
 * Delegasi read-only ke SnapRedirectAdapter (default) atau CoreApiAdapter
 * yang disuntikkan; tanpa keduanya memakai FakePaymentGateway (stub).
 * Mendukung alur redirect (Snap) + direct charge (Core/VA/QRIS/e-wallet).
 */
final class MidtransGateway implements PaymentGatewayInterface
{
    public const CODE = 'midtrans';

    /** @var string[] */
    private const CURRENCIES = ['IDR'];

    /** @var string[] */
    private const COUNTRIES = ['ID'];

    /**
     * Jujur sesuai CoreApiAdapter::getChannels() + Snap (redirect):
     * bank_transfer/echannel, VA bank, gopay/shopeepay=e-wallet, QRIS,
     * kartu kredit + retail (cstore) via Snap. Paylater/direct-debit
     * TIDAK diklaim.
     *
     * @var string[]
     */
    private const METHODS = [
        PaymentMethod::VIRTUAL_ACCOUNT,
        PaymentMethod::BANK_TRANSFER,
        PaymentMethod::E_WALLET,
        PaymentMethod::QRIS,
        PaymentMethod::CREDIT_CARD,
        PaymentMethod::RETAIL_OUTLET,
    ];

    private ?PaymentAdapterInterface $adapter;

    private FakePaymentGateway $stub;

    public function __construct(
        ?Provider $provider = null,
        ?PaymentAdapterInterface $adapter = null,
        ?FakePaymentGateway $stub = null
    ) {
        $this->adapter = $adapter ?? ($provider !== null ? new SnapRedirectAdapter($provider) : null);
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

        $orderId = (string) ($payload['reference_id'] ?? $payload['order_id'] ?? 'mtd-'.bin2hex(random_bytes(4)));
        $result = $this->adapter->createTransaction([
            'order_id' => $orderId,
            'amount' => $payload['amount'] ?? 0,
            'customer' => $payload['customer'] ?? [],
            'items' => $payload['items'] ?? [],
            'channels' => $payload['channels'] ?? [],
            'callbacks' => $payload['callbacks'] ?? [],
        ]);

        if (($result['success'] ?? false) !== true) {
            throw $this->failure($result, 'Midtrans');
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
            'Midtrans tidak mendukung otorisasi dua langkah via adaptor ini. / Midtrans does not support two-step authorization via this adapter.',
            'Midtrans tidak mendukung otorisasi dua langkah.',
            'Midtrans does not support two-step authorization.',
            ['gateway' => self::CODE]
        );
    }

    public function capture(string $referenceId, array $options = []): array
    {
        throw new PaymentException(
            'Midtrans tidak mendukung capture terpisah via adaptor ini. / Midtrans does not support separate capture via this adapter.',
            'Midtrans tidak mendukung capture terpisah.',
            'Midtrans does not support separate capture.',
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
            throw $this->failure($result, 'Midtrans');
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
            throw $this->failure($result, 'Midtrans');
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

        return $this->adapter->verifyCallback(['body' => $payload, 'headers' => $headers]);
    }

    public function handleWebhook(array $payload, array $headers): array
    {
        if ($this->adapter === null) {
            return $this->stub->handleWebhook($payload, $headers);
        }

        if (! $this->verify($payload, $headers)) {
            throw new WebhookVerificationException(
                'Tanda tangan webhook Midtrans tidak valid. / Invalid Midtrans webhook signature.',
                ['gateway' => self::CODE]
            );
        }

        $status = strtolower((string) ($payload['transaction_status'] ?? $payload['status'] ?? ''));
        $resolved = match (true) {
            $status === 'settlement' => 'paid',
            $status === 'capture' && ($payload['fraud_status'] ?? 'accept') === 'accept' => 'paid',
            in_array($status, ['deny', 'cancel'], true) => 'failed',
            $status === 'expire' => 'expired',
            in_array($status, ['refund', 'partial_refund'], true) => 'refunded',
            default => 'pending',
        };

        $amount = $payload['gross_amount'] ?? $payload['amount'] ?? null;

        return [
            'event_id' => (string) ($payload['transaction_id'] ?? $payload['order_id'] ?? ''),
            'reference_id' => (string) ($payload['order_id'] ?? ''),
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
            throw $this->failure($result, 'Midtrans');
        }

        $data = is_array($result['data'] ?? null) ? $result['data'] : [];

        return [
            'reference_id' => $referenceId,
            'status' => strtolower((string) ($data['transaction_status'] ?? $data['status'] ?? 'pending')),
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
}
