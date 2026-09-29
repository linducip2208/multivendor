<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Payment\PaymentStatusMachine;
use App\Enums\PaymentStatus;
use App\Models\PaymentGroup;
use App\Services\Payment\PaymentGatewayService;
use App\Services\Payment\PaymentGroupApplier;
use App\Services\Payment\PaymentLog;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ReconcilePaymentGroup implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $backoff = 60;

    public int $timeout = 90;

    private static ?bool $trackingColumns = null;

    public function __construct(public int $paymentGroupId) {}

    public function handle(PaymentGatewayService $payments, PaymentGroupApplier $applier): void
    {
        $group = PaymentGroup::with('provider')->find($this->paymentGroupId);

        if (! $group) {
            return;
        }

        if (PaymentStatusMachine::isTerminal($group->status)) {
            $this->markChecked($group, 'terminal');

            return;
        }

        $provider = $group->provider;

        if (! $provider || ! $provider->is_active) {
            $this->markChecked($group, 'provider_inactive');

            return;
        }

        if (! PaymentGatewayService::supportsReconciliation((string) $provider->api_format)) {
            $this->markChecked($group, 'unsupported');

            return;
        }

        $reference = trim((string) $group->gateway_reference);

        if ($reference === '' || str_starts_with($reference, 'synthetic:')) {
            $this->markChecked($group, 'no_reference');

            return;
        }

        try {
            $result = $payments->reconcile($provider, $reference);
        } catch (Throwable $e) {
            PaymentLog::channel('error', 'Payment reconciliation request failed', [
                'payment_group_id' => $group->id,
                'provider_id' => $provider->id,
                'exception' => $e::class,
            ]);

            $this->markChecked($group, 'gateway_error');

            return;
        }

        if (! ($result['success'] ?? false)) {
            PaymentLog::channel('warning', 'Payment reconciliation could not be completed', [
                'payment_group_id' => $group->id,
                'provider_id' => $provider->id,
                'code' => $result['code'] ?? null,
            ]);

            $this->markChecked($group, 'gateway_error');

            return;
        }

        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        $normalized = $payments->normalizeCallback($provider, ['body' => $this->normalisePayload($data, $group)]);
        $incoming = PaymentStatusMachine::resolve($normalized['status']);

        if ($incoming === null) {
            $this->markChecked($group, 'unknown_status');

            return;
        }

        $reported = $payments->reportedAmount($provider, ['body' => $data]);
        $expected = Money::of($group->grand_total);
        $tolerance = Money::of(PaymentGatewayService::amountTolerance());

        if ($reported !== null) {
            $delta = Money::of($reported)->subtract($expected);

            if (abs($delta->minor) > $tolerance->minor) {
                PaymentLog::channel('critical', 'Reconciliation amount mismatch', [
                    'payment_group_id' => $group->id,
                    'payment_number' => $group->payment_number,
                    'expected' => $expected->toDecimal(),
                    'reported' => Money::of($reported)->toDecimal(),
                ]);

                $this->markChecked($group, 'amount_mismatch');

                return;
            }
        }

        DB::transaction(function () use ($group, $incoming, $data, $normalized, $applier): void {
            $locked = PaymentGroup::whereKey($group->getKey())->lockForUpdate()->first();

            if (! $locked) {
                return;
            }

            $decision = PaymentStatusMachine::decide($locked->status, $incoming);

            if (! $decision->shouldApply()) {
                $this->markChecked($locked, $decision->result);

                return;
            }

            $applier->applyWithinLock(
                $locked,
                $incoming,
                $data,
                $normalized['gateway_transaction_id'],
                'Status pembayaran disinkronkan oleh rekonsiliasi gateway.',
            );

            $this->markChecked($locked, PaymentStatusMachine::RESULT_APPLIED);
        }, 3);
    }

    public function failed(?Throwable $exception): void
    {
        PaymentLog::channel('error', 'Payment reconciliation job failed', [
            'payment_group_id' => $this->paymentGroupId,
            'exception' => $exception?->getMessage(),
        ]);
    }

    private function normalisePayload(array $data, PaymentGroup $group): array
    {
        $payload = $data;
        $payload['transaction_status'] = $data['transaction_status'] ?? $data['order_status'] ?? $data['status'] ?? null;
        $payload['order_id'] = $data['order_id'] ?? $data['external_id'] ?? $data['merchant_ref'] ?? $group->payment_number;
        $payload['transaction_id'] = $data['transaction_id'] ?? $data['id'] ?? $data['reference'] ?? $group->gateway_reference;

        return $payload;
    }

    private function markChecked(PaymentGroup $group, string $result): void
    {
        $attributes = [];

        if ($this->hasTrackingColumns()) {
            $attributes['last_reconciled_at'] = now();
            $attributes['reconciliation_attempts'] = (int) $group->getAttribute('reconciliation_attempts') + 1;
            $attributes['reconciliation_note'] = $result;
        }

        if ($attributes !== []) {
            $group->forceFill($attributes)->saveQuietly();
        }

        PaymentLog::channel('info', 'Payment reconciliation probe finished', [
            'payment_group_id' => $group->getKey(),
            'status' => $group->status,
            'result' => $result,
        ]);
    }

    private function hasTrackingColumns(): bool
    {
        if (self::$trackingColumns === null) {
            self::$trackingColumns = Schema::hasTable('payment_groups') && Schema::hasColumn('payment_groups', 'last_reconciled_at');
        }

        return self::$trackingColumns;
    }

    public static function isSettled(string $status): bool
    {
        return in_array($status, [PaymentStatus::Paid->value, PaymentStatus::Refunded->value], true);
    }
}
