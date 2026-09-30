<?php

declare(strict_types=1);

namespace App\Payments;

use App\Models\LedgerEntry;
use App\Models\PaymentGroup;
use App\Models\PaymentWebhookCallback;
use App\Models\Refund;
use App\Support\Money;
use Illuminate\Support\Facades\Schema;

/**
 * Laporan rekonsiliasi: missing/duplicate/wrong-amount/wrong-currency/
 * refund/settlement mismatch + data dashboard (tanpa view).
 *
 * Murni baca (read-only): tidak mengubah order/ledger.
 */
final class ReconciliationReport
{
    /**
     * @return array{missing:array,duplicate:array,amount_mismatch:array,currency_mismatch:array,refund_mismatch:array,settlement_mismatch:array,summary:array}
     */
    public static function analyze(?int $limit = 200): array
    {
        $limit = max(1, min(1000, $limit ?? 200));
        $missing = [];
        $duplicate = [];
        $amountMismatch = [];
        $currencyMismatch = [];
        $refundMismatch = [];
        $settlementMismatch = [];

        $groups = PaymentGroup::query()->orderByDesc('id')->limit($limit)->get();

        foreach ($groups as $group) {
            $callbacks = PaymentWebhookCallback::where('payment_group_id', $group->id)->get();
            $processed = $callbacks->where('processing_result', 'applied');

            // 1) Missing: grup paid tanpa callback applied.
            if (strtolower((string) $group->status) === 'paid' && $processed->isEmpty()) {
                $missing[] = self::row($group, 'missing_callback', 'Grup paid tanpa callback applied.');
            }

            // 2) Duplicate: >1 callback applied dengan gateway_transaction_id sama.
            $seen = [];
            foreach ($callbacks->whereNotNull('gateway_transaction_id') as $cb) {
                $key = $group->id.'|'.(string) $cb->gateway_transaction_id;
                if (isset($seen[$key]) && $cb->processing_result === 'applied') {
                    $duplicate[] = self::row($group, 'duplicate_callback', 'Callback ganda applied: '.$cb->gateway_transaction_id);
                    break;
                }
                if ($cb->processing_result === 'applied') {
                    $seen[$key] = true;
                }
            }

            // 3) Wrong amount: callback amount_mismatch.
            foreach ($callbacks->where('processing_result', 'amount_mismatch') as $cb) {
                $amountMismatch[] = self::row($group, 'wrong_amount', 'Selisih nominal.', [
                    'expected' => $cb->expected_amount, 'reported' => $cb->reported_amount,
                ]);
            }

            // 4) Wrong currency: payload/headers menyebut currency != IDR padahal grup IDR.
            foreach ($callbacks as $cb) {
                $payload = is_array($cb->payload) ? $cb->payload : [];
                $reportedCurrency = strtoupper((string) ($payload['currency'] ?? $payload['gross_currency'] ?? ''));
                if ($reportedCurrency !== '' && $reportedCurrency !== 'IDR') {
                    $currencyMismatch[] = self::row($group, 'wrong_currency', 'Currency callback '.$reportedCurrency.' != IDR.');
                    break;
                }
            }

            // 5) Refund mismatch: refund tercatat tanpa grup paid/refunded.
            $refunds = Refund::where('payment_group_id', $group->id)->get();
            if ($refunds->isEmpty()) {
                $orderIds = $group->orders()->pluck('orders.id')->all();
                $refunds = $orderIds === [] ? collect() : Refund::whereIn('order_id', $orderIds)->get();
            }
            foreach ($refunds as $refund) {
                $status = strtolower((string) $group->status);
                if (! in_array($status, ['paid', 'refunded', 'partially_refunded'], true)) {
                    $refundMismatch[] = self::row($group, 'refund_without_payment', 'Refund tanpa pembayaran paid.', [
                        'refund_id' => $refund->id,
                    ]);
                    break;
                }
            }

            // 6) Settlement mismatch: grup paid tapi jurnal ledger tak seimbang per grup.
            if (strtolower((string) $group->status) === 'paid' && Schema::hasTable('ledger_entries')) {
                $entries = LedgerEntry::where('reference_type', PaymentGroup::class)
                    ->where('reference_id', $group->id)->get();
                if ($entries->isEmpty()) {
                    $entries = LedgerEntry::whereIn('order_id', $group->orders()->pluck('orders.id'))->get();
                }
                if ($entries->isNotEmpty()) {
                    $debit = Money::sum($entries->where('direction', 'debit')->map(fn ($e) => Money::of($e->amount))->all());
                    $credit = Money::sum($entries->where('direction', 'credit')->map(fn ($e) => Money::of($e->amount))->all());
                    if ($debit->minor !== $credit->minor) {
                        $settlementMismatch[] = self::row($group, 'settlement_unbalanced', 'Jurnal grup tak seimbang.', [
                            'debit' => $debit->toDecimal(), 'credit' => $credit->toDecimal(),
                        ]);
                    }
                }
            }
        }

        $summary = [
            'analyzed_groups' => $groups->count(),
            'missing' => count($missing),
            'duplicate' => count($duplicate),
            'amount_mismatch' => count($amountMismatch),
            'currency_mismatch' => count($currencyMismatch),
            'refund_mismatch' => count($refundMismatch),
            'settlement_mismatch' => count($settlementMismatch),
            'generated_at' => now()->toIso8601String(),
        ];

        return [
            'missing' => $missing,
            'duplicate' => $duplicate,
            'amount_mismatch' => $amountMismatch,
            'currency_mismatch' => $currencyMismatch,
            'refund_mismatch' => $refundMismatch,
            'settlement_mismatch' => $settlementMismatch,
            'summary' => $summary,
        ];
    }

    /** Data ringkas untuk dashboard (tanpa view): angka + 20 temuan terbaru. */
    public static function dashboard(): array
    {
        $full = self::analyze(200);
        $recent = array_slice(array_merge(
            $full['missing'], $full['duplicate'], $full['amount_mismatch'],
            $full['currency_mismatch'], $full['refund_mismatch'], $full['settlement_mismatch']
        ), 0, 20);

        return ['summary' => $full['summary'], 'recent' => $recent];
    }

    private static function row(PaymentGroup $group, string $kind, string $message, array $extra = []): array
    {
        return array_merge([
            'payment_group_id' => (int) $group->id,
            'payment_number' => $group->payment_number,
            'kind' => $kind,
            'status' => $group->status,
            'grand_total' => $group->grand_total,
            'message' => $message,
        ], $extra);
    }
}
