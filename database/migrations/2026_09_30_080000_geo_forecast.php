<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Geo + forecast di atas skema existing (backward-compatible).
 *
 * Inspeksi: shops sudah punya latitude/longitude/city/province,
 * customer_addresses sudah punya latitude/longitude/city/province,
 * warehouses BELUM punya lat/lng, products BELUM punya kolom forecast.
 * Semua kolom baru nullable/ber-default sehingga baris lama tetap valid.
 * Tanpa mengubah kolom existing; tanpa menyentuh atomicity checkout.
 *
 * - warehouses: pin peta (latitude/longitude) + radius layanan (service_radius_km).
 * - shops: radius layanan (service_radius_km) untuk validasi radius pin.
 * - products: cache hasil forecast (laju jual, sisa hari, estimasi habis).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('warehouses')) {
            Schema::table('warehouses', function (Blueprint $table) {
                if (! Schema::hasColumn('warehouses', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable();
                }
                if (! Schema::hasColumn('warehouses', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable();
                }
                if (! Schema::hasColumn('warehouses', 'service_radius_km')) {
                    $table->unsignedInteger('service_radius_km')->nullable();
                }
            });
        }

        if (Schema::hasTable('shops')) {
            Schema::table('shops', function (Blueprint $table) {
                if (! Schema::hasColumn('shops', 'service_radius_km')) {
                    $table->unsignedInteger('service_radius_km')->nullable();
                }
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (! Schema::hasColumn('products', 'forecast_daily_rate')) {
                    $table->decimal('forecast_daily_rate', 12, 4)->nullable();
                }
                if (! Schema::hasColumn('products', 'forecast_days_left')) {
                    $table->integer('forecast_days_left')->nullable();
                }
                if (! Schema::hasColumn('products', 'forecast_stockout_at')) {
                    $table->date('forecast_stockout_at')->nullable();
                }
                if (! Schema::hasColumn('products', 'forecast_run_at')) {
                    $table->timestamp('forecast_run_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                foreach (['forecast_daily_rate', 'forecast_days_left', 'forecast_stockout_at', 'forecast_run_at'] as $column) {
                    if (Schema::hasColumn('products', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('shops')) {
            Schema::table('shops', function (Blueprint $table) {
                if (Schema::hasColumn('shops', 'service_radius_km')) {
                    $table->dropColumn('service_radius_km');
                }
            });
        }

        if (Schema::hasTable('warehouses')) {
            Schema::table('warehouses', function (Blueprint $table) {
                foreach (['latitude', 'longitude', 'service_radius_km'] as $column) {
                    if (Schema::hasColumn('warehouses', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
