<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kapabilitas B2B/grosir — 100% aditif dan backward-compatible.
 *
 * Inspeksi sebelum tulis:
 * - `products` sudah punya `min_qty`/`max_qty` (2026_06_09_000005) → MOQ guard
 *   memakai kolom existing, TIDAK ada alter ke `products`.
 * - `orders.payment_status` sudah enum ['unpaid','paid','partial','refunded']
 *   (2026_06_09_000009) → termin memakai nilai 'partial' existing, TIDAK ada
 *   alter ke `orders`.
 * - `users.referral_code` + `referred_by` sudah ada (2026_06_09_000021) →
 *   dasbor salesman read-only dari kolom existing, TIDAK ada alter ke `users`.
 *
 * Maka migrasi ini HANYA membuat 2 tabel baru (nullable/default aman).
 * Rollback menghapus keduanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('b2b_price_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->restrictOnDelete();
            $table->unsignedInteger('min_qty');
            $table->decimal('price', 15, 2);
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'min_qty']);
            $table->index(['shop_id', 'product_id']);
        });

        Schema::create('b2b_termins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->restrictOnDelete();
            $table->unsignedInteger('sequence')->default(1);
            $table->string('label', 120)->nullable();
            $table->decimal('amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->string('status', 20)->default('scheduled');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'sequence']);
            $table->index(['shop_id', 'status']);
            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b2b_termins');
        Schema::dropIfExists('b2b_price_tiers');
    }
};
