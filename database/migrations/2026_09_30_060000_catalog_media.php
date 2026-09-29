<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perdalaman katalog existing: galeri per varian, lisensi digital,
 * dan koleksi tematik terkurasi.
 *
 * Kolom/tabel yang SUDAH diverifikasi ada di database live (MySQL):
 * - products.digital_file (varchar 255, nullable) — dipakai ulang untuk lisensi digital.
 * - products.images (json, nullable) + products.thumbnail — fallback galeri.
 * - product_variants: id, product_id, sku, variant, variant_attributes,
 *   price, special_price, discount_type, discount_start/end, stock,
 *   low_stock_threshold — BELUM ada kolom images (ditambah di sini).
 * - homepage_sections: starts_at/ends_at/is_enabled — jadwal didukung di
 *   tabel itu; koleksi tematik membawa kolom jadwalnya sendiri.
 * - order_items: product_id, product_variant_id — relasi lisensi per pembelian.
 *
 * Semua aditif + terjaga (guarded): aman dijalankan ulang, dan down()
 * mengembalikan skema seperti semula. Tidak menyentuh alur checkout/cart.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Gambar per varian — galeri PDP berubah mengikuti varian terpilih.
        if (Schema::hasTable('product_variants') && ! Schema::hasColumn('product_variants', 'images')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->json('images')->nullable()->after('variant_attributes');
            });
        }

        // 2a. Lisensi digital — satu kunci unik per pembelian produk digital.
        if (! Schema::hasTable('digital_licenses')) {
            Schema::create('digital_licenses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->unsignedBigInteger('order_item_id')->nullable()->unique();
                $table->string('license_key', 64)->unique();
                $table->unsignedInteger('max_downloads')->default(5);
                $table->unsignedInteger('download_count')->default(0);
                $table->boolean('revoked')->default(false);
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index(['product_id', 'revoked'], 'digital_licenses_product_idx');
            });
        }

        // 2b. Riwayat unduhan tiap lisensi (batas unduh ditegakkan dari sini).
        if (! Schema::hasTable('digital_license_downloads')) {
            Schema::create('digital_license_downloads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('digital_license_id')->constrained('digital_licenses')->cascadeOnDelete();
                $table->timestamp('downloaded_at')->nullable();
                $table->string('ip_hash', 64)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestamps();

                $table->index('digital_license_id', 'digital_license_downloads_license_idx');
            });
        }

        // 3a. Koleksi tematik terkurasi (mis. "Back to School") + jadwal tampil.
        if (! Schema::hasTable('thematic_collections')) {
            Schema::create('thematic_collections', function (Blueprint $table) {
                $table->id();
                $table->string('name', 160);
                $table->string('slug', 190)->unique();
                $table->text('description')->nullable();
                $table->string('banner', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['is_active', 'sort_order'], 'thematic_collections_active_idx');
            });
        }

        // 3b. Anggota produk tiap koleksi.
        if (! Schema::hasTable('thematic_collection_product')) {
            Schema::create('thematic_collection_product', function (Blueprint $table) {
                $table->id();
                $table->foreignId('thematic_collection_id')->constrained('thematic_collections')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['thematic_collection_id', 'product_id'], 'thematic_collection_product_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('thematic_collection_product');
        Schema::dropIfExists('thematic_collections');
        Schema::dropIfExists('digital_license_downloads');
        Schema::dropIfExists('digital_licenses');

        if (Schema::hasTable('product_variants') && Schema::hasColumn('product_variants', 'images')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->dropColumn('images');
            });
        }
    }
};
