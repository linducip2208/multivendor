<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhook;

use App\Domain\Payment\PaymentStatusMachine;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\PaymentGroup;
use App\Models\PaymentWebhookCallback;
use App\Models\Provider;
use App\Services\AuditLogger;
use App\Services\Payment\PaymentGatewayService;
use App\Services\Payment\PaymentGroupApplier;
use App\Services\Payment\PaymentLog;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentWebhookController extends Controller
{
    private const DEDUPE_SEPARATOR = '#';

    public function __construct(
        private readonly PaymentGroupApplier $applier,
    ) {}

    public function __invoke(Request $request, Provider $provider, PaymentGatewayService $payments): JsonResponse
    {
        abort_unless($provider->type === 'payment' && $provider->is_active, 404);

        $headers = collect($request->headers->all())
            ->map(fn (array $values) => (string) ($values[0] ?? ''))
            ->all();

        $callback = [
            'body' => $request->all(),
            'headers' => $headers,
            'raw' => $request->getContent(),
        ];

        if (! $payments->verifyCallback($provider, $callback)) {
            PaymentLog::channel('warning', 'Rejected payment webhook signature', [
                'provider_id' => $provider->id,
                'ip' => $request->ip(),
            ]);

            return response()->json(['success' => false, 'message' => 'Invalid callback signature.'], 401);
        }

        $normalized = $payments->normalizeCallback($provider, $callback);
        $incoming = PaymentStatusMachine::resolve($normalized['status']);

        if (! $normalized['external_id'] || $incoming === null) {
            PaymentLog::channel('warning', 'Rejected unrecognised payment callback payload', [
                'provider_id' => $provider->id,
                'body' => $callback['body'],
            ]);

            return response()->json(['success' => false, 'message' => 'Unsupported callback payload.'], 422);
        }

        $result = $this->run($provider, $payments, $callback, $normalized, $incoming);

        return response()->json(
            ['success' => $result['code'] < 300, 'message' => $result['message']],
            $result['code'],
        );
    }

    private function run(
        Provider $provider,
        PaymentGatewayService $payments,
        array $callback,
        array $normalized,
        PaymentStatus $incoming,
    ): array {
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                return DB::transaction(
                    fn (): array => $this->handle($provider, $payments, $callback, $normalized, $incoming),
                    3,
                );
            } catch (QueryException $e) {
                PaymentLog::channel('error', 'Payment webhook transaction failed', [
                    'provider_id' => $provider->id,
                    'attempt' => $attempt,
                    'exception' => $e::class,
                ]);

                usleep(50_000);
            }
        }

        return ['code' => 500, 'message' => 'Callback could not be processed.'];
    }

    private function handle(
        Provider $provider,
        PaymentGatewayService $payments,
        array $callback,
        array $normalized,
        PaymentStatus $incoming,
    ): array {
        $group = PaymentGroup::where('payment_number', $normalized['external_id'])
            ->where('provider_id', $provider->id)
            ->lockForUpdate()
            ->first();

        $dedupeKey = $this->dedupeKey($normalized, $incoming);

        $log = PaymentWebhookCallback::where('provider_id', $provider->id)
            ->where('gateway_transaction_id', $dedupeKey)
            ->lockForUpdate()
            ->first();

        $isNew = $log === null;

        if ($log === null) {
            $log = PaymentWebhookCallback::create([
                'provider_id' => $provider->id,
                'payment_group_id' => $group?->id,
                'gateway_transaction_id' => $dedupeKey,
                'external_id' => $normalized['external_id'],
                'status' => $incoming->value,
                'payload' => $callback['body'],
                'headers' => $callback['headers'],
                'received_at' => now(),
                'processing_result' => 'received',
            ]);
        }

        if (! $group) {
            if ($isNew) {
                $log->forceFill(['processing_result' => 'unknown_reference'])->save();
            }

            PaymentLog::channel('warning', 'Payment callback referenced an unknown payment number', [
                'provider_id' => $provider->id,
                'external_id' => $normalized['external_id'],
            ]);

            return ['code' => 404, 'message' => 'Payment reference not found.'];
        }

        if (! $isNew && $log->processed_at !== null) {
            return ['code' => 200, 'message' => 'Already processed.'];
        }

        $expected = Money::of($group->grand_total);
        $reported = $payments->reportedAmount($provider, $callback);
        $tolerance = Money::of(PaymentGatewayService::amountTolerance());

        $log->forceFill([
            'payment_group_id' => $group->id,
            'expected_amount' => $expected->toDecimal(),
            'reported_amount' => $reported === null ? null : Money::of($reported)->toDecimal(),
        ])->save();

        if ($reported !== null) {
            $delta = Money::of($reported)->subtract($expected);

            if (abs($delta->minor) > $tolerance->minor) {
                $log->forceFill([
                    'processing_result' => 'amount_mismatch',
                    'processed_at' => now(),
                ])->save();

                PaymentLog::channel('critical', 'Payment callback amount mismatch', [
                    'provider_id' => $provider->id,
                    'payment_group_id' => $group->id,
                    'payment_number' => $group->payment_number,
                    'expected' => $expected->toDecimal(),
                    'reported' => Money::of($reported)->toDecimal(),
                    'tolerance' => $tolerance->toDecimal(),
                    'status' => $incoming->value,
                ]);

                app(AuditLogger::class)->log('payment.amount_mismatch', $group, [
                    'status' => $group->status,
                    'grand_total' => $group->grand_total,
                ], [
                    'status' => $incoming->value,
                    'reported_amount' => Money::of($reported)->toDecimal(),
                    'tolerance' => $tolerance->toDecimal(),
                    'provider_id' => $provider->id,
                ]);

                return ['code' => 200, 'message' => 'Amount mismatch detected.'];
            }
        }

        $decision = PaymentStatusMachine::decide($group->status, $incoming);

        if (! $decision->shouldApply()) {
            $log->forceFill([
                'status' => $incoming->value,
                'processing_result' => $decision->result,
                'processed_at' => now(),
            ])->save();

            PaymentLog::channel('info', 'Ignored non-advancing payment callback', [
                'provider_id' => $provider->id,
                'payment_group_id' => $group->id,
                'current_status' => $group->status,
                'incoming_status' => $incoming->value,
                'result' => $decision->result,
            ]);

            return ['code' => 200, 'message' => 'Ignored.'];
        }

        $this->applier->applyWithinLock(
            $group,
            $incoming,
            $callback['body'],
            $normalized['gateway_transaction_id'],
        );

        $log->forceFill([
            'status' => $incoming->value,
            'processed_at' => now(),
            'processing_result' => PaymentStatusMachine::RESULT_APPLIED,
        ])->save();

        app(AuditLogger::class)->log('payment.callback_processed', $group, [
            'status' => $group->getOriginal('status'),
        ], [
            'status' => $incoming->value,
            'provider_id' => $provider->id,
            'gateway_transaction_id' => $normalized['gateway_transaction_id'],
        ]);

        return ['code' => 200, 'message' => 'Processed.'];
    }

    private function dedupeKey(array $normalized, PaymentStatus $incoming): string
    {
        $gatewayId = trim((string) ($normalized['gateway_transaction_id'] ?? ''));

        if ($gatewayId === '') {
            $gatewayId = 'synthetic:'.(string) $normalized['external_id'];
        }

        return substr($gatewayId.self::DEDUPE_SEPARATOR.$incoming->value, 0, 255);
    }
}
