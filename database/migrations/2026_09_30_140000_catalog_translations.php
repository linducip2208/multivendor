<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Terjemahan katalog: product/category/brand/shop (aditif, backward-compatible).
 *
 * Inspeksi sebelum tulis (D:\project laravel\multivendor):
 * - products (2026_06_09_000005): name/slug/description/short_description/
 *   meta_title/meta_description existing; slug UNIQUE di tabel induk.
 * - categories (2026_06_09_000002): name/slug/description existing; slug UNIQUE.
 * - brands (2026_06_09_000003): name/slug/description/meta_* existing.
 * - shops (2026_06_09_000001): name/slug/description/meta_* existing.
 * - Belum ada tabel *_translations (grep: tidak ada).
 * - redirects (2026_09_28_000004): from_path UNIQUE, to_path, status_code,
 *   is_active — dipakai untuk redirect slug lama (lihat Translatable service).
 * - phpunit.xml: sqlite :memory: — hanya string/text/integer/boolean.
 *
 * Skema tiap tabel terjemahan: id, {parent}_id FK cascade, locale(12),
 * name, slug, short_description/description, meta_title, meta_description,
 * timestamps, UNIQUE[{parent}_id,locale], INDEX[locale,slug].
 * Baris induk tetap sumber canonical + fallback (id).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_translations')) {
            Schema::create('product_translations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->string('locale', 12);
                $table->string('name', 255)->nullable();
                $table->string('slug', 255)->nullable();
                $table->text('short_description')->nullable();
                $table->longText('description')->nullable();
                $table->string('meta_title', 255)->nullable();
                $table->text('meta_description')->nullable();
                $table->timestamps();

                $table->unique(['product_id', 'locale']);
                $table->index(['locale', 'slug']);
            });
        }

        if (! Schema::hasTable('category_translations')) {
            Schema::create('category_translations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
                $table->string('locale', 12);
                $table->string('name', 255)->nullable();
                $table->string('slug', 255)->nullable();
                $table->text('description')->nullable();
                $table->string('meta_title', 255)->nullable();
                $table->text('meta_description')->nullable();
                $table->timestamps();

                $table->unique(['category_id', 'locale']);
                $table->index(['locale', 'slug']);
            });
        }

        if (! Schema::hasTable('brand_translations')) {
            Schema::create('brand_translations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
                $table->string('locale', 12);
                $table->string('name', 255)->nullable();
                $table->string('slug', 255)->nullable();
                $table->text('description')->nullable();
                $table->string('meta_title', 255)->nullable();
                $table->text('meta_description')->nullable();
                $table->timestamps();

                $table->unique(['brand_id', 'locale']);
                $table->index(['locale', 'slug']);
            });
        }

        if (! Schema::hasTable('shop_translations')) {
            Schema::create('shop_translations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
                $table->string('locale', 12);
                $table->string('name', 255)->nullable();
                $table->string('slug', 255)->nullable();
                $table->text('description')->nullable();
                $table->string('meta_title', 255)->nullable();
                $table->text('meta_description')->nullable();
                $table->timestamps();

                $table->unique(['shop_id', 'locale']);
                $table->index(['locale', 'slug']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_translations');
        Schema::dropIfExists('brand_translations');
        Schema::dropIfExists('category_translations');
        Schema::dropIfExists('product_translations');
    }
};
