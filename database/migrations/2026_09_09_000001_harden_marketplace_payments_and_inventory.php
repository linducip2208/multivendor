<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_groups', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 64)->unique();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('provider_id')->constrained('providers')->restrictOnDelete();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->string('status', 20)->default('pending')->index();
            $table->string('gateway_reference')->nullable()->index();
            $table->json('gateway_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_group_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();
            $table->unique(['payment_group_id', 'order_id']);
        });

        Schema::create('payment_webhook_callbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers')->restrictOnDelete();
            $table->foreignId('payment_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway_transaction_id')->nullable();
            $table->string('external_id')->nullable()->index();
            $table->string('status', 20)->nullable();
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->string('processing_result', 30)->default('received');
            $table->timestamps();
            $table->unique(['provider_id', 'gateway_transaction_id'], 'pay_callback_gateway_unique');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('payment_group_id')->nullable()->after('id')->constrained('payment_groups')->nullOnDelete();
            $table->timestamp('stock_released_at')->nullable()->after('canceled_at');
            $table->index(['payment_group_id', 'payment_status']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('payment_group_id')->nullable()->after('id')->constrained('payment_groups')->nullOnDelete();
            $table->index(['payment_group_id', 'status']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('weight')->default(1000)->after('current_stock')->comment('grams');
            $table->index(['shop_id', 'status', 'published']);
        });

        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->string('shipping_destination_id', 100)->nullable()->after('postal_code');
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->string('operation', 30)->default('credit')->after('type');
            $table->string('reference_key', 120)->nullable()->after('reference_id');
            $table->unique(['wallet_id', 'reference_key'], 'wallet_reference_key_unique');
            $table->index(['wallet_id', 'reference_type', 'reference_id'], 'wallet_reference_lookup');
        });

        Schema::table('wallets', function (Blueprint $table) {
            $table->unique('user_id', 'wallet_user_unique');
        });

        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->unique(['coupon_id', 'customer_id', 'order_id'], 'coupon_usage_business_unique');
        });

        Schema::table('shop_shipping_methods', function (Blueprint $table) {
            $table->unique(['shop_id', 'shipping_method_id']);
        });

        Schema::table('delivery_man_earnings', function (Blueprint $table) {
            $table->unique(['delivery_man_id', 'order_id'], 'delivery_earning_order_unique');
        });

        Schema::table('delivery_cash_collects', function (Blueprint $table) {
            $table->unique(['delivery_man_id', 'order_id'], 'delivery_collect_order_unique');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_cash_collects', fn (Blueprint $table) => $table->dropUnique('delivery_collect_order_unique'));
        Schema::table('delivery_man_earnings', fn (Blueprint $table) => $table->dropUnique('delivery_earning_order_unique'));
        Schema::table('shop_shipping_methods', fn (Blueprint $table) => $table->dropUnique(['shop_id', 'shipping_method_id']));
        Schema::table('coupon_usages', fn (Blueprint $table) => $table->dropUnique('coupon_usage_business_unique'));
        Schema::table('wallets', fn (Blueprint $table) => $table->dropUnique('wallet_user_unique'));
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropUnique('wallet_reference_key_unique');
            $table->dropIndex('wallet_reference_lookup');
            $table->dropColumn(['operation', 'reference_key']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['shop_id', 'status', 'published']);
            $table->dropColumn('weight');
        });
        Schema::table('customer_addresses', fn (Blueprint $table) => $table->dropColumn('shipping_destination_id'));
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['payment_group_id', 'status']);
            $table->dropConstrainedForeignId('payment_group_id');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_group_id', 'payment_status']);
            $table->dropConstrainedForeignId('payment_group_id');
            $table->dropColumn('stock_released_at');
        });
        Schema::dropIfExists('payment_webhook_callbacks');
        Schema::dropIfExists('payment_group_orders');
        Schema::dropIfExists('payment_groups');
    }
};
