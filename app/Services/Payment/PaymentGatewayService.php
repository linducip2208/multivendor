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

    /**
     * Rekonsiliasi terjadwal/manual untuk satu grup: probe gateway, catat
     * last_reconciled_at + attempts + note pada kolom existing, kembalikan
     * status selisih tanpa mengubah order (dashboard yang memutuskan).
     *
     * @return array{success: bool, status: string, matched: bool, message: string}
     */
    public function reconcileGroup(\App\Models\PaymentGroup $group): array
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($group): array {
            $locked = \App\Models\PaymentGroup::whereKey($group->getKey())->lockForUpdate()->firstOrFail();
            $provider = $locked->provider;
            $reference = (string) ($locked->gateway_reference ?: $locked->payment_number);

            if (! $provider || ! self::supportsReconciliation((string) $provider->api_format)) {
                $locked->forceFill([
                    'last_reconciled_at' => now(),
                    'reconciliation_attempts' => ((int) $locked->reconciliation_attempts) + 1,
                    'reconciliation_note' => 'Gateway tidak mendukung rekonsiliasi otomatis.',
                ])->save();

                foreach ($locked->orders()->lockForUpdate()->get() as $order) {
                    $order->forceFill(['reconciled_at' => now()])->save();
                }

                return ['success' => false, 'status' => (string) $locked->status, 'matched' => false, 'message' => 'Gateway tidak mendukung rekonsiliasi otomatis.'];
            }

            $result = $this->reconcile($provider, $reference);
            $remote = strtolower((string) ($result['status'] ?? $result['transaction_status'] ?? 'unknown'));
            $local = strtolower((string) $locked->status);
            $matched = $result['success'] ?? false
                ? in_array($remote, [$local, 'paid', 'settlement', 'success'], true) || $remote === $local
                : false;

            $locked->forceFill([
                'last_reconciled_at' => now(),
                'reconciliation_attempts' => ((int) $locked->reconciliation_attempts) + 1,
                'reconciliation_note' => mb_substr(
                    ($result['success'] ?? false ? 'Cocok' : 'Selisih').': gateway='.($remote ?: '?').' lokal='.$local,
                    0, 500,
                ),
            ])->save();

            foreach ($locked->orders()->lockForUpdate()->get() as $order) {
                $order->forceFill(['reconciled_at' => now()])->save();
            }

            return [
                'success' => (bool) ($result['success'] ?? false),
                'status' => $remote,
                'matched' => $matched,
                'message' => $matched ? 'Pembayaran cocok dengan gateway.' : 'Ditemukan selisih, periksa dashboard rekonsiliasi.',
            ];
        }, 3);
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

        return ['external_id' => null, 'gateway_transaction_id' => null, 'status' => null];
    }

    // ── COD OTP: verifikasi saat terima untuk COD di atas ambang nominal ──

    public const COD_OTP_MAX_ATTEMPTS = 5;

    public const COD_OTP_TTL_MINUTES = 15;

    /** Ambang nominal COD yang wajib OTP (rupiah), via SystemSetting. */
    public static function codOtpThreshold(): float
    {
        $raw = \App\Models\SystemSetting::get('cod_otp_threshold', '500000');

        return max(0.0, is_numeric($raw) ? (float) $raw : 500000.0);
    }

    public function codOtpRequired(\App\Models\Order $order): bool
    {
        return $order->codOtpRequired(static::codOtpThreshold());
    }

    /**
     * Generate OTP 6-digit untuk order COD. Disimpan sebagai hash + expiry,
     * upaya direset; atomik via lockForUpdate dalam transaksi.
     *
     * @return string kode plaintext (disalurkan via SMS/notifikasi di lapisan pemanggil)
     */
    public function generateCodOtp(\App\Models\Order $order): string
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($order): string {
            $locked = \App\Models\Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->codOtpRequired(static::codOtpThreshold())) {
                throw new \DomainException('Order ini tidak wajib verifikasi OTP COD.');
            }

            $code = (string) random_int(100000, 999999);

            $locked->forceFill([
                'cod_otp_hash' => \Illuminate\Support\Facades\Hash::make($code),
                'cod_otp_expires_at' => now()->addMinutes(self::COD_OTP_TTL_MINUTES),
                'cod_otp_attempts' => 0,
                'cod_otp_verified_at' => null,
            ])->save();

            return $code;
        }, 3);
    }

    /**
     * Validasi OTP COD. Idempoten bila sudah terverifikasi; rate-limit
     * maksimal COD_OTP_MAX_ATTEMPTS upaya gagal; kedaluwarsa ditolak.
     */
    public function verifyCodOtp(\App\Models\Order $order, string $code): bool
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($order, $code): bool {
            $locked = \App\Models\Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->codOtpVerified()) {
                return true;
            }

            if ($locked->cod_otp_hash === null) {
                return false;
            }

            if ($locked->cod_otp_expires_at !== null && now()->greaterThan($locked->cod_otp_expires_at)) {
                return false;
            }

            if ((int) $locked->cod_otp_attempts >= self::COD_OTP_MAX_ATTEMPTS) {
                return false;
            }

            if (! \Illuminate\Support\Facades\Hash::check(trim($code), (string) $locked->cod_otp_hash)) {
                $locked->forceFill([
                    'cod_otp_attempts' => ((int) $locked->cod_otp_attempts) + 1,
                ])->save();

                return false;
            }

            $locked->forceFill([
                'cod_otp_verified_at' => now(),
            ])->save();

            return true;
        }, 3);
    }
}
