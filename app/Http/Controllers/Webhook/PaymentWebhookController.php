<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\PaymentGroup;
use App\Models\PaymentWebhookCallback;
use App\Models\Provider;
use App\Models\Transaction;
use App\Services\AuditLogger;
use App\Services\Payment\PaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, Provider $provider, PaymentGatewayService $payments): JsonResponse
    {
        abort_unless($provider->type === 'payment' && $provider->is_active, 404);
        $headers = collect($request->headers->all())->map(fn (array $values) => (string) ($values[0] ?? ''))->all();
        $callback = ['body' => $request->all(), 'headers' => $headers, 'raw' => $request->getContent()];

        if (! $payments->verifyCallback($provider, $callback)) {
            Log::warning('Rejected payment webhook signature', ['provider_id' => $provider->id, 'ip' => $request->ip()]);

            return response()->json(['success' => false, 'message' => 'Invalid callback signature.'], 401);
        }

        $normalized = $payments->normalizeCallback($provider, $callback);
        if (! $normalized['external_id'] || ! $normalized['status']) {
            return response()->json(['success' => false, 'message' => 'Unsupported callback payload.'], 422);
        }

        $result = DB::transaction(function () use ($provider, $callback, $normalized) {
            $group = PaymentGroup::where('payment_number', $normalized['external_id'])
                ->where('provider_id', $provider->id)->lockForUpdate()->first();

            $log = PaymentWebhookCallback::firstOrCreate(
                ['provider_id' => $provider->id, 'gateway_transaction_id' => $normalized['gateway_transaction_id']],
                [
                    'payment_group_id' => $group?->id, 'external_id' => $normalized['external_id'], 'status' => $normalized['status'],
                    'payload' => $callback['body'], 'headers' => $callback['headers'], 'received_at' => now(), 'processing_result' => 'received',
                ],
            );
            if (! $group) {
                $log->update(['processing_result' => 'unknown_reference']);

                return ['code' => 404, 'message' => 'Payment reference not found.'];
            }
            if ($log->processed_at) {
                return ['code' => 200, 'message' => 'Already processed.'];
            }

            $group->update([
                'status' => $normalized['status'], 'gateway_reference' => $normalized['gateway_transaction_id'] ?: $group->gateway_reference,
                'gateway_response' => $callback['body'], 'paid_at' => $normalized['status'] === 'paid' ? now() : $group->paid_at,
                'expired_at' => $normalized['status'] === 'expired' ? now() : $group->expired_at,
            ]);
            $orders = $group->orders()->lockForUpdate()->get();
            foreach ($orders as $order) {
                $paymentStatus = match ($normalized['status']) {
                    'paid' => 'paid', 'refunded' => 'refunded', default => 'unpaid',
                };
                $order->update(['payment_status' => $paymentStatus]);
                Transaction::where('order_id', $order->id)->where('payment_group_id', $group->id)->update([
                    'status' => $normalized['status'] === 'paid' ? 'success' : $normalized['status'],
                    'paid_at' => $normalized['status'] === 'paid' ? now() : null,
                    'payment_response' => $callback['body'],
                ]);
                $order->statusHistory()->create([
                    'status' => "payment_{$normalized['status']}", 'note' => 'Status pembayaran diperbarui oleh callback gateway.',
                ]);
            }
            $log->update(['payment_group_id' => $group->id, 'status' => $normalized['status'], 'processed_at' => now(), 'processing_result' => 'processed']);
            app(AuditLogger::class)->log('payment.callback_processed', $group, ['status' => $group->getOriginal('status')], [
                'status' => $normalized['status'], 'provider_id' => $provider->id, 'gateway_transaction_id' => $normalized['gateway_transaction_id'],
            ]);

            return ['code' => 200, 'message' => 'Processed.'];
        });

        return response()->json(['success' => $result['code'] < 300, 'message' => $result['message']], $result['code']);
    }
}
