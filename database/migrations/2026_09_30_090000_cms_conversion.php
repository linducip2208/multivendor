<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CMS konversi & promo (backward-compatible, aditif).
 *
 * Inspeksi sebelum tulis:
 * - banners (2026_06_09_000013): title, subtitle, image, link,
 *   position(hero|sidebar|footer|popup), sort_order, status. BELUM ada
 *   experiment_key/weight/impressions/clicks, starts_at/ends_at (kolom
 *   jadwal hanya dibaca defensif via Schema::hasColumn di BannerController).
 * - orders (2026_06_09_000009 + aditif s.d. 2026_09_30_070000): BELUM ada
 *   utm_source/utm_medium/utm_campaign (grep: tidak ada).
 * - campaigns (2026_09_28_000002): clicks/conversions/revenue existing.
 * - Belum ada tabel popups / landing_views (grep: tidak ada).
 *
 * Semua kolom baru nullable/ber-default sehingga baris lama tetap valid.
 * Tanpa mengubah kolom existing; tanpa menyentuh kalkulasi checkout.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('popups')) {
            Schema::create('popups', function (Blueprint $table) {
                $table->id();
                $table->string('title', 160);
                $table->text('body_html')->nullable();
                $table->string('image', 500)->nullable();
                $table->string('button_text', 80)->nullable();
                $table->string('button_link', 500)->nullable();
                $table->string('targeting', 20)->default('all');
                $table->unsignedSmallInteger('delay_seconds')->default(3);
                $table->unsignedSmallInteger('cap_days')->default(7);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('views_count')->default(0);
                $table->unsignedBigInteger('clicks_count')->default(0);
                $table->timestamps();

                $table->index(['is_active', 'targeting']);
            });
        }

        if (! Schema::hasTable('landing_views')) {
            Schema::create('landing_views', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('campaign_id')->nullable();
                $table->string('utm_source', 120)->nullable();
                $table->string('utm_medium', 120)->nullable();
                $table->string('utm_campaign', 120)->nullable();
                $table->string('session_id', 120)->nullable();
                $table->string('url', 500)->nullable();
                $table->timestamps();

                $table->index('campaign_id');
                $table->index('utm_campaign');
            });
        }

        if (Schema::hasTable('banners')) {
            Schema::table('banners', function (Blueprint $table) {
                if (! Schema::hasColumn('banners', 'experiment_key')) {
                    $table->string('experiment_key', 80)->nullable();
                }
                if (! Schema::hasColumn('banners', 'weight')) {
                    $table->unsignedInteger('weight')->default(100);
                }
                if (! Schema::hasColumn('banners', 'impressions')) {
                    $table->unsignedBigInteger('impressions')->default(0);
                }
                if (! Schema::hasColumn('banners', 'clicks')) {
                    $table->unsignedBigInteger('clicks')->default(0);
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'utm_source')) {
                    $table->string('utm_source', 120)->nullable();
                }
                if (! Schema::hasColumn('orders', 'utm_medium')) {
                    $table->string('utm_medium', 120)->nullable();
                }
                if (! Schema::hasColumn('orders', 'utm_campaign')) {
                    $table->string('utm_campaign', 120)->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $column) {
                    if (Schema::hasColumn('orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('banners')) {
            Schema::table('banners', function (Blueprint $table) {
                foreach (['experiment_key', 'weight', 'impressions', 'clicks'] as $column) {
                    if (Schema::hasColumn('banners', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('landing_views');
        Schema::dropIfExists('popups');
    }
};
