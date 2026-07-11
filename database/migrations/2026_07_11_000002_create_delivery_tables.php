<?php

use App\Models\DeliveryManEarning;
use App\Models\DeliveryCashCollect;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('delivery_man_ratings')) {
            Schema::create('delivery_man_ratings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('delivery_man_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->integer('rating')->default(5);
                $table->text('review')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('delivery_man_earnings')) {
            Schema::create('delivery_man_earnings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('delivery_man_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->decimal('amount', 12, 2);
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('delivery_cash_collects')) {
            Schema::create('delivery_cash_collects', function (Blueprint $table) {
                $table->id();
                $table->foreignId('delivery_man_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->decimal('amount', 12, 2);
                $table->boolean('collected')->default(false);
                $table->timestamp('collected_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_man_ratings');
        Schema::dropIfExists('delivery_man_earnings');
        Schema::dropIfExists('delivery_cash_collects');
    }
};
