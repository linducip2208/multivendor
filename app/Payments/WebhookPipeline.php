<?php

declare(strict_types=1);

namespace App\Payments;

use App\Models\PaymentWebhookCallback;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pipeline webhook: signature → timestamp → idempotency → event store.
 *
 * Memakai tabel existing `payment_webhook_callbacks` (verifikasi skema:
 *  - 2026_09_09_000001: id, provider_id FK, payment_group_id?, gateway_transaction_id?,
 *    external_id, status, payload(json), headers(json), received_at,
 *    processed_at?, processing_result default 'received',
 *    unique [provider_id, gateway_transaction_id]
 *  - 2026_09_28_000100: + reported_amount?, expected_amount?,
 *    index [processing_result, received_at]
 * Jadi TIDAK ada tabel baru — status 'received/verified/processing/processed/failed/ignored'
 * dipetakan ke kolom `processing_result` yang sudah ada).
 */
final class WebhookPipeline
{
    public const RECEIVED = 'received';
    public const VERIFIED = 'verified';
    public const PROCESSING = 'processing';
    public const PROCESSED = 'processed';
    public const FAILED = 'failed';
    public const IGNORED = 'ignored';

    private GatewayRegistry $registry;
    private int $timestampTolerance;

    public function __construct(?GatewayRegistry $registry = null, int $timestampTolerance = 300)
    {
        $this->registry = $registry ?? new GatewayRegistry();
        $this->timestampTolerance = $timestampTolerance;
    }

    /**
     * Jalankan pipeline untuk satu callback.
     *
     * @param array $payload Body webhook ter-decode.
     * @param array $headers Header (X-Signature, X-Timestamp, X-Event-Id).
     * @param callable|null $apply Dipanggil saat status PROCESSING: fn(array $event, PaymentWebhookCallback $record): void
     */
    public function handle(
        int $providerId,
        string $gatewayName,
        array $payload,
        array $headers,
        ?callable $apply = null,
        ?float $expectedAmount = null
    ): PaymentWebhookCallback {
        $gateway = $this->registry->resolve($gatewayName);

        // 1) Idempotency: event yang sama persis → kembalikan baris lama sebagai ignored.
        $gatewayTxId = $this->transactionId($payload, $headers);
        if ($gatewayTxId !== null) {
            $existing = PaymentWebhookCallback::query()
                ->where('provider_id', $providerId)
                ->where('gateway_transaction_id', $gatewayTxId)
                ->first();
            if ($existing instanceof PaymentWebhookCallback && $existing->processing_result === self::PROCESSED) {
                return $existing; // replay aman: tidak diproses ulang
            }
            if ($existing instanceof PaymentWebhookCallback && $existing->processing_result !== self::FAILED) {
                $existing->forceFill(['processing_result' => self::IGNORED])->save();

                return $existing->fresh();
            }
        }

        // 2) Event store: catat dulu sebagai received (tanpa gateway_transaction_id bila null
        //    agar tidak menabrak unique constraint pada NULL di MySQL — di SQLite NULL bebas).
        $record = new PaymentWebhookCallback();
        $record->forceFill([
            'provider_id' => $providerId,
            'payment_group_id' => null,
            'gateway_transaction_id' => $gatewayTxId,
            'external_id' => $headers['X-Event-Id'] ?? $headers['x-event-id'] ?? ($payload['event_id'] ?? null),
            'status' => isset($payload['status']) ? (string) $payload['status'] : null,
            'payload' => $payload,
            'headers' => $headers,
            'received_at' => now(),
            'processing_result' => self::RECEIVED,
            'reported_amount' => isset($payload['amount']) ? (float) $payload['amount'] : null,
            'expected_amount' => $expectedAmount,
        ]);
        try {
            $record->save();
        } catch (Throwable $e) {
            // Balapan insert konkuren dengan unique key → anggap duplikat.
            if ($gatewayTxId !== null && $this->isUniqueViolation($e)) {
                $existing = PaymentWebhookCallback::query()
                    ->where('provider_id', $providerId)
                    ->where('gateway_transaction_id', $gatewayTxId)
                    ->first();
                if ($existing instanceof PaymentWebhookCallback) {
                    $existing->forceFill(['processing_result' => self::IGNORED])->save();

                    return $existing->fresh();
                }
            }
            throw $e;
        }

        // 3) Signature.
        try {
            if (! $gateway->verify($payload, $headers)) {
                throw new WebhookVerificationException('tanda tangan tidak valid / invalid signature', [
                    'provider_id' => $providerId,
                ]);
            }
        } catch (WebhookVerificationException $e) {
            $record->forceFill(['processing_result' => self::FAILED, 'processed_at' => now()])->save();

            throw $e;
        }
        $record->forceFill(['processing_result' => self::VERIFIED])->save();

        // 4) Timestamp freshness.
        $timestamp = $headers['X-Timestamp'] ?? $headers['x-timestamp'] ?? null;
        if ($timestamp !== null && abs(time() - (int) $timestamp) > $this->timestampTolerance) {
            $record->forceFill(['processing_result' => self::IGNORED, 'processed_at' => now()])->save();

            return $record->fresh();
        }

        // 5) Processing → apply efek bisnis → processed/failed.
        $record->forceFill(['processing_result' => self::PROCESSING])->save();
        try {
            $event = $gateway->handleWebhook($payload, $headers);
            if (($event['duplicate'] ?? false) === true) {
                $record->forceFill(['processing_result' => self::IGNORED, 'processed_at' => now()])->save();

                return $record->fresh();
            }
            if ($apply !== null) {
                DB::transaction(function () use ($apply, $event, $record): void {
                    $apply($event, $record->fresh());
                });
            }
            $record->forceFill([
                'processing_result' => self::PROCESSED,
                'processed_at' => now(),
                'status' => (string) ($event['status'] ?? $record->status),
            ])->save();

            return $record->fresh();
        } catch (WebhookVerificationException $e) {
            $record->forceFill(['processing_result' => self::FAILED, 'processed_at' => now()])->save();

            throw $e;
        } catch (Throwable $e) {
            $record->forceFill(['processing_result' => self::FAILED, 'processed_at' => now()])->save();
            throw new PaymentException(
                'Gagal memproses webhook. / Failed to process webhook.',
                'Gagal memproses webhook.',
                'Failed to process webhook.',
                ['provider_id' => $providerId],
                $e
            );
        }
    }

    private function transactionId(array $payload, array $headers): ?string
    {
        $candidates = [
            $payload['gateway_transaction_id'] ?? null,
            $payload['reference_id'] ?? null,
            $payload['transaction_id'] ?? null,
            $headers['X-Event-Id'] ?? null,
            $headers['x-event-id'] ?? null,
            $payload['event_id'] ?? null,
        ];
        foreach ($candidates as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function isUniqueViolation(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        if (str_contains($message, 'unique') || str_contains($message, 'duplicate')) {
            return true;
        }
        $code = (string) ($e->getCode() ?? '');
        if (in_array($code, ['23000', '23505', '19'], true)) {
            return true;
        }
        $previous = $e->getPrevious();
        if ($previous instanceof Throwable) {
            return $this->isUniqueViolation($previous);
        }

        return false;
    }
}
