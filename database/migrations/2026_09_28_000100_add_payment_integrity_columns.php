<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Payment integrity columns.
 *
 * `orders.idempotency_key` becomes unique so a double-clicked checkout can never
 * create two orders, webhook callbacks keep the amount they reported next to the
 * amount that was expected, and refunds record how often the gateway was called.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addUniqueIdempotencyKey();
        $this->addCallbackAmountColumns();
        $this->addRefundAttemptColumns();
        $this->addPaymentGroupIndexes();
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_groups')) {
            Schema::table('payment_groups', function (Blueprint $table) {
                if ($this->indexExists('payment_groups', 'payment_groups_status_expired_index')) {
                    $table->dropIndex('payment_groups_status_expired_index');
                }
                if (Schema::hasColumn('payment_groups', 'refunded_at')) {
                    $table->dropColumn('refunded_at');
                }
            });
        }

        if (Schema::hasTable('refunds')) {
            Schema::table('refunds', function (Blueprint $table) {
                if ($this->indexExists('refunds', 'refunds_status_created_index')) {
                    $table->dropIndex('refunds_status_created_index');
                }
                foreach (['attempts', 'last_attempt_at'] as $column) {
                    if (Schema::hasColumn('refunds', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('payment_webhook_callbacks')) {
            Schema::table('payment_webhook_callbacks', function (Blueprint $table) {
                if ($this->indexExists('payment_webhook_callbacks', 'pwc_result_received_index')) {
                    $table->dropIndex('pwc_result_received_index');
                }
                foreach (['reported_amount', 'expected_amount'] as $column) {
                    if (Schema::hasColumn('payment_webhook_callbacks', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('orders') && $this->indexExists('orders', 'orders_idempotency_key_unique')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropUnique('orders_idempotency_key_unique'));
        }
    }

    private function addUniqueIdempotencyKey(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'idempotency_key')) {
            return;
        }

        if ($this->indexExists('orders', 'orders_idempotency_key_unique')) {
            return;
        }

        $duplicates = DB::table('orders')
            ->whereNotNull('idempotency_key')
            ->select('idempotency_key')
            ->groupBy('idempotency_key')
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->exists();

        if ($duplicates) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->unique('idempotency_key', 'orders_idempotency_key_unique');
        });
    }

    private function addCallbackAmountColumns(): void
    {
        if (! Schema::hasTable('payment_webhook_callbacks')) {
            return;
        }

        $missing = array_values(array_filter(
            ['reported_amount', 'expected_amount'],
            static fn (string $column): bool => ! Schema::hasColumn('payment_webhook_callbacks', $column),
        ));

        if ($missing !== []) {
            Schema::table('payment_webhook_callbacks', function (Blueprint $table) use ($missing) {
                foreach ($missing as $column) {
                    $table->decimal($column, 18, 2)->nullable();
                }
            });
        }

        if (! $this->indexExists('payment_webhook_callbacks', 'pwc_result_received_index')) {
            Schema::table('payment_webhook_callbacks', function (Blueprint $table) {
                $table->index(['processing_result', 'received_at'], 'pwc_result_received_index');
            });
        }
    }

    private function addRefundAttemptColumns(): void
    {
        if (! Schema::hasTable('refunds')) {
            return;
        }

        $missing = array_values(array_filter(
            ['attempts', 'last_attempt_at'],
            static fn (string $column): bool => ! Schema::hasColumn('refunds', $column),
        ));

        if ($missing !== []) {
            Schema::table('refunds', function (Blueprint $table) use ($missing) {
                if (in_array('attempts', $missing, true)) {
                    $table->unsignedInteger('attempts')->default(0);
                }
                if (in_array('last_attempt_at', $missing, true)) {
                    $table->timestamp('last_attempt_at')->nullable();
                }
            });
        }

        if (! $this->indexExists('refunds', 'refunds_status_created_index')) {
            Schema::table('refunds', function (Blueprint $table) {
                $table->index(['status', 'created_at'], 'refunds_status_created_index');
            });
        }
    }

    private function addPaymentGroupIndexes(): void
    {
        if (! Schema::hasTable('payment_groups')) {
            return;
        }

        if (! Schema::hasColumn('payment_groups', 'refunded_at')) {
            Schema::table('payment_groups', function (Blueprint $table) {
                $table->timestamp('refunded_at')->nullable();
            });
        }

        if (! $this->indexExists('payment_groups', 'payment_groups_status_expired_index')) {
            Schema::table('payment_groups', function (Blueprint $table) {
                $table->index(['status', 'expired_at'], 'payment_groups_status_expired_index');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            foreach (Schema::getIndexes($table) as $definition) {
                if (($definition['name'] ?? null) === $index) {
                    return true;
                }
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }
};
