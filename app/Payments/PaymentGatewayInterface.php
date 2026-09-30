<?php

declare(strict_types=1);

namespace App\Payments;

/**
 * Kontrak gateway pembayaran generik / Generic payment gateway contract.
 *
 * Semua provider (Midtrans, Xendit, Tripay, Fake, ...) wajib mengimplementasikan
 * interface ini agar PaymentRouter + WebhookPipeline bisa bertukar provider
 * tanpa menyentuh sistem existing di app/Services/Payment/**.
 */
interface PaymentGatewayInterface
{
    /** Nama unik gateway, mis. "fake", "midtrans-snap", "xendit-invoice". */
    public function getName(): string;

    /**
     * Buat sesi/tagihan awal (mendapatkan redirect URL / token).
     *
     * @return array{reference_id:string,status:string,redirect_url?:string,raw:array}
     */
    public function initialize(array $payload): array;

    /** Otorisasi dana tanpa capture (two-step flow kartu). */
    public function authorize(array $payload): array;

    /** Capture dana yang sebelumnya di-authorize. */
    public function capture(string $referenceId, array $options = []): array;

    /** Satu langkah authorize+capture. */
    public function charge(array $payload, ?string $idempotencyKey = null): array;

    /** Refund penuh/parsial. */
    public function refund(string $referenceId, float $amount, array $options = []): array;

    /** Batalkan otorisasi yang belum di-capture. */
    public function void(string $referenceId, array $options = []): array;

    /** Verifikasi tanda tangan webhook. True bila valid. */
    public function verify(array $payload, array $headers): bool;

    /**
     * Normalisasi event webhook menjadi bentuk baku.
     *
     * @return array{event_id:string,reference_id:string,status:string,amount:?float,raw:array}
     */
    public function handleWebhook(array $payload, array $headers): array;

    /** Status mutakhir transaksi di sisi gateway. */
    public function getStatus(string $referenceId): array;

    public function supportsCurrency(string $currency): bool;

    public function supportsCountry(string $country): bool;

    public function supportsPaymentMethod(string $method): bool;
}
