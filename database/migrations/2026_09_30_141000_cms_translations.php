<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Terjemahan CMS: blog/page/banner/menu generik (aditif, backward-compatible).
 *
 * Inspeksi sebelum tulis:
 * - blog_posts (2026_06_09_000016): title/slug/content/excerpt/meta_*,
 *   is_published + published_at existing. Belum ada status per-locale.
 * - banners: title/subtitle/link existing (lihat cms_conversion).
 * - Halaman statis disimpan di SystemSetting key page_{slug} (lihat
 *   PageController::PAGES) — tidak ada tabel pages; maka page diterjemahkan
 *   via content_translations dengan subject_type='page', subject_key=slug.
 * - Menu tidak punya tabel/model (grep: tidak ada) — dicakup generik via
 *   content_translations subject_type='menu'.
 * - Belum ada blog_post_translations / content_translations.
 *
 * Status konten per bahasa (draft/published per locale) minimal untuk
 * page/blog: kolom status di kedua tabel baru (default 'published').
 * Rollback: drop kedua tabel (urutan terbalik).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('blog_post_translations')) {
            Schema::create('blog_post_translations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('blog_post_id')->constrained('blog_posts')->cascadeOnDelete();
                $table->string('locale', 12);
                $table->string('title', 255)->nullable();
                $table->string('slug', 255)->nullable();
                $table->text('excerpt')->nullable();
                $table->longText('content')->nullable();
                $table->string('meta_title', 255)->nullable();
                $table->text('meta_description')->nullable();
                $table->string('status', 20)->default('published');
                $table->timestamps();

                $table->unique(['blog_post_id', 'locale']);
                $table->index(['locale', 'slug']);
                $table->index(['locale', 'status']);
            });
        }

        if (! Schema::hasTable('content_translations')) {
            Schema::create('content_translations', function (Blueprint $table) {
                $table->id();
                // Generik: page (subject_key=slug halaman), banner (subject_id=banner id),
                // menu (subject_key=key menu), blog_category (subject_id=id).
                $table->string('subject_type', 80);
                $table->unsignedBigInteger('subject_id')->default(0);
                $table->string('subject_key', 190)->default('');
                $table->string('locale', 12);
                $table->string('title', 255)->nullable();
                $table->string('slug', 255)->nullable();
                $table->longText('body')->nullable();
                $table->string('meta_title', 255)->nullable();
                $table->text('meta_description')->nullable();
                $table->string('status', 20)->default('published');
                $table->timestamps();

                $table->unique(['subject_type', 'subject_id', 'subject_key', 'locale'], 'content_tr_unique');
                $table->index(['subject_type', 'locale', 'status'], 'content_tr_lookup_idx');
                $table->index(['locale', 'slug'], 'content_tr_slug_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_translations');
        Schema::dropIfExists('blog_post_translations');
    }
};
