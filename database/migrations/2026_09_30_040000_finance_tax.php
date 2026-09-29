<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perdalaman keuangan: e-Faktur pajak per order + settlement komisi terjadwal.
 *
 * Backward-compatible: hanya CREATE tabel baru dan ADD kolom nullable/default.
 * Rollback: drop tabel baru, drop kolom tambahan (guard hasColumn).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tax_invoices')) {
            Schema::create('tax_invoices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
                $table->foreignId('shop_id')->index()->constrained()->cascadeOnDelete();
                $table->string('invoice_number', 64)->unique(); // INV-... (turunan order_number)
                $table->string('tax_serial', 64)->unique(); // nomor seri pajak e-Faktur
                $table->decimal('dpp', 15, 2)->default(0); // dasar pengenaan pajak
                $table->decimal('ppn_rate', 8, 2)->default(0);
                $table->decimal('ppn', 15, 2)->default(0);
                $table->decimal('grand_total', 15, 2)->default(0);
                $table->string('status', 20)->default('issued')->index();
                $table->timestamp('issued_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('settlement_batches')) {
            Schema::create('settlement_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();
                $table->date('period_start')->index();
                $table->date('period_end')->index();
                $table->string('period_label', 32)->index(); // YYYY-MM per toko
                $table->integer('orders_count')->default(0);
                $table->decimal('gross', 15, 2)->default(0);
                $table->decimal('commission', 15, 2)->default(0);
                $table->decimal('tax', 15, 2)->default(0);
                $table->decimal('net_payable', 15, 2)->default(0);
                $table->string('status', 20)->default('posted')->index(); // drafted|posted|paid
                $table->timestamp('executed_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->unique(['shop_id', 'period_label'], 'settlement_shop_period_unique');
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'settled_at')) {
                $table->timestamp('settled_at')->nullable()->after('completed_at');
            }
            if (! Schema::hasColumn('orders', 'settlement_batch_id')) {
                $table->foreignId('settlement_batch_id')->nullable()->after('settled_at')
                    ->constrained('settlement_batches')->nullOnDelete();
            }
            if (! Schema::hasColumn('orders', 'held_amount')) {
                $table->decimal('held_amount', 15, 2)->default(0)->after('settlement_batch_id');
            }
        });

        Schema::table('vendor_withdraw_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_withdraw_requests', 'settlement_batch_id')) {
                $table->foreignId('settlement_batch_id')->nullable()->after('shop_id')
                    ->constrained('settlement_batches')->nullOnDelete();
            }
            if (! Schema::hasColumn('vendor_withdraw_requests', 'period_label')) {
                $table->string('period_label', 32)->nullable()->after('settlement_batch_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_withdraw_requests', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_withdraw_requests', 'settlement_batch_id')) {
                try {
                    $table->dropConstrainedForeignId('settlement_batch_id');
                } catch (\Throwable) {
                    $table->dropColumn('settlement_batch_id');
                }
            }
            if (Schema::hasColumn('vendor_withdraw_requests', 'period_label')) {
                $table->dropColumn('period_label');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'settlement_batch_id')) {
                try {
                    $table->dropConstrainedForeignId('settlement_batch_id');
                } catch (\Throwable) {
                    $table->dropColumn('settlement_batch_id');
                }
            }
            if (Schema::hasColumn('orders', 'held_amount')) {
                $table->dropColumn('held_amount');
            }
            if (Schema::hasColumn('orders', 'settled_at')) {
                $table->dropColumn('settled_at');
            }
        });

        Schema::dropIfExists('settlement_batches');
        Schema::dropIfExists('tax_invoices');
    }
};
