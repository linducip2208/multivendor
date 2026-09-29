<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Order\OrderStateMachine;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\PaymentGroup;
use App\Services\OrderService;
use App\Services\Payment\PaymentLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ExpireStalePayments implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $batchSize = 100) {}

    public function handle(): void
    {
        $candidates = PaymentGroup::query()
            ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Unpaid->value])
            ->where(function ($query) {
                $query->whereNotNull('expired_at')->where('expired_at', '<=', now());
            })
            ->orderBy('id')
            ->limit(max(1, $this->batchSize))
            ->pluck('id');

        if ($candidates->isEmpty()) {
            return;
        }

        $expired = 0;

        foreach ($candidates as $id) {
            if ($this->expireGroup((int) $id)) {
                $expired++;
            }
        }

        PaymentLog::channel('info', 'Expired stale payment groups', [
            'scanned' => $candidates->count(),
            'expired' => $expired,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        PaymentLog::channel('error', 'Payment expiry job failed', [
            'exception' => $exception?->getMessage(),
        ]);
    }

    private function expireGroup(int $groupId): bool
    {
        return DB::transaction(function () use ($groupId): bool {
            $group = PaymentGroup::whereKey($groupId)->lockForUpdate()->first();

            if (! $group) {
                return false;
            }

            if (! in_array((string) $group->status, [PaymentStatus::Pending->value, PaymentStatus::Unpaid->value], true)) {
                return false;
            }

            if ($group->expired_at === null || $group->expired_at->isFuture()) {
                return false;
            }

            $group->forceFill([
                'status' => PaymentStatus::Expired->value,
                'expired_at' => $group->expired_at ?? now(),
            ])->save();

            $orders = $group->orders()->lockForUpdate()->get();

            foreach ($orders as $order) {
                try {
                    OrderStateMachine::assertCanTransition($order->order_status, OrderStatus::Cancelled, $order->payment_status);
                } catch (ValidationException) {
                    app(OrderService::class)->restoreStock($order);

                    continue;
                }

                $order->forceFill([
                    'order_status' => OrderStatus::Cancelled->stored(),
                    'canceled_at' => now(),
                    'payment_status' => PaymentStatus::Unpaid->value,
                    'cancel_reason' => 'Kedaluwarsa pembayaran gateway.',
                ])->save();

                $order->statusHistory()->create([
                    'status' => OrderStatus::Cancelled->stored(),
                    'note' => 'Pesanan dibatalkan otomatis karena pembayaran kedaluwarsa.',
                ]);

                app(OrderService::class)->restoreStock($order);
            }

            return true;
        }, 3);
    }
}
