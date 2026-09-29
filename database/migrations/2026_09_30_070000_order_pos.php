<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perdalaman order & POS (backward-compatible).
 *
 * Semua kolom baru nullable / ber-default sehingga baris lama tetap valid.
 * Atomicity (lockForUpdate + satu DB::transaction di service) tidak disentuh:
 * migrasi ini hanya menambah kolom + satu tabel kasir, tanpa mengubah kolom
 * existing. Rollback menghapus kembali semua yang ditambahkan di up().
 *
 * - orders: slot jadwal pengiriman (tanggal/jam/label + kapan dijadwalkan)
 *   serta struk digital POS (token verifikasi, nomor WA pelanggan, kapan
 *   struk dibagikan).
 * - pos_payments: rincian split tender per order POS (tunai + QRIS dst.
 *   dalam satu struk; satu baris per metode).
 * - order_returns: grading QC retur (baik/rusak/buang) + jejak stok kembali.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'delivery_slot_date')) {
                    $table->date('delivery_slot_date')->nullable();
                }
                if (! Schema::hasColumn('orders', 'delivery_slot_time')) {
                    $table->string('delivery_slot_time', 20)->nullable();
                }
                if (! Schema::hasColumn('orders', 'delivery_slot_label')) {
                    $table->string('delivery_slot_label', 120)->nullable();
                }
                if (! Schema::hasColumn('orders', 'slot_scheduled_at')) {
                    $table->timestamp('slot_scheduled_at')->nullable();
                }
                if (! Schema::hasColumn('orders', 'receipt_token')) {
                    $table->string('receipt_token', 64)->nullable();
                }
                if (! Schema::hasColumn('orders', 'pos_customer_phone')) {
                    $table->string('pos_customer_phone', 20)->nullable();
                }
                if (! Schema::hasColumn('orders', 'digital_receipt_sent_at')) {
                    $table->timestamp('digital_receipt_sent_at')->nullable();
                }
            });

            $this->addIndex('orders', ['delivery_slot_date'], 'orders_delivery_slot_date_idx');

            try {
                Schema::table('orders', fn (Blueprint $t) => $t->unique('receipt_token', 'orders_receipt_token_unique'));
            } catch (\Throwable) {
                // Token unik sudah ada atau DB tidak mendukung — rerun aman.
            }
        }

        if (! Schema::hasTable('pos_payments')) {
            Schema::create('pos_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->string('method', 20); // cash|qris|transfer|debit|ewallet
                $table->decimal('amount', 18, 2)->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index('order_id', 'pos_payments_order_idx');
            });
        }

        if (Schema::hasTable('order_returns')) {
            Schema::table('order_returns', function (Blueprint $table) {
                if (! Schema::hasColumn('order_returns', 'qc_grade')) {
                    $table->string('qc_grade', 20)->nullable(); // baik|rusak|buang
                }
                if (! Schema::hasColumn('order_returns', 'qc_note')) {
                    $table->text('qc_note')->nullable();
                }
                if (! Schema::hasColumn('order_returns', 'qc_at')) {
                    $table->timestamp('qc_at')->nullable();
                }
                if (! Schema::hasColumn('order_returns', 'qc_by')) {
                    $table->unsignedBigInteger('qc_by')->nullable();
                }
                if (! Schema::hasColumn('order_returns', 'stock_restored_qty')) {
                    $table->unsignedInteger('stock_restored_qty')->default(0);
                }
            });

            $this->addIndex('order_returns', ['qc_grade'], 'order_returns_qc_grade_idx');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_payments');

        if (Schema::hasTable('order_returns')) {
            Schema::table('order_returns', function (Blueprint $table) {
                foreach (['qc_grade', 'qc_note', 'qc_at', 'qc_by', 'stock_restored_qty'] as $column) {
                    if (Schema::hasColumn('order_returns', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('orders')) {
            try {
                Schema::table('orders', fn (Blueprint $table) => $table->dropUnique('orders_receipt_token_unique'));
            } catch (\Throwable) {
                // Indeks unik belum ada — abaikan.
            }

            Schema::table('orders', function (Blueprint $table) {
                foreach ([
                    'delivery_slot_date', 'delivery_slot_time', 'delivery_slot_label',
                    'slot_scheduled_at', 'receipt_token', 'pos_customer_phone',
                    'digital_receipt_sent_at',
                ] as $column) {
                    if (Schema::hasColumn('orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function addIndex(string $table, array $columns, string $name): void
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        try {
            Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
        } catch (\Throwable) {
            // Indeks sudah ada (rerun aman) — abaikan.
        }
    }
};
