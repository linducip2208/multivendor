<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Jobs\ReconcilePaymentGroup;
use App\Models\PaymentGroup;
use App\Models\SystemSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile {--minutes= : Override how old a pending group must be} {--limit=100 : Maximum groups to probe}';

    protected $description = 'Poll payment gateways for pending payment groups that may have settled out of band';

    public function handle(): int
    {
        $minutes = (int) ($this->option('minutes') ?: SystemSetting::get('payment_reconcile_after_minutes', '15'));
        $minutes = max(1, $minutes);
        $limit = max(1, (int) $this->option('limit'));
        $threshold = now()->subMinutes($minutes);

        $groups = PaymentGroup::query()
            ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Unpaid->value])
            ->whereNotNull('gateway_reference')
            ->where('created_at', '<=', $threshold)
            ->when($this->hasReconcileColumns(), fn ($query) => $query->where(function ($inner) use ($threshold) {
                $inner->whereNull('last_reconciled_at')
                    ->orWhere('last_reconciled_at', '<=', $threshold);
            }))
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        if ($groups->isEmpty()) {
            $this->info('No pending payment groups require reconciliation.');

            return self::SUCCESS;
        }

        foreach ($groups as $id) {
            ReconcilePaymentGroup::dispatch((int) $id);
        }

        $this->info(sprintf('Queued %d payment group(s) for reconciliation.', $groups->count()));

        return self::SUCCESS;
    }

    private function hasReconcileColumns(): bool
    {
        return Schema::hasTable('payment_groups') && Schema::hasColumn('payment_groups', 'last_reconciled_at');
    }
}
