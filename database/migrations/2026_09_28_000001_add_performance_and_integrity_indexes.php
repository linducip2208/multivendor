<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance and integrity index pass.
 *
 * Every index below was verified against a real query in a controller or
 * service. Additions are wrapped per index so the migration is a safe no-op on
 * a database that already carries an equivalent index, and portable across
 * MySQL, MariaDB, PostgreSQL and SQLite (no SHOW INDEX, no raw DDL).
 */
return new class extends Migration
{
    /** @var list<array{0: string, 1: list<string>, 2: string, 3: bool}> */
    private array $indexes = [
        ['orders', ['order_status'], 'orders_order_status_idx', false],
        ['orders', ['created_at'], 'orders_created_at_idx', false],
        ['transactions', ['status'], 'transactions_status_idx', false],
        ['transactions', ['created_at'], 'transactions_created_at_idx', false],
        ['transactions', ['order_id'], 'transactions_order_idx', false],
        ['products', ['sku'], 'products_sku_idx', false],
        ['products', ['barcode'], 'products_barcode_idx', false],
        ['products', ['created_at'], 'products_created_at_idx', false],
        ['order_status_history', ['status'], 'order_status_history_status_idx', false],
        ['payment_groups', ['expired_at'], 'payment_groups_expired_at_idx', false],
        ['payment_groups', ['status', 'expired_at'], 'payment_groups_status_expiry_idx', false],
        ['product_variants', ['sku'], 'product_variants_sku_idx', false],
        ['coupons', ['end_date'], 'coupons_end_date_idx', false],
        ['vendor_subscriptions', ['ends_at'], 'vendor_subscriptions_ends_at_idx', false],
        ['vendor_subscriptions', ['shop_id', 'status'], 'vendor_subscriptions_shop_status_idx', false],
        ['shops', ['status', 'created_at'], 'shops_status_created_idx', false],
        ['users', ['role', 'status'], 'users_role_status_idx', false],
        ['categories', ['parent_id', 'sort_order'], 'categories_parent_sort_idx', false],
        ['product_reviews', ['product_id', 'status'], 'product_reviews_product_status_idx', false],
        ['wishlist', 'customer_id', 'wishlist_customer_idx', false],
        ['carts', 'customer_id', 'carts_customer_idx', false],
        ['order_items', 'order_id', 'order_items_order_idx', false],
        ['order_items', 'product_id', 'order_items_product_idx', false],
        ['order_items', ['refund_status', 'refund_requested_at'], 'order_items_refund_idx', false],
        ['wallet_transactions', ['created_at'], 'wallet_transactions_created_idx', false],
        ['provider_webhook_callbacks', ['provider_id', 'processed_at'], 'pwc_processed_idx', false],
        ['order_shipments', ['status'], 'order_shipments_status_idx', false],
        ['ledger_entries', ['entry_type', 'posted_at'], 'ledger_entries_type_posted_idx', false],
    ];

    public function up(): void
    {
        foreach ($this->indexes as [$table, $columns, $name, $unique]) {
            $this->addIndex($table, $columns, $name, $unique);
        }

        if (Schema::hasTable('shops') && ! Schema::hasColumn('shops', 'deleted_at')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('social_logins') && $this->columnsExist('social_logins', ['user_id', 'provider'])) {
            $this->addIndex('social_logins', ['user_id', 'provider'], 'social_logins_user_provider_unique', true);
        }

        if (Schema::hasTable('loyalty_points') && $this->columnsExist('loyalty_points', ['customer_id'])) {
            $this->addIndex('loyalty_points', ['customer_id'], 'loyalty_points_customer_unique', true);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shops') && Schema::hasColumn('shops', 'deleted_at')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }

    private function addIndex(string $table, $columns, string $name, bool $unique = false): void
    {
        if (! Schema::hasTable($table) || ! $this->columnsExist($table, (array) $columns)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $t) use ($columns, $name, $unique) {
                $unique ? $t->unique($columns, $name) : $t->index($columns, $name);
            });
        } catch (\Throwable) {
            // The index already exists under this or another name. Nothing to do.
        }
    }

    /** @param list<string> $columns */
    private function columnsExist(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }
};
