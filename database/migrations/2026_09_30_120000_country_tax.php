<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Country + tax engine (database-driven).
 *
 * Backward-compatible: hanya CREATE tabel baru, tanpa mengubah tabel existing.
 * Rollback: drop tabel baru urutan terbalik (tax_rules → cities → regions → countries).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('countries')) {
            Schema::create('countries', function (Blueprint $table) {
                $table->id();
                $table->char('iso2', 2)->unique(); // ISO 3166-1 alpha-2, mis. ID, US, DE
                $table->char('iso3', 3)->unique()->nullable(); // ISO alpha-3, mis. IDN, USA
                $table->string('name', 100);
                $table->char('currency_code', 3); // ISO 4217, mis. IDR, USD, EUR
                $table->string('locale', 12); // mis. id, en_US, de_DE
                $table->string('timezone', 64); // IANA, mis. Asia/Jakarta
                $table->boolean('is_active')->default(true)->index();
                $table->json('payment_hints')->nullable(); // saran metode bayar per negara
                $table->json('shipping_hints')->nullable(); // saran pengiriman per negara
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('regions')) {
            Schema::create('regions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
                $table->string('code', 16); // mis. JB (Jawa Barat), CA (California)
                $table->string('name', 100);
                $table->string('type', 32)->nullable(); // province|state|prefecture|...
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
                $table->unique(['country_id', 'code']);
                $table->index(['country_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('cities')) {
            Schema::create('cities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
                $table->foreignId('region_id')->constrained('regions')->cascadeOnDelete();
                $table->string('code', 32)->nullable();
                $table->string('name', 100);
                $table->string('postal_prefix', 16)->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
                $table->index(['region_id', 'is_active']);
                $table->index(['country_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('tax_rules')) {
            Schema::create('tax_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
                $table->foreignId('region_id')->nullable()->constrained('regions')->cascadeOnDelete();
                $table->string('tax_class', 32)->default('standard')->index(); // standard|reduced|luxury|...
                $table->decimal('rate', 8, 4); // persen, mis. 11.0000
                $table->boolean('is_inclusive')->default(false); // true = harga sudah termasuk pajak
                $table->boolean('is_compound')->default(false); // true = dihitung di atas pajak sebelumnya
                $table->integer('priority')->default(0); // urutan aplikasi menaik
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
                $table->index(['country_id', 'region_id', 'tax_class', 'is_active'], 'tax_rules_geo_class_idx');
                $table->index(['country_id', 'tax_class', 'is_active'], 'tax_rules_country_class_idx');
                $table->index(['country_id', 'priority'], 'tax_rules_country_priority_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rules');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('regions');
        Schema::dropIfExists('countries');
    }
};
