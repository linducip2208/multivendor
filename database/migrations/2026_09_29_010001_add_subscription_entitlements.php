<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscription entitlements.
 *
 * A plan carries the hard caps a vendor may consume (products, staff, storage,
 * transactions) plus its commission tier. A subscription carries the billing
 * window, the cancellation state and the grace period applied when a term
 * lapses. Every column is additive and guarded so the migration is a no-op on a
 * database that already carries an equivalent definition.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addColumns('subscription_plans', [
            ['max_products', 'integer', ['unsigned' => true], ['default' => 0]],
            ['max_staff', 'integer', ['unsigned' => true], ['default' => 0]],
            ['max_storage_mb', 'integer', ['unsigned' => true], ['default' => 0]],
            ['max_monthly_transactions', 'integer', ['unsigned' => true], ['default' => 0]],
            ['max_shop_limit', 'integer', ['unsigned' => true], ['default' => 0]],
            ['commission_value', 'decimal', ['total' => 8, 'places' => 2], ['default' => 0]],
            ['commission_type', 'string', ['length' => 20], ['default' => 'percentage']],
            ['commission_tier', 'string', ['length' => 60], ['nullable' => true]],
            ['grace_days', 'unsignedSmallInteger', [], ['default' => 0]],
            ['trial_days', 'unsignedSmallInteger', [], ['default' => 0]],
            ['grace_allowed', 'boolean', [], ['default' => false]],
            ['sort_order', 'integer', [], ['default' => 0]],
        ]);

        $this->addColumns('vendor_subscriptions', [
            ['grace_ends_at', 'timestamp', [], ['nullable' => true]],
            ['trial_ends_at', 'timestamp', [], ['nullable' => true]],
            ['cancelled_at', 'timestamp', [], ['nullable' => true]],
            ['cancel_reason', 'string', ['length' => 255], ['nullable' => true]],
            ['auto_renew', 'boolean', [], ['default' => true]],
            ['renewed_from_id', 'integer', ['unsigned' => true], ['nullable' => true]],
            ['downgrade_at_period_end', 'boolean', [], ['default' => false]],
            ['notes', 'text', [], ['nullable' => true]],
        ]);

        $this->addIndex('subscription_plans', ['is_active', 'sort_order'], 'subscription_plans_active_sort_idx');
        $this->addIndex('vendor_subscriptions', ['shop_id', 'ends_at'], 'vendor_subscriptions_shop_ends_idx');
    }

    public function down(): void
    {
    }

    private function addColumns(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as [$name, $type, $args, $modifiers]) {
            if (Schema::hasColumn($table, $name)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($name, $type, $args, $modifiers) {
                $t->addColumn($type, $name, $args + $modifiers);
            });
        }
    }

    private function addIndex(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        try {
            Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
        } catch (\Throwable) {
        }
    }
};
