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

    // ── ADITIF global-checkout: routing + fallback via app/Payments ──
    // Adapter existing di atas TIDAK diubah. Blok ini hanya menambah
    // capability check + pemilihan provider + fallback aman + guard
    // idempotency anti double-charge.

    /** ID-centric formats: tanpa metadata config, hanya layani ID/IDR. */
    private const ID_ONLY_FORMATS = [
        'midtrans-snap',
        'midtrans-core',
        'xendit-invoice',
        'tripay-closed',
    ];

    /** Kode hasil yang membuktikan TIDAK ada tagihan terbentuk (aman fallback). */
    private const SAFE_FALLBACK_CODES = [
        'unsupported_format',
        'unsupported_currency',
        'unsupported_country',
        'unsupported_method',
        'no_capable_provider',
        'validation_error',
        'declined',
        'rejected',
    ];

    /** Kemampuan provider dari kolom config (tanpa menebak header user). */
    public function providerCapabilities(Provider $provider): array
    {
        $config = $provider->config;
        $config = is_array($config) ? $config : [];

        $upper = static fn ($v): array => array_values(array_unique(array_map(
            static fn ($x): string => strtoupper(trim((string) $x)),
            (array) $v,
        )));

        $currencies = isset($config['currencies']) && is_array($config['currencies']) && $config['currencies'] !== []
            ? $upper($config['currencies'])
            : ['IDR'];
        $countries = isset($config['countries']) && is_array($config['countries']) && $config['countries'] !== []
            ? $upper($config['countries'])
            : null; // null = tak dideklarasikan → cek format/default di bawah
        $methods = isset($config['methods']) && is_array($config['methods']) && $config['methods'] !== []
            ? array_values(array_unique(array_map(
                static fn ($x): string => \App\Payments\PaymentMethod::normalize((string) $x),
                $config['methods'],
            )))
            : [];

        return ['currencies' => $currencies, 'countries' => $countries, 'methods' => $methods, 'gateway' => strtolower((string) ($config['gateway'] ?? ''))];
    }

    /** True bila gateway sanggup menagih currency tsb (selalu benar untuk IDR). */
    public function gatewaySupportsCurrency(Provider $provider, string $currency): bool
    {
        $currency = strtoupper(trim($currency));

        if ($currency === '' || $currency === 'IDR') {
            return true;
        }

        $caps = $this->providerCapabilities($provider);

        if (! in_array($currency, $caps['currencies'], true)) {
            return false;
        }

        // Cross-check registry app/Payments bila config menunjuk gateway terdaftar.
        if ($caps['gateway'] !== '') {
            try {
                $registry = new \App\Payments\GatewayRegistry();
                if ($registry->has($caps['gateway']) && ! $registry->resolve($caps['gateway'])->supportsCurrency($currency)) {
                    return false;
                }
            } catch (\Throwable) {
                // Registry opsional; kegagalan resolve tidak menggugurkan capability config.
            }
        }

        return true;
    }

    /** True bila provider boleh dipakai untuk negara tsb (server-side). */
    public function gatewaySupportsCountry(Provider $provider, string $country): bool
    {
        $country = strtoupper(trim($country));

        if ($country === '') {
            return false;
        }

        $caps = $this->providerCapabilities($provider);

        if (is_array($caps['countries']) && $caps['countries'] !== []) {
            $ok = in_array($country, $caps['countries'], true);
        } else {
            // Tanpa deklarasi: format ID-centric hanya ID; lainnya terbuka.
            $ok = in_array((string) $provider->api_format, self::ID_ONLY_FORMATS, true)
                ? $country === 'ID'
                : true;
        }

        if (! $ok) {
            return false;
        }

        if ($caps['gateway'] !== '') {
            try {
                $registry = new \App\Payments\GatewayRegistry();
                if ($registry->has($caps['gateway']) && ! $registry->resolve($caps['gateway'])->supportsCountry($country)) {
                    return false;
                }
            } catch (\Throwable) {
            }
        }

        return true;
    }

    /**
     * Pilih provider terbaik: preferensi dulu bila capable, lalu urutan
     * sort_order. Mengembalikan null bila tak ada yang capable.
     */
    public function routeProvider(?string $currency = null, ?string $country = null, ?int $preferredId = null): ?Provider
    {
        $currency = $currency !== null ? strtoupper(trim($currency)) : null;
        $country = $country !== null ? strtoupper(trim($country)) : null;

        $pool = Provider::ofType('payment')->active()->orderBy('sort_order')->orderBy('id')->get();

        $capable = $pool->filter(fn (Provider $p): bool => ($currency === null || $currency === '' || $this->gatewaySupportsCurrency($p, $currency))
            && ($country === null || $country === '' || $this->gatewaySupportsCountry($p, $country)));

        if ($capable->isEmpty()) {
            return null;
        }

        if ($preferredId !== null) {
            $preferred = $capable->firstWhere('id', (int) $preferredId);
            if ($preferred) {
                return $preferred;
            }
        }

        return $capable->first();
    }

    /**
     * Buat pembayaran dengan fallback aman antar provider capable.
     *
     * - Guard idempotency: kunci yang sudah sukses TIDAK PERNAH menagih ulang
     *   (hasil pertama dikembalikan dari cache).
     * - Fallback HANYA bila hasil pertama membuktikan tidak ada tagihan
     *   terbentuk (kode aman) — timeout/ambiguous TIDAK di-fallback otomatis
     *   agar tidak double charge.
     */
    public function createPaymentWithFallback(Provider $primary, array $payload, ?string $idempotencyKey = null, array $context = []): array
    {
        $currency = strtoupper(trim((string) ($context['currency'] ?? $payload['currency'] ?? 'IDR')) ?: 'IDR');
        $country = strtoupper(trim((string) ($context['country'] ?? $payload['country'] ?? 'ID')) ?: 'ID');

        $key = is_string($idempotencyKey) && trim($idempotencyKey) !== '' ? trim($idempotencyKey) : null;

        if ($key !== null) {
            $cached = $this->recallIdempotent($key);
            if ($cached !== null) {
                return $cached + ['idempotent_replay' => true];
            }
            if (! $this->claimIdempotent($key)) {
                $cached = $this->recallIdempotent($key);
                if ($cached !== null) {
                    return $cached + ['idempotent_replay' => true];
                }

                return ['success' => false, 'code' => 'processing', 'message' => 'Pembayaran sedang diproses untuk kunci ini. / Payment is already processing for this key.'];
            }
        }

        // Kandidat: primary dulu, lalu provider capable lain (sort_order).
        $candidates = collect([$primary]);
        $routed = $this->routeProvider($currency, $country, (int) $primary->getKey());
        if ($routed && (int) $routed->getKey() !== (int) $primary->getKey()) {
            $candidates->push($routed);
        }
        try {
            $others = Provider::ofType('payment')->active()
                ->where('id', '!=', (int) $primary->getKey())
                ->orderBy('sort_order')->orderBy('id')->get()
                ->filter(fn (Provider $p): bool => $this->gatewaySupportsCurrency($p, $currency) && $this->gatewaySupportsCountry($p, $country));
            foreach ($others as $other) {
                if (! $candidates->contains(fn (Provider $p): bool => (int) $p->getKey() === (int) $other->getKey())) {
                    $candidates->push($other);
                }
            }
        } catch (\Throwable) {
        }

        $last = null;
        $attempted = 0;
        foreach ($candidates as $candidate) {
            // Jangan tagih dalam currency yang tak didukung kandidat ini.
            if (! $this->gatewaySupportsCurrency($candidate, $currency) || ! $this->gatewaySupportsCountry($candidate, $country)) {
                $last = ['success' => false, 'code' => 'unsupported_country', 'message' => 'Provider tidak mendukung negara/currency ini.'];
                continue;
            }

            $attemptPayload = $payload;
            $attemptPayload['currency'] = 'IDR';
            $attemptPayload['country'] = $country;

            // Charge non-IDR HANYA bila capability check lolos + konversi presisi ada.
            if ($currency !== 'IDR' && $this->gatewaySupportsCurrency($candidate, $currency)) {
                $converted = $this->convertChargeAmount((float) ($payload['amount'] ?? 0), $currency);
                if ($converted !== null) {
                    $attemptPayload['currency'] = $currency;
                    $attemptPayload['amount'] = $converted;
                }
            }

            try {
                $result = $this->createPayment($candidate, $attemptPayload);
            } catch (\Throwable $e) {
                $last = ['success' => false, 'code' => 'exception', 'message' => 'Gateway error.'];
                continue;
            }

            $attempted++;
            $result['provider_id'] = (int) $candidate->getKey();
            $result['charge_currency'] = (string) ($attemptPayload['currency'] ?? 'IDR');
            $result['fallback_attempts'] = $attempted - 1;

            if (($result['success'] ?? false) === true) {
                if ($key !== null) {
                    $this->storeIdempotent($key, $result);
                }

                return $result;
            }

            $last = $result;

            // Fallback hanya bila aman (terbukti tak ada tagihan terbentuk).
            if (! $this->isSafeToFallback($result)) {
                break;
            }
        }

        if ($key !== null) {
            $this->releaseIdempotent($key);
        }

        return is_array($last) ? $last : ['success' => false, 'code' => 'all_failed', 'message' => 'Semua gateway gagal. / All gateways failed.'];
    }

    /**
     * Konversi presisi IDR → currency tujuan memakai string kurs DB (tanpa float).
     * Null bila tabel/kurs tak tersedia (pemanggil tetap menagih IDR).
     */
    public function convertChargeAmount(float $amountIdr, string $toCurrency): ?float
    {
        $toCurrency = strtoupper(trim($toCurrency));

        if ($toCurrency === '' || $toCurrency === 'IDR' || $amountIdr < 0) {
            return null;
        }

        try {
            if (! class_exists(\App\Services\Currency\CurrencyService::class)) {
                return null;
            }
            $from = \App\Services\Currency\CurrencyService::find('IDR');
            $to = \App\Services\Currency\CurrencyService::find($toCurrency);
            if ($from === null || $to === null || ! (bool) $to->is_active || ! (bool) $from->is_active) {
                return null;
            }
            $minor = \App\Services\Currency\CurrencyConverter::convertMinor(
                (int) round($amountIdr),
                (int) $from->decimal_places,
                (int) $to->decimal_places,
                (string) $from->exchange_rate,
                (string) $to->exchange_rate,
            );
            $factor = 10 ** max(0, (int) $to->decimal_places);

            return $minor / $factor;
        } catch (\Throwable) {
            return null;
        }
    }

    private function isSafeToFallback(array $result): bool
    {
        $code = strtolower((string) ($result['code'] ?? ''));

        return in_array($code, self::SAFE_FALLBACK_CODES, true);
    }

    private function idemKey(string $key): string
    {
        return 'checkout:pay:'.sha1($key);
    }

    private function recallIdempotent(string $key): ?array
    {
        try {
            $value = \Illuminate\Support\Facades\Cache::get($this->idemKey($key));
        } catch (\Throwable) {
            return null;
        }

        return is_array($value) && ($value['__done'] ?? false) === true ? ($value['result'] ?? null) : null;
    }

    private function claimIdempotent(string $key): bool
    {
        try {
            return \Illuminate\Support\Facades\Cache::add($this->idemKey($key), ['__done' => false], 86400);
        } catch (\Throwable) {
            return true;
        }
    }

    private function storeIdempotent(string $key, array $result): void
    {
        try {
            \Illuminate\Support\Facades\Cache::put($this->idemKey($key), ['__done' => true, 'result' => $result], 86400);
        } catch (\Throwable) {
        }
    }

    private function releaseIdempotent(string $key): void
    {
        try {
            \Illuminate\Support\Facades\Cache::forget($this->idemKey($key));
        } catch (\Throwable) {
        }
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
