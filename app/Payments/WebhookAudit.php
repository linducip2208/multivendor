<?php

declare(strict_types=1);

namespace App\Payments;

use App\Models\Provider;
use App\Services\Payment\PaymentGatewayService;

/**
 * Audit webhook per provider — JUJUR mana yang didukung gateway.
 *
 * Sumber: PaymentGatewayService::{supportsCallbackVerification,supportsRefunds,
 * supportsReconciliation} + format terdaftar. Format generik
 * (duitku/oy/ipaymu/faspay/doku/esia) dinyatakan TIDAK terverifikasi
 * (verifyCallback=false) agar integrator tidak mengandalkannya.
 */
final class WebhookAudit
{
    /**
     * @return array{provider_id:int,api_format:string,signature_verification:bool,timestamp_check:bool,idempotency:bool,retry:bool,dead_letter:bool,refund_supported:bool,reconciliation_supported:bool,note:string}
     */
    public static function for(Provider $provider): array
    {
        $format = (string) $provider->api_format;
        $verified = PaymentGatewayService::supportsCallbackVerification($format);

        return [
            'provider_id' => (int) $provider->id,
            'api_format' => $format,
            'signature_verification' => $verified,
            'timestamp_check' => $verified, // timestamp hanya bermakna bila signature terverifikasi
            'idempotency' => true, // controller + pipeline selalu dedupe
            'retry' => true,
            'dead_letter' => true,
            'refund_supported' => PaymentGatewayService::supportsRefunds($format),
            'reconciliation_supported' => PaymentGatewayService::supportsReconciliation($format),
            'note' => $verified
                ? 'Webhook terverifikasi + timestamp + retry + dead-letter.'
                : 'TIDAK ada verifikasi signature untuk format ini; webhook ditolak (401) dan dicatat dead-letter.',
        ];
    }

    /** @return array<int, array> */
    public static function all(): array
    {
        return Provider::ofType('payment')->orderBy('id')->get()->map(fn (Provider $p): array => self::for($p))->all();
    }
}
