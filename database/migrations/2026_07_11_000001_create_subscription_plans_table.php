<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->string('billing_period')->default('monthly');
            $table->integer('max_products')->default(0);
            $table->integer('max_images_per_product')->default(5);
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->boolean('can_chat')->default(true);
            $table->boolean('can_export')->default(false);
            $table->boolean('can_bulk_import')->default(false);
            $table->boolean('can_pos')->default(false);
            $table->boolean('can_barcode')->default(false);
            $table->boolean('featured_shop')->default(false);
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('vendor_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->string('status')->default('active');
            $table->decimal('amount_paid', 12, 2);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('canceled_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('transaction_reference')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
