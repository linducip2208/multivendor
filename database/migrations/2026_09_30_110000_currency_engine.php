<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-currency engine: tabel currencies.
 *
 * Konvensi kurs (didokumentasikan agar konsisten di semua service):
 * - exchange_rate = jumlah IDR per 1 (satu) satuan MAJOR mata uang tsb.
 * - Contoh: IDR = 1.00000000, USD = 16500.00000000 (1 USD = Rp16.500).
 * - Konversi antar mata uang selalu lewat basis IDR:
 *     major_tujuan = (major_asal * rate_asal) / rate_tujuan
 *   lalu dibulatkan half-up ke minor-units mata uang tujuan.
 *
 * Aditif: tabel baru saja, tanpa mengubah kalkulasi checkout/payment existing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('currencies')) {
            return;
        }

        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique()->comment('ISO 4217, uppercase: IDR, USD, ...');
            $table->string('name', 80);
            $table->string('symbol', 12);
            $table->unsignedTinyInteger('decimal_places')->default(2)->comment('0 untuk IDR/JPY, 2 untuk mayoritas');
            $table->string('decimal_separator', 2)->default('.');
            $table->string('thousand_separator', 2)->default(',');
            $table->decimal('exchange_rate', 24, 8)->default('1.00000000')->comment('IDR per 1 major unit');
            $table->string('rate_source', 30)->default('manual');
            $table->timestamp('rate_updated_at')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
            $table->index('is_default');
            $table->index(['is_active', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
