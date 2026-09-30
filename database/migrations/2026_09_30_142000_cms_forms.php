<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Form builder CMS: definisi form + submissions (aditif).
 *
 * Inspeksi sebelum tulis:
 * - Belum ada tabel cms_forms / cms_form_submissions (grep: tidak ada).
 * - CmsFormService memakai tabel ini bila ada, fallback SystemSetting
 *   bila belum migrate — maka migrasi aman di semua env.
 * - Media meta + workflow + theme snapshot memakai SystemSetting
 *   (tanpa tabel baru) agar jatah MAKS 1 migrasi dipakai di sini.
 *
 * Rollback: drop submissions lalu forms.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_forms')) {
            Schema::create('cms_forms', function (Blueprint $table) {
                $table->id();
                $table->string('key', 80)->unique();
                $table->string('title', 160);
                $table->json('fields_json')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('is_active');
            });
        }

        if (! Schema::hasTable('cms_form_submissions')) {
            Schema::create('cms_form_submissions', function (Blueprint $table) {
                $table->id();
                $table->string('form_key', 80)->index();
                $table->foreignId('form_id')->nullable()->constrained('cms_forms')->nullOnDelete();
                $table->json('payload_json')->nullable();
                $table->timestamps();

                $table->index(['form_key', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_form_submissions');
        Schema::dropIfExists('cms_forms');
    }
};
