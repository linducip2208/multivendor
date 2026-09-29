<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Provider;

class PaymentGatewayService
{
    private const ADAPTERS = [
        'midtrans-snap' => SnapRedirectAdapter::class,
        'midtrans-core' => CoreApiAdapter::class,
        'xendit-invoice' => XenditInvoiceAdapter::class,
        'tripay-closed' => TripayClosedAdapter::class,
        'duitku-redirect' => GenericRedirectAdapter::class,
        'oyindonesia-api' => GenericApiAdapter::class,
        'ipaymu-api' => GenericApiAdapter::class,
        'faspay-api' => GenericApiAdapter::class,
        'doku-api' => GenericApiAdapter::class,
        'esiapay-api' => GenericApiAdapter::class,
    ];

    private const REDIRECT_FORMATS = [
        'midtrans-snap',
        'xendit-invoice',
        'tripay-closed',
        'duitku-redirect',
    ];

    private const API_FORMATS = [
        'midtrans-core',
        'oyindonesia-api',
        'ipaymu-api',
        'faspay-api',
        'doku-api',
        'esiapay-api',
    ];

    private const VERIFIED_CALLBACK_FORMATS = [
        'midtrans-snap',
        'midtrans-core',
        'xendit-invoice',
        'tripay-closed',
    ];

    private const REFUNDABLE_FORMATS = [
        'midtrans-snap',
        'midtrans-core',
        'xendit-invoice',
        'tripay-closed',
    ];

    private const RECONCILABLE_FORMATS = [
        'midtrans-snap',
        'midtrans-core',
        'xendit-invoice',
        'tripay-closed',
    ];

    private const AMOUNT_KEYS = [
        'gross_amount',
        'amount_paid',
        'paid_amount',
        'amount_received',
        'amount',
        'total_amount',
    ];

    private array $adapters = [];

    public function getAdapter(Provider $provider): ?PaymentAdapterInterface
    {
        $cacheKey = $provider->id;

        if (isset($this->adapters[$cacheKey])) {
            return $this->adapters[$cacheKey];
        }

        $class = self::ADAPTERS[(string) $provider->api_format] ?? null;

        if ($class === null) {
            return null;
        }

        return $this->adapters[$cacheKey] = new $class($provider);
    }

    public function createPayment(Provider $provider, array $payload): array
    {
        $adapter = $this->getAdapter($provider);

        if (! $adapter) {
            return [
                'success' => false,
                'code' => 'unsupported_format',
                'message' => 'Format gateway pembayaran ini tidak didukung.',
            ];
        }

        return $adapter->createTransaction($payload);
    }

    public function getActiveGateways(): array
    {
        return Provider::ofType('payment')->active()->orderBy('sort_order')->get()->all();
    }

    public function getChannelsForGateway(Provider $provider): array
    {
        return $this->getAdapter($provider)?->getChannels() ?? [];
    }

    public function verifyCallback(Provider $provider, array $data): bool
    {
        if (! self::supportsCallbackVerification((string) $provider->api_format)) {
            return false;
        }

        return $this->getAdapter($provider)?->verifyCallback($data) ?? false;
    }

    public function refund(Provider $provider, string $gatewayPaymentId, float $amount, array $options = []): array
    {
        $adapter = $this->getAdapter($provider);

        if (! $adapter) {
            return SnapRedirectAdapter::unsupported('Format gateway pembayaran ini tidak didukung.');
        }

        return $adapter->refund($gatewayPaymentId, $amount, $options);
    }

    public function cancel(Provider $provider, string $gatewayPaymentId): array
    {
        $adapter = $this->getAdapter($provider);

        if (! $adapter) {
            return SnapRedirectAdapter::unsupported('Format gateway pembayaran ini tidak didukung.');
        }

        return $adapter->cancel($gatewayPaymentId);
    }

    public function reconcile(Provider $provider, string $gatewayPaymentId): array
    {
        if (! self::supportsReconciliation((string) $provider->api_format)) {
            return ['success' => false, 'code' => 'unsupported', 'message' => 'Rekonsiliasi tidak didukung.'];
        }

        $adapter = $this->getAdapter($provider);

        if (! $adapter) {
            return ['success' => false, 'code' => 'unsupported', 'message' => 'Format gateway tidak didukung.'];
        }

        return $adapter->getTransactionStatus($gatewayPaymentId);
    }

    public static function supportedFormats(): array
    {
        return array_keys(self::ADAPTERS);
    }

    public static function supportedRedirectFormats(): array
    {
        return array_values(array_intersect(self::supportedFormats(), self::REDIRECT_FORMATS));
    }

    public static function supportedApiFormats(): array
    {
        return array_values(array_intersect(self::supportedFormats(), self::API_FORMATS));
    }

    public static function isSupportedFormat(?string $format): bool
    {
        return is_string($format) && isset(self::ADAPTERS[$format]);
    }

    public static function supportsCallbackVerification(?string $format): bool
    {
        return is_string($format) && in_array($format, self::VERIFIED_CALLBACK_FORMATS, true);
    }

    public static function supportsRefunds(?string $format): bool
    {
        return is_string($format) && in_array($format, self::REFUNDABLE_FORMATS, true);
    }

    public static function supportsReconciliation(?string $format): bool
    {
        return is_string($format) && in_array($format, self::RECONCILABLE_FORMATS, true);
    }

    public static function amountTolerance(): float
    {
        $raw = \App\Models\SystemSetting::get('payment_amount_tolerance', '0');
        $tolerance = is_numeric($raw) ? (float) $raw : 0.0;

        return max(0.0, $tolerance);
    }

    public function reportedAmount(Provider $provider, array $data): ?float
    {
        $body = $data['body'] ?? $data;

        if (! is_array($body)) {
            return null;
        }

        foreach (self::AMOUNT_KEYS as $key) {
            $value = $body[$key] ?? null;

            if ($value !== null && is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    public function normalizeCallback(Provider $provider, array $data): array
    {
        $body = $data['body'] ?? $data;
        $body = is_array($body) ? $body : [];
        $status = strtolower((string) ($body['transaction_status'] ?? $body['status'] ?? ''));

        if (in_array($provider->api_format, ['midtrans-snap', 'midtrans-core'], true)) {
            $resolved = match (true) {
                $status === 'settlement' => 'paid',
                $status === 'capture' && ($body['fraud_status'] ?? 'accept') === 'accept' => 'paid',
                in_array($status, ['deny', 'cancel'], true) => 'failed',
                $status === 'expire' => 'expired',
                in_array($status, ['refund', 'partial_refund'], true) => 'refunded',
                default => 'pending',
            };

            return [
                'external_id' => $body['order_id'] ?? null,
                'gateway_transaction_id' => $body['transaction_id'] ?? null,
                'status' => $resolved,
            ];
        }

        if ($provider->api_format === 'xendit-invoice') {
            $resolved = match ($status) {
                'paid', 'settled', 'success' => 'paid',
                'expired' => 'expired',
                'failed' => 'failed',
                'refunded' => 'refunded',
                default => 'pending',
            };

            return [
                'external_id' => $body['external_id'] ?? null,
                'gateway_transaction_id' => $body['id'] ?? $body['invoice_id'] ?? null,
                'status' => $resolved,
            ];
        }

        if ($provider->api_format === 'tripay-closed') {
            $resolved = match ($status) {
                'paid', 'settlement' => 'paid',
                'expired' => 'expired',
                'failed', 'unpaid' => 'failed',
                'refund', 'refunded' => 'refunded',
                default => 'pending',
            };

            return [
                'external_id' => $body['merchant_ref'] ?? $body['reference'] ?? null,
                'gateway_transaction_id' => $body['reference'] ?? null,
                'status' => $resolved,
            ];
        }

        return ['external_id' => null, 'gateway_transaction_id' => null, 'status' => null];    }
}
