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
use App\Services\Payment\GenericApiAdapter;
use App\Services\Payment\PaymentAdapterInterface;

/**
 * Adaptor iPaymu ke kontrak App\Payments / iPaymu adapter.
 *
 * Delegasi read-only ke GenericApiAdapter (format `ipaymu-api`) bila
 * Provider tersedia; tanpa Provider memakai FakePaymentGateway (stub).
 *
 * Kejujuran kapabilitas: adaptor existing TIDAK mendukung verifikasi
 * callback, status transaksi, refund, maupun cancel — jalur live untuk
 * operasi tersebut melempar PaymentException/WebhookVerificationException
 * dwibahasa; hanya initialize/charge yang didelegasikan ke API.
 */
final class IpaymuGateway implements PaymentGatewayInterface
{
    public const CODE = 'ipaymu';

    /** @var string[] */
    private const CURRENCIES = ['IDR'];

    /** @var string[] */
    private const COUNTRIES = ['ID'];

    /**
     * Kanal publik iPaymu (VA, transfer bank, e-wallet, QRIS, gerai retail,
     * kartu kredit). Verifikasi/refund/status live tetap TIDAK didukung
     * oleh GenericApiAdapter — lihat docblock kelas.
     *
     * @var string[]
     */
    private const METHODS = [
        PaymentMethod::VIRTUAL_ACCOUNT,
        PaymentMethod::BANK_TRANSFER,
        PaymentMethod::E_WALLET,
        PaymentMethod::QRIS,
        PaymentMethod::RETAIL_OUTLET,
        PaymentMethod::CREDIT_CARD,
    ];

    private ?PaymentAdapterInterface $adapter;

    private FakePaymentGateway $stub;

    public function __construct(
        ?Provider $provider = null,
        ?PaymentAdapterInterface $adapter = null,
        ?FakePaymentGateway $stub = null
    ) {
        $this->adapter = $adapter ?? ($provider !== null ? new GenericApiAdapter($provider) : null);
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

        $orderId = (string) ($payload['reference_id'] ?? $payload['order_id'] ?? 'ipm-'.bin2hex(random_bytes(4)));
        $result = $this->adapter->createTransaction([
            'order_id' => $orderId,
            'amount' => $payload['amount'] ?? 0,
            'customer' => $payload['customer'] ?? [],
            'items' => $payload['items'] ?? [],
            'callback_url' => $payload['callback_url'] ?? '',
        ]);

        if (($result['success'] ?? false) !== true) {
            throw $this->failure($result, 'iPaymu');
        }

        $out = [
            'reference_id' => $orderId,
            'status' => 'pending',
            'raw' => $result['raw'] ?? $result,
        ];
        foreach (['redirect_url', 'payment_url', 'va_number'] as $key) {
            if (isset($result[$key])) {
                $out[$key] = $result[$key];
            }
        }

        return $out;
    }

    public function authorize(array $payload): array
    {
        throw new PaymentException(
            'iPaymu tidak mendukung otorisasi dua langkah. / iPaymu does not support two-step authorization.',
            'iPaymu tidak mendukung otorisasi dua langkah.',
            'iPaymu does not support two-step authorization.',
            ['gateway' => self::CODE]
        );
    }

    public function capture(string $referenceId, array $options = []): array
    {
        throw new PaymentException(
            'iPaymu tidak mendukung capture terpisah. / iPaymu does not support separate capture.',
            'iPaymu tidak mendukung capture terpisah.',
            'iPaymu does not support separate capture.',
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

        throw new PaymentException(
            'Refund iPaymu belum didukung oleh adaptor existing. / iPaymu refund is not supported by the existing adapter.',
            'Refund iPaymu belum didukung.',
            'iPaymu refund is not supported.',
            ['gateway' => self::CODE, 'reference_id' => $referenceId]
        );
    }

    public function void(string $referenceId, array $options = []): array
    {
        if ($this->adapter === null) {
            return $this->stub->void($referenceId, $options);
        }

        throw new PaymentException(
            'Pembatalan iPaymu belum didukung oleh adaptor existing. / iPaymu void is not supported by the existing adapter.',
            'Pembatalan iPaymu belum didukung.',
            'iPaymu void is not supported.',
            ['gateway' => self::CODE, 'reference_id' => $referenceId]
        );
    }

    public function verify(array $payload, array $headers): bool
    {
        if ($this->adapter === null) {
            return $this->stub->verify($payload, $headers);
        }

        // GenericApiAdapter::verifyCallback() selalu false — diteruskan jujur.
        return $this->adapter->verifyCallback(['body' => $payload, 'headers' => $headers]);
    }

    public function handleWebhook(array $payload, array $headers): array
    {
        if ($this->adapter === null) {
            return $this->stub->handleWebhook($payload, $headers);
        }

        throw new WebhookVerificationException(
            'Verifikasi callback iPaymu belum didukung oleh adaptor existing. / iPaymu callback verification is not supported by the existing adapter.',
            ['gateway' => self::CODE]
        );
    }

    public function getStatus(string $referenceId): array
    {
        if ($this->adapter === null) {
            return $this->stub->getStatus($referenceId);
        }

        throw new PaymentException(
            'Pemeriksaan status iPaymu belum didukung oleh adaptor existing. / iPaymu status check is not supported by the existing adapter.',
            'Pemeriksaan status iPaymu belum didukung.',
            'iPaymu status check is not supported.',
            ['gateway' => self::CODE, 'reference_id' => $referenceId]
        );
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
