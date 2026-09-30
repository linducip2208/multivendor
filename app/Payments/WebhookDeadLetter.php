<?php

declare(strict_types=1);

namespace App\Payments;

use App\Models\PaymentWebhookCallback;
use App\Services\Payment\PaymentLog;

/**
 * Dead-letter webhook: memakai tabel existing payment_webhook_callbacks.
 *
 * Konvensi tanpa migrasi: processing_result='failed' + processed_at terisi
 * + headers['X-Dead-Letter']='true' + headers['X-Retry-Count']=n.
 * Retry memakai backoff 1,2,4,... menit (maks 60) dihitung dari received_at.
 */
final class WebhookDeadLetter
{
    public const MAX_RETRIES = 5;

    public static function markDead(PaymentWebhookCallback $record, string $reason): PaymentWebhookCallback
    {
        $headers = is_array($record->headers) ? $record->headers : [];
        $headers['X-Dead-Letter'] = 'true';
        $headers['X-Dead-Reason'] = mb_substr($reason, 0, 200);
        $record->forceFill(['headers' => $headers, 'processing_result' => WebhookPipeline::FAILED, 'processed_at' => now()])->save();
        PaymentLog::channel('error', 'Webhook dead-letter', [
            'provider_id' => $record->provider_id,
            'callback_id' => $record->id,
        ]);

        return $record->fresh();
    }

    public static function isDead(PaymentWebhookCallback $record): bool
    {
        $headers = is_array($record->headers) ? $record->headers : [];

        return $record->processing_result === WebhookPipeline::FAILED
            && ($headers['X-Dead-Letter'] ?? null) === 'true';
    }

    public static function retryCount(PaymentWebhookCallback $record): int
    {
        $headers = is_array($record->headers) ? $record->headers : [];

        return max(0, (int) ($headers['X-Retry-Count'] ?? 0));
    }

    public static function canRetry(PaymentWebhookCallback $record): bool
    {
        if (! self::isDead($record)) {
            return $record->processing_result === WebhookPipeline::FAILED;
        }

        return self::retryCount($record) < self::MAX_RETRIES;
    }

    /** Detik backoff: 60,120,240,480,960 (cap 3600). */
    public static function backoffSeconds(int $retryCount): int
    {
        return (int) min(3600, 60 * (2 ** max(0, $retryCount)));
    }

    public static function dueForRetry(PaymentWebhookCallback $record): bool
    {
        if (! self::canRetry($record)) {
            return false;
        }
        $base = $record->processed_at ?? $record->received_at ?? now();

        return now()->diffInSeconds($base) >= self::backoffSeconds(self::retryCount($record));
    }

    /** Tandai satu percobaan retry (tanpa memproses bisnis). */
    public static function noteRetry(PaymentWebhookCallback $record): PaymentWebhookCallback
    {
        $headers = is_array($record->headers) ? $record->headers : [];
        $headers['X-Retry-Count'] = self::retryCount($record) + 1;
        unset($headers['X-Dead-Letter']);
        $record->forceFill(['headers' => $headers, 'processing_result' => WebhookPipeline::RECEIVED, 'processed_at' => null])->save();

        return $record->fresh();
    }

    /** @return array<int, array{id:int,provider_id:int,retries:int,reason:?string}> */
    public static function queue(int $limit = 50): array
    {
        return PaymentWebhookCallback::query()
            ->where('processing_result', WebhookPipeline::FAILED)
            ->orderBy('received_at')
            ->limit(max(1, min(200, $limit)))
            ->get()
            ->map(fn (PaymentWebhookCallback $cb): array => [
                'id' => (int) $cb->id,
                'provider_id' => (int) $cb->provider_id,
                'retries' => self::retryCount($cb),
                'reason' => is_array($cb->headers) ? ($cb->headers['X-Dead-Reason'] ?? null) : null,
            ])->all();
    }
}
