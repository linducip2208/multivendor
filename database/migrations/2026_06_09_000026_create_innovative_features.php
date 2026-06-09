<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('price_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('target_price', 15, 2);
            $table->boolean('notified')->default(false);
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->unique(['customer_id','product_id']);
        });
        Schema::create('product_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->decimal('discount_percentage', 5, 2)->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('bundle_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_id')->constrained('product_bundles')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
        Schema::create('group_buys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('target_count');
            $table->integer('current_count')->default(0);
            $table->decimal('discount_percentage', 5, 2);
            $table->decimal('special_price', 15, 2);
            $table->dateTime('end_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('group_buy_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_buy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['group_buy_id','customer_id']);
        });
        Schema::create('customer_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->string('badge');
            $table->string('tier')->default('bronze');
            $table->timestamps();
        });
        Schema::create('social_feeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('video_url')->nullable();
            $table->string('caption')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('views')->default(0);
            $table->integer('likes')->default(0);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('social_feeds');
        Schema::dropIfExists('customer_badges');
        Schema::dropIfExists('group_buy_participants');
        Schema::dropIfExists('group_buys');
        Schema::dropIfExists('bundle_products');
        Schema::dropIfExists('product_bundles');
        Schema::dropIfExists('price_alerts');
    }
};
