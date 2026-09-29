<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Order\OrderStateMachine;
use App\Domain\Payment\PaymentStatusMachine;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Jobs\ExecuteRefund;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentGroup;
use App\Models\Provider;
use App\Models\Refund;
use App\Services\Finance\LedgerService;
use App\Services\Payment\PaymentGatewayService;
use App\Services\Payment\PaymentLog;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RefundWorkflowService
{
    private const REFUNDABLE_ORDER_STATES = [
        'delivered', 'completed', 'return_requested', 'returned', 'refund_pending', 'refunded',
    ];

    private const WALLET_REFERENCE_PREFIX = 'refund:';

    public function request(OrderItem $item, int $customerId, string $reason, float|int|string|null $amount = null): OrderItem
    {
        return DB::transaction(function () use ($item, $customerId, $reason, $amount) {
            $item = OrderItem::with('order.shop.vendor')->lockForUpdate()->findOrFail($item->id);
            $order = $item->order;

            if ((int) $order->customer_id !== $customerId) {
                abort(403);
            }

            if ($order->payment_status !== 'paid' || ! in_array((string) $order->order_status, self::REFUNDABLE_ORDER_STATES, true)) {
                throw ValidationException::withMessages(['refund' => 'Refund hanya dapat diminta untuk pesanan yang sudah dibayar dan diterima.']);
            }

            if ($item->refund_status !== RefundStatus::None->value) {
                throw ValidationException::withMessages(['refund' => 'Permintaan refund untuk item ini sudah ada.']);
            }

            $this->assertRefundableAmount($item, $amount === null ? $item->sub_total : $amount);

            $item->forceFill([
                'refund_status' => RefundStatus::Requested->value,
                'refund_reason' => $reason,
                'refund_requested_at' => now(),
            ])->save();

            $order->statusHistory()->create([
                'status' => 'refund_requested',
                'changed_by' => $customerId,
                'note' => 'Permintaan refund untuk item #'.$item->id,
            ]);

            app(AuditLogger::class)->log('refund.requested', $item, [], [
                'reason' => $reason,
            ], $customerId);

            if (in_array((string) $order->order_status, ['delivered', 'completed'], true)) {
                app(OrderWorkflowService::class)->projectStage($order, OrderStatus::ReturnRequested, $customerId, 'Permintaan retur diterima.');
            } else {
                app(NotificationService::class)->queueOrderEvent($order, 'return_requested');
            }

            return $item->fresh();
        }, 3);
    }

    public function decide(OrderItem $item, int $vendorId, string $decision, ?string $note = null): OrderItem
    {
        return DB::transaction(function () use ($item, $vendorId, $decision, $note) {
            $item = OrderItem::with('order.shop.vendor')->lockForUpdate()->findOrFail($item->id);

            if ($item->order->shop?->vendor_id !== $vendorId) {
                abort(403);
            }

            if ($item->refund_status !== RefundStatus::Requested->value || ! in_array($decision, ['approved', 'rejected'], true)) {
                throw ValidationException::withMessages(['status' => 'Keputusan refund tidak valid atau sudah diproses.']);
            }

            $item->forceFill([
                'refund_status' => $decision,
                'refund_admin_note' => $note,
                'refund_decided_at' => now(),
            ])->save();

            $item->order->statusHistory()->create([
                'status' => "refund_{$decision}",
                'changed_by' => $vendorId,
                'note' => 'Refund item #'.$item->id.($note ? ': '.$note : ''),
            ]);

            app(AuditLogger::class)->log('refund.'.$decision, $item, [
                'refund_status' => RefundStatus::Requested->value,
            ], ['note' => $note], $vendorId);

            $order = $item->order;

            if ($decision !== 'approved') {
                app(NotificationService::class)->queuePaymentEvent($order, 'refund_rejected');

                return $item->fresh();
            }

            app(OrderWorkflowService::class)->projectStage($order, OrderStatus::Returned, $vendorId, 'Barang retur diterima penjual.');

            app(NotificationService::class)->queuePaymentEvent($order, 'refund_approved');

            $this->queueExecution($item, $vendorId);

            return $item->fresh();
        }, 3);
    }

    public function execute(
        OrderItem|Refund $target,
        float|int|string|null $amount = null,
        ?int $actorId = null,
        ?string $idempotencyKey = null,
    ): Refund {
        $item = $target instanceof Refund
            ? $target->orderItem()->firstOrFail()
            : $target;

        $refund = $this->prepare($item, $amount, $actorId, $idempotencyKey);

        if ($refund->status === Refund::STATUS_SUCCEEDED) {
            return $refund;
        }

        $order = $refund->order()->firstOrFail();
        $provider = $this->providerFor($order);

        if (! $provider) {
            $this->fail($refund, 'no_gateway', 'Gateway pembayaran tidak dapat menjalankan refund untuk pesanan ini.');

            throw new RuntimeException('Refund tidak dapat diproses karena gateway pembayaran tidak tersedia.');
        }

        $gatewayResult = app(PaymentGatewayService::class)->refund(
            $provider,
            (string) ($order->paymentGroup?->gateway_reference ?? $order->paymentGroup?->payment_number ?? ''),
            (float) $refund->amount,
            [
                'reason' => (string) ($refund->reason ?? 'Permintaan refund pelanggan'),
                'payment_number' => (string) ($order->paymentGroup?->payment_number ?? ''),
                'invoice_id' => (string) ($order->paymentGroup?->gateway_reference ?? ''),
                'refund_number' => (string) $refund->refund_number,
            ],
        );

        if (! ($gatewayResult['success'] ?? false)) {
            $this->fail($refund, (string) ($gatewayResult['code'] ?? 'gateway_error'), 'Gateway menolak permintaan refund.');

            throw new RuntimeException('Refund gagal diproses oleh gateway dan dapat diulang.');
        }

        return $this->settle($refund, $gatewayResult, $actorId);
    }

    public function retry(Refund $refund, ?int $actorId = null): Refund
    {
        return $this->execute($refund, (float) $refund->amount, $actorId, (string) $refund->idempotency_key);
    }

    /** Katalog alasan retur terstruktur (validasi + analitik). */
    public static function reasonCatalog(): array
    {
        return \App\Models\OrderReturn::reasonLabels();
    }

    public static function normalizeReason(?string $reason): string
    {
        return \App\Models\OrderReturn::normalizeReason($reason);
    }

    /** Analitik alasan retur per toko memakai kolom reason existing. */
    public static function analyticsForShop(int $shopId): array
    {
        return \App\Models\OrderReturn::analyticsForShop($shopId);
    }

    private function prepare(
        OrderItem $item,
        float|int|string|null $amount,
        ?int $actorId,
        ?string $idempotencyKey,
    ): Refund {
        return DB::transaction(function () use ($item, $amount, $actorId, $idempotencyKey) {
            $item = OrderItem::with('order.shop.vendor')->lockForUpdate()->findOrFail($item->id);
            $order = Order::whereKey($item->order_id)->lockForUpdate()->firstOrFail();

            if (! in_array((string) $order->payment_status, ['paid', 'partial', 'refunded'], true)) {
                throw ValidationException::withMessages(['refund' => 'Pesanan ini belum dibayar.']);
            }

            if (! in_array((string) $item->refund_status, [RefundStatus::Approved->value, RefundStatus::Refunded->value], true)) {
                throw ValidationException::withMessages(['refund' => 'Refund belum disetujui untuk item ini.']);
            }

            $value = $this->assertRefundableAmount($item, $amount ?? $this->availableAmount($item));

            $key = $idempotencyKey !== null && $idempotencyKey !== ''
                ? substr($idempotencyKey, 0, 80)
                : 'refund:'.$order->id.':'.$item->id.':'.$value->toDecimal();

            $refund = Refund::where('idempotency_key', $key)->lockForUpdate()->first();

            if ($refund) {
                if ($refund->status === Refund::STATUS_SUCCEEDED) {
                    return $refund;
                }

                $refund->forceFill([
                    'status' => Refund::STATUS_PENDING,
                    'failure_reason' => null,
                    'attempts' => (int) $refund->attempts + 1,
                    'last_attempt_at' => now(),
                ])->save();

                return $refund->fresh();
            }

            return Refund::create([
                'refund_number' => Refund::generateNumber(),
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'payment_group_id' => $order->payment_group_id,
                'provider_id' => $order->paymentGroup?->provider_id,
                'amount' => $value->toDecimal(),
                'currency' => (string) ($order->currency ?: 'IDR'),
                'reason' => $item->refund_reason,
                'status' => Refund::STATUS_PENDING,
                'requested_by_type' => 'system',
                'requested_by' => $actorId,
                'idempotency_key' => $key,
                'attempts' => 1,
                'last_attempt_at' => now(),
            ]);
        }, 3);
    }

    private function settle(Refund $refund, array $gatewayResult, ?int $actorId): Refund
    {
        return DB::transaction(function () use ($refund, $gatewayResult, $actorId) {
            $locked = Refund::whereKey($refund->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === Refund::STATUS_SUCCEEDED) {
                return $locked;
            }

            $item = OrderItem::whereKey($locked->order_item_id)->lockForUpdate()->first();
            $order = Order::whereKey($locked->order_id)->lockForUpdate()->firstOrFail();
            $amount = Money::of($locked->amount);

            $locked->forceFill([
                'status' => Refund::STATUS_SUCCEEDED,
                'gateway_refund_id' => $this->gatewayRefundId($gatewayResult, $locked),
                'gateway_response' => is_array($gatewayResult['raw'] ?? null) ? $gatewayResult['raw'] : null,
                'succeeded_at' => now(),
                'failure_reason' => null,
            ])->save();

            if ($item) {
                $item->forceFill([
                    'refund_status' => RefundStatus::Refunded->value,
                    'refund_amount' => Money::of($item->refund_amount)->add($amount)->toDecimal(),
                    'refund_reference' => $locked->gateway_refund_id ?: $locked->refund_number,
                    'refund_processed_at' => now(),
                ])->save();
            }

            $refundedTotal = Money::of($order->refunded_amount)->add($amount);
            $fullyRefunded = $refundedTotal->compare(Money::of($order->total)) >= 0;

            $order->forceFill([
                'refunded_amount' => $refundedTotal->toDecimal(),
                'payment_status' => $fullyRefunded ? 'refunded' : $order->payment_status,
            ])->save();

            if ($fullyRefunded && $order->payment_group_id !== null) {
                $this->markGroupRefunded($order);
            }

            $this->reverseVendorShare($order, $amount, $locked);
            $this->postLedger($order, $amount, $locked);
            $this->advanceOrder($order, $fullyRefunded, $actorId);

            app(AuditLogger::class)->log('refund.succeeded', $locked, [
                'status' => Refund::STATUS_PENDING,
            ], [
                'status' => Refund::STATUS_SUCCEEDED,
                'amount' => $locked->amount,
                'gateway_refund_id' => $locked->gateway_refund_id,
                'order_id' => $order->id,
            ], $actorId);

            app(NotificationService::class)->queuePaymentEvent($order, 'refund_succeeded');

            return $locked->fresh();
        }, 3);
    }

    private function fail(Refund $refund, string $code, string $message): void
    {
        DB::transaction(function () use ($refund, $code, $message): void {
            $locked = Refund::whereKey($refund->getKey())->lockForUpdate()->first();

            if (! $locked || $locked->status === Refund::STATUS_SUCCEEDED) {
                return;
            }

            $locked->forceFill([
                'status' => Refund::STATUS_FAILED,
                'failure_reason' => $code.': '.$message,
                'last_attempt_at' => now(),
            ])->save();
        }, 3);

        PaymentLog::channel('warning', 'Refund execution failed', [
            'refund_id' => $refund->getKey(),
            'refund_number' => $refund->refund_number,
            'order_id' => $refund->order_id,
            'code' => $code,
        ]);

        app(NotificationService::class)->queuePaymentEvent((int) $refund->order_id, 'refund_failed');
    }

    private function markGroupRefunded(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $group = PaymentGroup::whereKey($order->payment_group_id)->lockForUpdate()->first();

            if (! $group) {
                return;
            }

            $decision = PaymentStatusMachine::decide($group->status, PaymentStatus::Refunded);

            if (! $decision->shouldApply()) {
                return;
            }

            $group->forceFill([
                'status' => PaymentStatus::Refunded->value,
                'refunded_at' => now(),
            ])->save();
        }, 3);
    }

    private function reverseVendorShare(Order $order, Money $amount, Refund $refund): void
    {
        $wallet = $order->shop?->vendor?->wallet;

        if (! $wallet || ! $amount->isPositive()) {
            return;
        }

        $debit = $amount->min(Money::of($wallet->fresh()?->balance ?? 0)->maxZero());

        if (! $debit->isPositive()) {
            PaymentLog::channel('warning', 'Vendor wallet has no balance to reverse for a refund', [
                'order_id' => $order->id,
                'refund_id' => $refund->getKey(),
            ]);

            return;
        }

        try {
            $wallet->debit(
                $debit->toFloat(),
                'Refund #'.$refund->refund_number,
                'refund',
                (int) $refund->getKey(),
                self::WALLET_REFERENCE_PREFIX.$refund->getKey(),
            );
        } catch (DomainException) {
            PaymentLog::channel('warning', 'Vendor wallet reversal was rejected', [
                'order_id' => $order->id,
                'refund_id' => $refund->getKey(),
            ]);
        }
    }

    private function postLedger(Order $order, Money $amount, Refund $refund): void
    {
        if (! $amount->isPositive()) {
            return;
        }

        app(LedgerService::class)->postRefund($order, $amount->toFloat(), (string) $refund->refund_number);
    }

    private function advanceOrder(Order $order, bool $fullyRefunded, ?int $actorId): void
    {
        $workflow = app(OrderWorkflowService::class);

        if ($this->canMove($order, OrderStatus::RefundPending)) {
            $workflow->markRefundPending($order, $actorId, 'Refund sedang diproses ke pembayaran pelanggan.');
            $order->refresh();
        }

        if ($fullyRefunded) {
            if ($this->canMove($order, OrderStatus::Refunded)) {
                $workflow->markRefunded($order, $actorId, 'Seluruh pesanan telah direfund.');
            }

            return;
        }

        app(NotificationService::class)->queueOrderEvent($order, 'refund_pending');
    }
    private function canMove(Order $order, OrderStatus $to): bool
    {
        if ($order->order_status === $to->stored()) {
            return true;
        }

        try {
            OrderStateMachine::assertCanTransition($order->order_status, $to, $order->payment_status);
        } catch (ValidationException) {
            return false;
        }

        return true;
    }

    private function availableAmount(OrderItem $item): Money
    {
        return Money::of($item->sub_total)->subtract(Money::of($item->refund_amount));
    }

    private function assertRefundableAmount(OrderItem $item, Money|float|int|string $amount): Money
    {
        $requested = $amount instanceof Money ? $amount : Money::of($amount);

        if (! $requested->isPositive()) {
            throw ValidationException::withMessages(['refund_amount' => 'Nominal refund harus lebih dari nol.']);
        }

        $available = $this->availableAmount($item);

        if ($requested->compare($available) > 0) {
            throw ValidationException::withMessages([
                'refund_amount' => 'Nominal refund melebihi sisa yang dapat direfund untuk item ini.',
            ]);
        }

        return $requested;
    }

    private function providerFor(Order $order): ?Provider
    {
        $provider = $order->paymentGroup?->provider;

        if (! $provider || ! $provider->is_active) {
            return null;
        }

        return PaymentGatewayService::supportsRefunds((string) $provider->api_format) ? $provider : null;
    }

    private function gatewayRefundId(array $gatewayResult, Refund $refund): string
    {
        $candidate = $gatewayResult['refund_id'] ?? null;

        if (is_scalar($candidate) && (string) $candidate !== '') {
            return substr((string) $candidate, 0, 120);
        }

        return substr((string) $refund->refund_number, 0, 120);
    }

    private function queueExecution(OrderItem $item, ?int $actorId): void
    {
        if (! $this->providerFor($item->order)) {
            return;
        }

        ExecuteRefund::dispatch((int) $item->getKey(), null, $actorId)->afterCommit();
    }
}
