<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Provider;
use App\Support\Money;
use Illuminate\Support\Facades\Http;
use Throwable;

class XenditInvoiceAdapter implements PaymentAdapterInterface
{
    private const TIMEOUT = 15;

    private const CONNECT_TIMEOUT = 5;

    public function __construct(protected Provider $provider) {}

    public function createTransaction(array $payload): array
    {
        $body = [
            'external_id' => $payload['order_id'] ?? uniqid('ORD-'),
            'amount' => Money::of($payload['amount'] ?? 0)->toFloat(),
            'payer_email' => $payload['customer']['email'] ?? '',
            'description' => $payload['description'] ?? 'Pembayaran pesanan',
            'success_redirect_url' => $payload['success_url'] ?? '',
            'failure_redirect_url' => $payload['failure_url'] ?? $payload['success_url'] ?? '',
        ];

        if (($payload['customer']['phone'] ?? null) !== null) {
            $body['customer'] = ['given_names' => $payload['customer']['name'] ?? 'Customer'];
        }

        return $this->post('/v2/invoices', $body, function (array $data) {
            return [
                'success' => true,
                'redirect_url' => $data['invoice_url'] ?? null,
                'invoice_id' => $data['id'] ?? null,
                'raw' => $data,
            ];
        });
    }

    public function getTransactionStatus(string $transactionId): array
    {
        $reference = $this->reference($transactionId, []);

        if ($reference === null) {
            return ['success' => false, 'code' => 'unsupported', 'message' => 'Referensi invoice tidak tersedia.'];
        }

        return $this->get("/v2/invoices/{$reference}", function (array $data) {
            return ['success' => true, 'data' => $data];
        });
    }

    public function verifyCallback(array $requestData): bool
    {
        $expected = $this->provider->getApiSecretAttribute();
        $incoming = (string) ($requestData['headers']['x-callback-token'] ?? '');

        return $expected !== null && $expected !== '' && hash_equals($expected, $incoming);
    }

    public function getChannels(): array
    {
        return ['BCA', 'BNI', 'BRI', 'MANDIRI', 'PERMATA', 'QRIS', 'OVO', 'DANA', 'LINKAJA', 'ALFAMART', 'INDOMARET'];
    }

    public function refund(string $gatewayRefundId, float $amount, array $options = []): array
    {
        $invoiceId = $options['invoice_id'] ?? $this->reference($gatewayRefundId, $options);

        if ($invoiceId === null) {
            return SnapRedirectAdapter::unsupported('Referensi invoice Xendit tidak tersedia untuk refund.');
        }

        return $this->post('/refund', [
            'invoice_id' => $invoiceId,
            'amount' => Money::of($amount)->toFloat(),
            'reason' => (string) ($options['reason'] ?? 'Permintaan refund pelanggan'),
        ], function (array $data) {
            return [
                'success' => true,
                'refund_id' => $data['id'] ?? null,
                'status' => strtolower((string) ($data['status'] ?? 'pending')),
                'raw' => $data,
            ];
        });
    }

    public function cancel(string $gatewayPaymentId): array
    {
        $invoiceId = $this->reference($gatewayPaymentId, []);

        if ($invoiceId === null) {
            return SnapRedirectAdapter::unsupported('Referensi invoice Xendit tidak tersedia untuk pembatalan.');
        }

        $response = $this->request(fn () => Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout(self::TIMEOUT)->connectTimeout(self::CONNECT_TIMEOUT)
            ->withBasicAuth($this->secretKey(), '')
            ->post($this->baseUrl().'/v2/invoices/'.$invoiceId.'/expire'));

        if ($response === null) {
            return ['success' => false, 'code' => 'unreachable', 'message' => 'Xendit tidak dapat dihubungi.'];
        }

        $data = $response->json();
        $data = is_array($data) ? $data : [];

        if ($response->successful()) {
            return ['success' => true, 'status' => strtolower((string) ($data['status'] ?? 'expired')), 'raw' => $data];
        }

        PaymentLog::channel('warning', 'Xendit rejected cancel request', [
            'provider_id' => $this->provider->id,
            'status' => $response->status(),
            'body' => $data,
        ]);

        return ['success' => false, 'code' => 'gateway_rejected', 'message' => 'Xendit menolak pembatalan.', 'status' => $response->status()];
    }

    protected function secretKey(): string
    {
        $secret = $this->provider->getApiSecretAttribute();

        return $secret !== null && $secret !== '' ? $secret : (string) $this->provider->getApiKeyAttribute();
    }

    protected function reference(string $identifier, array $options): ?string
    {
        $candidate = trim($identifier);

        if ($candidate === '' || str_starts_with($candidate, 'synthetic:')) {
            $candidate = trim((string) ($options['payment_number'] ?? ''));
        }

        if ($candidate === '' || str_starts_with($candidate, 'synthetic:')) {
            return null;
        }

        return rawurlencode($candidate);
    }

    protected function post(string $path, array $body, callable $onSuccess): array
    {
        $response = $this->request(fn () => Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(self::TIMEOUT)->connectTimeout(self::CONNECT_TIMEOUT)
            ->withBasicAuth($this->secretKey(), '')
            ->post($this->baseUrl().$path, $body));

        return $this->interpret($response, $onSuccess);
    }

    protected function get(string $path, callable $onSuccess): array
    {
        $response = $this->request(fn () => Http::withHeaders(['Accept' => 'application/json'])
            ->timeout(self::TIMEOUT)->connectTimeout(self::CONNECT_TIMEOUT)
            ->withBasicAuth((string) $this->provider->getApiKeyAttribute(), '')
            ->get($this->baseUrl().$path));

        return $this->interpret($response, $onSuccess);
    }

    protected function baseUrl(): string
    {
        return rtrim((string) $this->provider->base_url, '/');
    }

    protected function request(callable $callback): ?\Illuminate\Http\Client\Response
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            PaymentLog::channel('error', 'Xendit request failed', [
                'provider_id' => $this->provider->id,
                'exception' => $e::class,
            ]);

            return null;
        }
    }

    protected function interpret(?\Illuminate\Http\Client\Response $response, callable $onSuccess): array
    {
        if ($response === null) {
            return ['success' => false, 'code' => 'unreachable', 'message' => 'Xendit tidak dapat dihubungi.'];
        }

        $data = $response->json();
        $data = is_array($data) ? $data : [];

        if ($response->successful()) {
            return $onSuccess($data);
        }

        PaymentLog::channel('warning', 'Xendit rejected request', [
            'provider_id' => $this->provider->id,
            'status' => $response->status(),
            'body' => $data,
        ]);

        return [
            'success' => false,
            'code' => 'gateway_rejected',
            'message' => 'Xendit menolak permintaan.',
            'status' => $response->status(),
        ];
    }
}
