<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logistik lanjutan di atas skema existing (backward-compatible).
 *
 * Semua kolom baru nullable / ber-default sehingga baris lama tetap valid.
 * Atomicity checkout (lockForUpdate + satu DB::transaction) tidak disentuh:
 * migrasi ini hanya menambah kolom, tanpa mengubah kolom existing.
 *
 * - orders: mode ambil di toko (click & collect) + kode verifikasi hash.
 * - warehouses: penanda melayani pengambilan di tempat.
 * - order_shipments: gudang pemenuh + manifest AWB + penanda pickup.
 * - order_returns: jadwal penjemputan retur + label retur.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'is_pickup')) {
                    $table->boolean('is_pickup')->default(false);
                }
                if (! Schema::hasColumn('orders', 'pickup_warehouse_id')) {
                    $table->unsignedBigInteger('pickup_warehouse_id')->nullable();
                }
                if (! Schema::hasColumn('orders', 'pickup_code_hash')) {
                    $table->string('pickup_code_hash', 255)->nullable();
                }
                if (! Schema::hasColumn('orders', 'pickup_verified_at')) {
                    $table->timestamp('pickup_verified_at')->nullable();
                }
                if (! Schema::hasColumn('orders', 'pickup_ready_at')) {
                    $table->timestamp('pickup_ready_at')->nullable();
                }
                if (! Schema::hasColumn('orders', 'pickup_completed_at')) {
                    $table->timestamp('pickup_completed_at')->nullable();
                }
            });

            $this->addIndex('orders', ['is_pickup'], 'orders_is_pickup_idx');
            $this->addIndex('orders', ['pickup_warehouse_id'], 'orders_pickup_warehouse_idx');
        }

        if (Schema::hasTable('warehouses')) {
            Schema::table('warehouses', function (Blueprint $table) {
                if (! Schema::hasColumn('warehouses', 'allow_pickup')) {
                    $table->boolean('allow_pickup')->default(false);
                }
                if (! Schema::hasColumn('warehouses', 'pickup_hours')) {
                    $table->string('pickup_hours', 120)->nullable();
                }
                if (! Schema::hasColumn('warehouses', 'pickup_address')) {
                    $table->string('pickup_address', 255)->nullable();
                }
            });

            $this->addIndex('warehouses', ['allow_pickup', 'is_active'], 'warehouses_pickup_active_idx');
        }

        if (Schema::hasTable('order_shipments')) {
            Schema::table('order_shipments', function (Blueprint $table) {
                if (! Schema::hasColumn('order_shipments', 'warehouse_id')) {
                    $table->unsignedBigInteger('warehouse_id')->nullable();
                }
                if (! Schema::hasColumn('order_shipments', 'is_pickup')) {
                    $table->boolean('is_pickup')->default(false);
                }
                if (! Schema::hasColumn('order_shipments', 'pickup_code')) {
                    $table->string('pickup_code', 20)->nullable();
                }
                if (! Schema::hasColumn('order_shipments', 'pickup_verified_at')) {
                    $table->timestamp('pickup_verified_at')->nullable();
                }
                if (! Schema::hasColumn('order_shipments', 'manifest_no')) {
                    $table->string('manifest_no', 40)->nullable();
                }
                if (! Schema::hasColumn('order_shipments', 'manifest_date')) {
                    $table->date('manifest_date')->nullable();
                }
            });

            $this->addIndex('order_shipments', ['manifest_no'], 'order_shipments_manifest_no_idx');
            $this->addIndex('order_shipments', ['manifest_date'], 'order_shipments_manifest_date_idx');
            $this->addIndex('order_shipments', ['warehouse_id'], 'order_shipments_warehouse_idx');
            $this->addIndex('order_shipments', ['is_pickup'], 'order_shipments_is_pickup_idx');
        }

        if (Schema::hasTable('order_returns')) {
            Schema::table('order_returns', function (Blueprint $table) {
                if (! Schema::hasColumn('order_returns', 'pickup_status')) {
                    $table->string('pickup_status', 20)->nullable();
                }
                if (! Schema::hasColumn('order_returns', 'pickup_scheduled_at')) {
                    $table->timestamp('pickup_scheduled_at')->nullable();
                }
                if (! Schema::hasColumn('order_returns', 'pickup_address')) {
                    $table->string('pickup_address', 255)->nullable();
                }
                if (! Schema::hasColumn('order_returns', 'pickup_courier')) {
                    $table->string('pickup_courier', 40)->nullable();
                }
                if (! Schema::hasColumn('order_returns', 'pickup_tracking')) {
                    $table->string('pickup_tracking', 80)->nullable();
                }
                if (! Schema::hasColumn('order_returns', 'return_label_code')) {
                    $table->string('return_label_code', 40)->nullable();
                }
            });

            $this->addIndex('order_returns', ['pickup_status'], 'order_returns_pickup_status_idx');
            $this->addIndex('order_returns', ['pickup_scheduled_at'], 'order_returns_pickup_at_idx');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_returns')) {
            Schema::table('order_returns', function (Blueprint $table) {
                foreach (['pickup_status', 'pickup_scheduled_at', 'pickup_address', 'pickup_courier', 'pickup_tracking', 'return_label_code'] as $column) {
                    if (Schema::hasColumn('order_returns', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('order_shipments')) {
            Schema::table('order_shipments', function (Blueprint $table) {
                foreach (['warehouse_id', 'is_pickup', 'pickup_code', 'pickup_verified_at', 'manifest_no', 'manifest_date'] as $column) {
                    if (Schema::hasColumn('order_shipments', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('warehouses')) {
            Schema::table('warehouses', function (Blueprint $table) {
                foreach (['allow_pickup', 'pickup_hours', 'pickup_address'] as $column) {
                    if (Schema::hasColumn('warehouses', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                foreach (['is_pickup', 'pickup_warehouse_id', 'pickup_code_hash', 'pickup_verified_at', 'pickup_ready_at', 'pickup_completed_at'] as $column) {
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
