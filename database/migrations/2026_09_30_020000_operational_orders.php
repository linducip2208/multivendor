<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kapabilitas operasional di atas alur existing (backward-compatible).
 *
 * Semua kolom baru nullable / ber-default sehingga baris lama tetap valid.
 * Atomicity, lockForUpdate, dan idempotency checkout tidak disentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                // 1. Dropship: penanda + identitas pengirim + sembunyikan harga.
                if (! Schema::hasColumn('orders', 'is_dropship')) {
                    $table->boolean('is_dropship')->default(false);
                }
                if (! Schema::hasColumn('orders', 'dropship_sender_name')) {
                    $table->string('dropship_sender_name', 255)->nullable();
                }
                if (! Schema::hasColumn('orders', 'dropship_sender_store')) {
                    $table->string('dropship_sender_store', 255)->nullable();
                }
                if (! Schema::hasColumn('orders', 'hide_price_in_package')) {
                    $table->boolean('hide_price_in_package')->default(false);
                }
                // 2. Pre-order: flag + ETA + DP (uang muka partial).
                if (! Schema::hasColumn('orders', 'is_preorder')) {
                    $table->boolean('is_preorder')->default(false);
                }
                if (! Schema::hasColumn('orders', 'preorder_eta')) {
                    $table->date('preorder_eta')->nullable();
                }
                if (! Schema::hasColumn('orders', 'preorder_dp_amount')) {
                    $table->decimal('preorder_dp_amount', 15, 2)->default(0);
                }
                if (! Schema::hasColumn('orders', 'preorder_remaining')) {
                    $table->decimal('preorder_remaining', 15, 2)->default(0);
                }
                if (! Schema::hasColumn('orders', 'preorder_settled_at')) {
                    $table->timestamp('preorder_settled_at')->nullable();
                }
                // 3. Gift: bungkus + kartu ucapan + fee resmi.
                if (! Schema::hasColumn('orders', 'is_gift')) {
                    $table->boolean('is_gift')->default(false);
                }
                if (! Schema::hasColumn('orders', 'gift_wrap')) {
                    $table->boolean('gift_wrap')->default(false);
                }
                if (! Schema::hasColumn('orders', 'gift_message')) {
                    $table->text('gift_message')->nullable();
                }
                if (! Schema::hasColumn('orders', 'gift_fee')) {
                    $table->decimal('gift_fee', 10, 2)->default(0);
                }
                // 4. COD OTP: verifikasi saat terima untuk nominal di atas ambang.
                if (! Schema::hasColumn('orders', 'cod_otp_hash')) {
                    $table->string('cod_otp_hash', 255)->nullable();
                }
                if (! Schema::hasColumn('orders', 'cod_otp_expires_at')) {
                    $table->timestamp('cod_otp_expires_at')->nullable();
                }
                if (! Schema::hasColumn('orders', 'cod_otp_attempts')) {
                    $table->unsignedInteger('cod_otp_attempts')->default(0);
                }
                if (! Schema::hasColumn('orders', 'cod_otp_verified_at')) {
                    $table->timestamp('cod_otp_verified_at')->nullable();
                }
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (! Schema::hasColumn('products', 'is_preorder')) {
                    $table->boolean('is_preorder')->default(false);
                }
                if (! Schema::hasColumn('products', 'preorder_lead_days')) {
                    $table->unsignedInteger('preorder_lead_days')->nullable();
                }
                if (! Schema::hasColumn('products', 'preorder_dp_percent')) {
                    $table->decimal('preorder_dp_percent', 5, 2)->default(0);
                }
            });
        }

        // 5. Repeat-order langganan: jadwal membuat DRAF cart, bukan order langsung.
        if (! Schema::hasTable('order_repeat_schedules')) {
            Schema::create('order_repeat_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
                $table->unsignedInteger('quantity')->default(1);
                $table->string('frequency', 20)->default('weekly'); // daily|weekly|monthly
                $table->timestamp('next_run_at')->nullable()->index();
                $table->timestamp('last_run_at')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();

                $table->index(['customer_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_repeat_schedules');

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                foreach (['is_preorder', 'preorder_lead_days', 'preorder_dp_percent'] as $column) {
                    if (Schema::hasColumn('products', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                foreach ([
                    'is_dropship', 'dropship_sender_name', 'dropship_sender_store', 'hide_price_in_package',
                    'is_preorder', 'preorder_eta', 'preorder_dp_amount', 'preorder_remaining', 'preorder_settled_at',
                    'is_gift', 'gift_wrap', 'gift_message', 'gift_fee',
                    'cod_otp_hash', 'cod_otp_expires_at', 'cod_otp_attempts', 'cod_otp_verified_at',
                ] as $column) {
                    if (Schema::hasColumn('orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
