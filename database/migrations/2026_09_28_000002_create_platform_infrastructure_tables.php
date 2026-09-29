<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commerce platform infrastructure: financial ledger, warehouses/stock,
 * webhooks, notifications, homepage sections, PSEO quality, tenants, RBAC,
 * conversations, POS, customer segmentation and subscriptions.
 *
 * Written to be safe on MySQL, MariaDB, PostgreSQL and SQLite: no raw DDL, no
 * engine hints, no post-hoc column drops.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* ---------------------------------------------------------------
         | 1. Double-entry financial ledger
         | ------------------------------------------------------------ */
        if (! Schema::hasTable('ledger_accounts')) {
            Schema::create('ledger_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('code', 60)->unique();
                $table->string('name', 160);
                $table->string('type', 30); // asset|liability|equity|revenue|expense
                $table->string('normal_balance', 10)->default('debit');
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->boolean('is_system')->default(false);
                $table->timestamps();

                $table->index('type');
            });
        }

        if (! Schema::hasTable('ledger_entries')) {
            Schema::create('ledger_entries', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('transaction_group', 64)->index();
                $table->unsignedBigInteger('account_id');
                $table->string('direction', 6); // debit|credit
                $table->decimal('amount', 18, 2);
                $table->string('currency', 8)->default('IDR');
                $table->string('entry_type', 40)->index();
                $table->string('reference_type', 60)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->unsignedBigInteger('order_id')->nullable();
                $table->unsignedBigInteger('shop_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('memo', 255)->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('posted_at')->useCurrent();
                $table->timestamps();

                $table->foreign('account_id')->references('id')->on('ledger_accounts')->cascadeOnDelete();
                $table->index(['reference_type', 'reference_id']);
                $table->index(['account_id', 'posted_at']);
                $table->index(['order_id', 'entry_type']);
                $table->index(['shop_id', 'posted_at']);
            });
        }

        /* ---------------------------------------------------------------
         | 2. Warehouses + stock movements + reservations
         | ------------------------------------------------------------ */
        if (! Schema::hasTable('warehouses')) {
            Schema::create('warehouses', function (Blueprint $table) {
                $table->id();
                $table->string('name', 160);
                $table->string('code', 40)->unique();
                $table->string('address', 255)->nullable();
                $table->string('city', 100)->nullable();
                $table->string('province', 100)->nullable();
                $table->string('postal_code', 12)->nullable();
                $table->string('country', 2)->default('ID');
                $table->string('phone', 30)->nullable();
                $table->string('manager_name', 160)->nullable();
                $table->unsignedBigInteger('shop_id')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['shop_id', 'is_active']);
                $table->index('city');
            });
        }

        if (! Schema::hasTable('product_stocks')) {
            Schema::create('product_stocks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('warehouse_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('product_variant_id')->nullable();
                $table->integer('on_hand')->default(0);
                $table->integer('reserved')->default(0);
                $table->integer('incoming')->default(0);
                $table->integer('safety_stock')->default(0);
                $table->timestamp('last_counted_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['warehouse_id', 'product_id', 'product_variant_id'],
                    'product_stocks_location_unique'
                );
                $table->index('product_id');
                $table->index(['product_variant_id']);
            });
        }

        if (! Schema::hasTable('stock_movements')) {
            Schema::create('stock_movements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('warehouse_id')->nullable();
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('product_variant_id')->nullable();
                $table->string('type', 30)->index(); // in|out|adjustment|transfer|reservation|release|return|opname
                $table->integer('quantity');
                $table->integer('balance_after')->nullable();
                $table->string('reference_type', 60)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('note', 255)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['product_id', 'created_at']);
                $table->index(['reference_type', 'reference_id']);
                $table->index(['warehouse_id', 'type']);
            });
        }

        if (! Schema::hasTable('stock_transfers')) {
            Schema::create('stock_transfers', function (Blueprint $table) {
                $table->id();
                $table->string('transfer_number', 40)->unique();
                $table->unsignedBigInteger('from_warehouse_id');
                $table->unsignedBigInteger('to_warehouse_id');
                $table->string('status', 20)->default('draft'); // draft|in_transit|received|cancelled
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('shipped_at')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamps();

                $table->index('status');
            });
        }

        if (! Schema::hasTable('stock_transfer_items')) {
            Schema::create('stock_transfer_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('stock_transfer_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('product_variant_id')->nullable();
                $table->integer('quantity');
                $table->integer('received_quantity')->default(0);
                $table->timestamps();

                $table->foreign('stock_transfer_id')->references('id')->on('stock_transfers')->cascadeOnDelete();
                $table->index('product_id');
            });
        }

        /* ---------------------------------------------------------------
         | 3. Order domain additions
         | ------------------------------------------------------------ */
        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'parent_order_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedBigInteger('parent_order_id')->nullable()->after('id');
                $table->string('source', 20)->default('web')->after('order_status');
                $table->string('fulfillment_status', 20)->default('unfulfilled')->after('order_status');
                $table->unsignedBigInteger('warehouse_id')->nullable()->after('shop_id');
                $table->timestamp('packed_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('returned_at')->nullable();
                $table->timestamp('refunded_at')->nullable();
                $table->text('return_reason')->nullable();
                $table->decimal('refunded_amount', 18, 2)->default(0);
                $table->string('currency', 8)->default('IDR');
                $table->string('idempotency_key', 80)->nullable();

                $table->index('parent_order_id');
                $table->index('fulfillment_status');
                $table->index('idempotency_key');
            });
        }

        if (Schema::hasTable('order_items') && ! Schema::hasColumn('order_items', 'refund_amount')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->decimal('refund_amount', 18, 2)->default(0)->after('sub_total');
                $table->string('refund_reference', 80)->nullable();
                $table->string('fulfillment_status', 20)->default('unfulfilled');
            });
        }

        if (! Schema::hasTable('order_shipments')) {
            Schema::create('order_shipments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('provider_id')->nullable();
                $table->string('courier', 40)->nullable();
                $table->string('service', 80)->nullable();
                $table->string('tracking_number', 80)->nullable();
                $table->string('label_url', 255)->nullable();
                $table->decimal('weight', 12, 2)->nullable();
                $table->decimal('cost', 18, 2)->default(0);
                $table->string('status', 20)->default('pending'); // pending|shipped|in_transit|delivered|returned|failed
                $table->json('tracking_history')->nullable();
                $table->timestamp('shipped_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();

                $table->index('order_id');
                $table->index('tracking_number');
                $table->index('status');
            });
        }

        if (! Schema::hasTable('order_returns')) {
            Schema::create('order_returns', function (Blueprint $table) {
                $table->id();
                $table->string('rma_number', 40)->unique();
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('order_item_id')->nullable();
                $table->string('reason', 60);
                $table->text('description')->nullable();
                $table->json('images')->nullable();
                $table->string('status', 20)->default('requested'); // requested|approved|rejected|received|refunded
                $table->decimal('amount', 18, 2)->default(0);
                $table->unsignedBigInteger('decided_by')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->text('admin_note')->nullable();
                $table->timestamps();

                $table->index('order_id');
                $table->index('status');
            });
        }

        /* ---------------------------------------------------------------
         | 4. Refunds (with partial amounts and gateway execution)
         | ------------------------------------------------------------ */
        if (! Schema::hasTable('refunds')) {
            Schema::create('refunds', function (Blueprint $table) {
                $table->id();
                $table->string('refund_number', 48)->unique();
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('order_item_id')->nullable();
                $table->unsignedBigInteger('payment_group_id')->nullable();
                $table->unsignedBigInteger('provider_id')->nullable();
                $table->string('gateway_refund_id', 120)->nullable();
                $table->decimal('amount', 18, 2);
                $table->string('currency', 8)->default('IDR');
                $table->string('reason', 255)->nullable();
                $table->string('status', 20)->default('pending'); // pending|succeeded|failed|rejected
                $table->string('requested_by_type', 30)->default('customer');
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->string('idempotency_key', 80)->nullable();
                $table->json('gateway_response')->nullable();
                $table->text('failure_reason')->nullable();
                $table->timestamp('succeeded_at')->nullable();
                $table->timestamps();

                $table->index('order_id');
                $table->index('status');
                $table->index('gateway_refund_id');
                $table->unique('idempotency_key');
            });
        }

        /* ---------------------------------------------------------------
         | 5. Webhooks
         | ------------------------------------------------------------ */
        if (! Schema::hasTable('webhook_endpoints')) {
            Schema::create('webhook_endpoints', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('shop_id')->nullable();
                $table->string('name', 120);
                $table->string('url', 500);
                $table->string('secret', 128);
                $table->json('events')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('description', 255)->nullable();
                $table->timestamp('last_triggered_at')->nullable();
                $table->unsignedInteger('failure_count')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['is_active', 'created_at']);
                $table->index('shop_id');
            });
        }

        if (! Schema::hasTable('webhook_deliveries')) {
            Schema::create('webhook_deliveries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('webhook_endpoint_id');
                $table->string('event', 80)->index();
                $table->string('event_id', 64)->index();
                $table->json('payload')->nullable();
                $table->unsignedSmallInteger('attempt')->default(1);
                $table->unsignedSmallInteger('max_attempts')->default(5);
                $table->string('status', 20)->default('pending'); // pending|delivered|failed|exhausted
                $table->unsignedSmallInteger('response_status')->nullable();
                $table->text('response_body')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('next_retry_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'next_retry_at']);
                $table->index(['webhook_endpoint_id', 'created_at']);
            });
        }

        /* ---------------------------------------------------------------
         | 6. Notification centre
         | ------------------------------------------------------------ */
        if (! Schema::hasTable('user_notifications')) {
            Schema::create('user_notifications', function (Blueprint $table) {
                $table->id();
                $table->string('uuid', 40)->unique();
                $table->string('notifiable_type', 120);
                $table->unsignedBigInteger('notifiable_id');
                $table->string('channel', 20)->default('database'); // database|mail|push|sms
                $table->string('category', 40)->index(); // order|payment|wallet|marketing|system|chat
                $table->string('title', 180);
                $table->text('body')->nullable();
                $table->string('action_url', 500)->nullable();
                $table->string('action_label', 60)->nullable();
                $table->json('data')->nullable();
                $table->string('dedupe_key', 120)->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();

                $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
                $table->index(['notifiable_type', 'notifiable_id', 'created_at']);
                $table->index('dedupe_key');
            });
        }

        if (! Schema::hasTable('notification_preferences')) {
            Schema::create('notification_preferences', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('category', 40);
                $table->string('channel', 20);
                $table->boolean('enabled')->default(true);
                $table->timestamps();

                $table->unique(['user_id', 'category', 'channel'], 'notification_prefs_unique');
            });
        }

        if (! Schema::hasTable('devices')) {
            Schema::create('devices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('platform', 20); // ios|android|web
                $table->string('push_token', 255)->unique();
                $table->string('device_name', 120)->nullable();
                $table->string('app_version', 30)->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->index('user_id');
            });
        }

        /* ---------------------------------------------------------------
         | 7. Homepage sections
         | ------------------------------------------------------------ */
        if (! Schema::hasTable('homepage_sections')) {
            Schema::create('homepage_sections', function (Blueprint $table) {
                $table->id();
                $table->string('code', 60)->unique();
                $table->string('title', 160)->nullable();
                $table->string('subtitle', 255)->nullable();
                $table->boolean('is_enabled')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->json('settings')->nullable();
                $table->string('devices', 30)->default('all'); // all|desktop|mobile
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamps();

                $table->index(['is_enabled', 'sort_order']);
            });
        }

        /* ---------------------------------------------------------------
         | 8. PSEO quality engine
         | ------------------------------------------------------------ */
        if (! Schema::hasTable('pseo_templates')) {
            Schema::create('pseo_templates', function (Blueprint $table) {
                $table->id();
                $table->string('code', 60)->unique();
                $table->string('name', 120);
                $table->string('route_pattern', 200);
                $table->text('title_template')->nullable();
                $table->text('description_template')->nullable();
                $table->text('intro_template')->nullable();
                $table->unsignedTinyInteger('quality_threshold')->default(70);
                $table->boolean('is_indexable')->default(false);
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pseo_pages')) {
            Schema::create('pseo_pages', function (Blueprint $table) {
                $table->id();
                $table->string('template_code', 60)->index();
                $table->string('slug', 200);
                $table->string('url', 400);
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->text('body')->nullable();
                $table->json('context')->nullable(); // the resolved entities behind the page
                $table->unsignedTinyInteger('quality_score')->default(0);
                $table->json('quality_breakdown')->nullable();
                $table->string('state', 20)->default('generated'); // generated|reviewed|published|stale|disabled
                $table->string('indexability', 20)->default('noindex'); // index|noindex
                $table->string('canonical_url', 400)->nullable();
                $table->unsignedTinyInteger('product_count')->default(0);
                $table->timestamp('generated_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('stale_at')->nullable();
                $table->timestamps();

                $table->unique('url');
                $table->index(['state', 'indexability']);
                $table->index(['template_code', 'quality_score']);
            });
        }

        /* ---------------------------------------------------------------
         | 9. Multi-tenancy (white label)
         | ------------------------------------------------------------ */
        if (! Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table) {
                $table->id();
                $table->string('name', 160);
                $table->string('slug', 80)->unique();
                $table->string('domain', 160)->unique();
                $table->json('domains')->nullable();
                $table->string('logo_url', 255)->nullable();
                $table->string('favicon_url', 255)->nullable();
                $table->json('theme')->nullable();
                $table->string('currency_code', 8)->default('IDR');
                $table->string('timezone', 60)->default('Asia/Jakarta');
                $table->string('locale', 10)->default('id');
                $table->string('contact_email', 160)->nullable();
                $table->string('contact_phone', 40)->nullable();
                $table->json('enabled_features')->nullable();
                $table->decimal('default_commission_rate', 5, 2)->default(10);
                $table->boolean('is_active')->default(true);
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('is_active');
            });
        }

        if (! Schema::hasTable('saas_plans')) {
            Schema::create('saas_plans', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('slug', 80)->unique();
                $table->text('description')->nullable();
                $table->decimal('monthly_price', 18, 2)->default(0);
                $table->decimal('yearly_price', 18, 2)->default(0);
                $table->string('currency', 8)->default('IDR');
                $table->unsignedInteger('max_products')->nullable();
                $table->unsignedInteger('max_vendors')->nullable();
                $table->unsignedInteger('max_staff')->nullable();
                $table->unsignedInteger('max_orders_per_month')->nullable();
                $table->unsignedBigInteger('max_storage_mb')->nullable();
                $table->unsignedInteger('pseo_page_quota')->nullable();
                $table->unsignedInteger('ai_request_quota')->nullable();
                $table->json('features')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_featured')->default(false);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('saas_subscriptions')) {
            Schema::create('saas_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('saas_plan_id');
                $table->string('status', 20)->default('active'); // trialing|active|past_due|cancelled|expired
                $table->string('billing_cycle', 10)->default('monthly');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamp('grace_ends_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
                $table->index('ends_at');
            });
        }

        /* ---------------------------------------------------------------
         | 10. RBAC
         | ------------------------------------------------------------ */
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name', 80);
                $table->string('slug', 80)->unique();
                $table->string('description', 255)->nullable();
                $table->boolean('is_system')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('slug', 120)->unique();
                $table->string('group', 60)->index();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('permission_role')) {
            Schema::create('permission_role', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('role_id');
                $table->unsignedBigInteger('permission_id');

                $table->unique(['role_id', 'permission_id']);
                $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
                $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'is_super_admin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_super_admin')->default(false)->after('role');
            });
        }

        if (! Schema::hasTable('role_user')) {
            Schema::create('role_user', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('role_id');
                $table->unsignedBigInteger('user_id');

                $table->unique(['role_id', 'user_id']);
                $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
                $table->index('user_id');
            });
        }

        /* ---------------------------------------------------------------
         | 11. Conversations (customer <-> vendor <-> admin)
         | ------------------------------------------------------------ */
        if (! Schema::hasTable('conversations')) {
            Schema::create('conversations', function (Blueprint $table) {
                $table->id();
                $table->string('uuid', 40)->unique();
                $table->string('type', 20)->default('direct'); // direct|support|order
                $table->string('subject', 190)->nullable();
                $table->unsignedBigInteger('shop_id')->nullable();
                $table->unsignedBigInteger('order_id')->nullable();
                $table->string('status', 20)->default('open'); // open|pending|closed|spam
                $table->string('priority', 20)->default('normal');
                $table->timestamp('first_reply_at')->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['shop_id', 'status']);
                $table->index('last_message_at');
            });
        }

        if (! Schema::hasTable('conversation_participants')) {
            Schema::create('conversation_participants', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('conversation_id');
                $table->unsignedBigInteger('user_id');
                $table->string('role', 20)->default('member'); // customer|vendor|admin
                $table->timestamp('last_read_at')->nullable();
                $table->unsignedInteger('unread_count')->default(0);
                $table->boolean('is_muted')->default(false);
                $table->timestamps();

                $table->unique(['conversation_id', 'user_id']);
                $table->index(['user_id', 'unread_count']);
            });
        }

        if (! Schema::hasTable('messages')) {
            Schema::create('messages', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('conversation_id');
                $table->unsignedBigInteger('user_id');
                $table->text('body');
                $table->json('attachments')->nullable();
                $table->boolean('is_internal_note')->default(false);
                $table->boolean('is_flagged')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->timestamps();

                $table->index(['conversation_id', 'created_at']);
                $table->index(['user_id', 'read_at']);
            });
        }

        /* ---------------------------------------------------------------
         | 12. POS
         | ------------------------------------------------------------ */
        if (! Schema::hasTable('pos_outlets')) {
            Schema::create('pos_outlets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('shop_id')->nullable();
                $table->unsignedBigInteger('warehouse_id')->nullable();
                $table->string('name', 160);
                $table->string('code', 40)->unique();
                $table->string('address', 255)->nullable();
                $table->string('phone', 30)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['shop_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('pos_registers')) {
            Schema::create('pos_registers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pos_outlet_id');
                $table->string('name', 120);
                $table->string('code', 40)->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pos_shifts')) {
            Schema::create('pos_shifts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pos_register_id');
                $table->unsignedBigInteger('cashier_id')->nullable();
                $table->string('status', 20)->default('open'); // open|closed
                $table->decimal('opening_cash', 18, 2)->default(0);
                $table->decimal('expected_cash', 18, 2)->default(0);
                $table->decimal('counted_cash', 18, 2)->default(0);
                $table->decimal('variance', 18, 2)->default(0);
                $table->decimal('sales_total', 18, 2)->default(0);
                $table->unsignedInteger('transaction_count')->default(0);
                $table->text('note')->nullable();
                $table->timestamp('opened_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->index(['pos_register_id', 'status']);
            });
        }

        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'pos_shift_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedBigInteger('pos_shift_id')->nullable();
                $table->unsignedBigInteger('pos_register_id')->nullable();
            });
        }

        /* ---------------------------------------------------------------
         | 13. Customer segmentation / CRM
         | ------------------------------------------------------------ */
        if (! Schema::hasTable('customer_segments')) {
            Schema::create('customer_segments', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('slug', 120)->unique();
                $table->string('description', 255)->nullable();
                $table->string('type', 40)->default('manual'); // manual|rule
                $table->json('rules')->nullable();
                $table->unsignedInteger('member_count')->default(0);
                $table->boolean('is_dynamic')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('customer_segment_members')) {
            Schema::create('customer_segment_members', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_segment_id');
                $table->unsignedBigInteger('customer_id');
                $table->decimal('lifetime_value', 18, 2)->default(0);
                $table->unsignedInteger('order_count')->default(0);
                $table->timestamp('last_order_at')->nullable();
                $table->timestamps();

                $table->unique(['customer_segment_id', 'customer_id'], 'segment_member_unique');
                $table->index('customer_id');
            });
        }

        if (! Schema::hasTable('customer_activities')) {
            Schema::create('customer_activities', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id')->index();
                $table->string('type', 40)->index();
                $table->string('description', 255)->nullable();
                $table->string('reference_type', 60)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->json('properties')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();

                $table->index(['customer_id', 'created_at']);
            });
        }

        /* ---------------------------------------------------------------
         | 14. AI usage ledger
         | ------------------------------------------------------------ */
        if (! Schema::hasTable('ai_usages')) {
            Schema::create('ai_usages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('shop_id')->nullable();
                $table->unsignedBigInteger('provider_id')->nullable();
                $table->string('feature', 60)->index();
                $table->string('model', 120)->nullable();
                $table->unsignedInteger('prompt_tokens')->default(0);
                $table->unsignedInteger('completion_tokens')->default(0);
                $table->decimal('estimated_cost', 18, 6)->default(0);
                $table->unsignedInteger('duration_ms')->default(0);
                $table->boolean('success')->default(true);
                $table->text('error')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'created_at']);
                $table->index(['shop_id', 'created_at']);
            });
        }

        /* ---------------------------------------------------------------
         | 15. Marketing: campaigns
         | ------------------------------------------------------------ */
        if (! Schema::hasTable('campaigns')) {
            Schema::create('campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('name', 160);
                $table->string('slug', 160)->unique();
                $table->string('type', 30)->default('promotion'); // promotion|flash_sale|bundle|referral|affiliate|cashback
                $table->text('description')->nullable();
                $table->json('rules')->nullable();
                $table->json('audience')->nullable();
                $table->decimal('budget', 18, 2)->nullable();
                $table->decimal('discount_value', 18, 2)->default(0);
                $table->string('discount_type', 20)->default('percentage');
                $table->unsignedInteger('usage_limit')->nullable();
                $table->unsignedInteger('used_count')->default(0);
                $table->unsignedInteger('per_user_limit')->nullable();
                $table->string('status', 20)->default('draft'); // draft|scheduled|active|paused|ended
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->unsignedInteger('clicks')->default(0);
                $table->unsignedInteger('conversions')->default(0);
                $table->decimal('revenue', 18, 2)->default(0);
                $table->timestamps();

                $table->index(['status', 'starts_at', 'ends_at']);
            });
        }

        if (! Schema::hasTable('campaign_products')) {
            Schema::create('campaign_products', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('campaign_id');
                $table->unsignedBigInteger('product_id');
                $table->decimal('override_price', 18, 2)->nullable();

                $table->unique(['campaign_id', 'product_id']);
                $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
                $table->index('product_id');
            });
        }

        if (! Schema::hasTable('campaign_categories')) {
            Schema::create('campaign_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('campaign_id');
                $table->unsignedBigInteger('category_id');

                $table->unique(['campaign_id', 'category_id']);
            });
        }

        if (! Schema::hasTable('abandoned_carts')) {
            Schema::create('abandoned_carts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->string('email', 160)->nullable();
                $table->string('session_id', 80)->nullable();
                $table->unsignedInteger('item_count')->default(0);
                $table->decimal('amount', 18, 2)->default(0);
                $table->json('items')->nullable();
                $table->unsignedTinyInteger('reminder_count')->default(0);
                $table->timestamp('last_reminder_at')->nullable();
                $table->timestamp('recovered_at')->nullable();
                $table->unsignedBigInteger('recovered_order_id')->nullable();
                $table->timestamps();

                $table->index(['customer_id', 'recovered_at']);
                $table->index('created_at');
            });
        }

        /* ---------------------------------------------------------------
         | 16. Referral / affiliate
         | ------------------------------------------------------------ */
        if (! Schema::hasTable('affiliates')) {
            Schema::create('affiliates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('code', 40)->unique();
                $table->string('name', 120);
                $table->string('email', 160)->nullable();
                $table->string('status', 20)->default('pending'); // pending|active|rejected|suspended
                $table->decimal('commission_rate', 5, 2)->default(5);
                $table->decimal('total_commission', 18, 2)->default(0);
                $table->unsignedInteger('total_orders')->default(0);
                $table->decimal('total_revenue', 18, 2)->default(0);
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();

                $table->index('status');
            });
        }

        if (! Schema::hasTable('affiliate_clicks')) {
            Schema::create('affiliate_clicks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('affiliate_id')->index();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->string('landing_path', 400)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 400)->nullable();
                $table->unsignedBigInteger('converted_order_id')->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->timestamps();

                $table->index(['affiliate_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'affiliate_clicks', 'affiliates', 'abandoned_carts', 'campaign_categories', 'campaign_products', 'campaigns',
            'ai_usages', 'customer_activities', 'customer_segment_members', 'customer_segments',
            'pos_shifts', 'pos_registers', 'pos_outlets',
            'messages', 'conversation_participants', 'conversations',
            'role_user', 'permission_role', 'permissions', 'roles',
            'saas_subscriptions', 'saas_plans', 'tenants',
            'pseo_pages', 'pseo_templates',
            'homepage_sections',
            'devices', 'notification_preferences', 'user_notifications',
            'webhook_deliveries', 'webhook_endpoints',
            'refunds', 'order_returns', 'order_shipments',
            'stock_transfer_items', 'stock_transfers', 'stock_movements', 'product_stocks', 'warehouses',
            'ledger_entries', 'ledger_accounts',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
