<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mesin i18n database-driven (aditif, backward-compatible).
 *
 * Inspeksi sebelum tulis (D:\project laravel\multivendor):
 * - 2026_06_09_000025_create_vat_translation_delivery.php SUDAH membuat tabel
 *   `translations` (id, locale(10), group(50), key, value nullable, timestamps,
 *   unique[locale,group,key]). Model App\Models\Translation memakai kolom itu.
 *   => Migrasi ini TIDAK membuat ulang `translations`; hanya ALTER aditif bila
 *   kolom/index belum ada.
 * - Belum ada tabel `languages` / `translation_groups` / `translation_keys`
 *   (grep: tidak ada). => Dibuat baru di sini.
 * - config/app.php: locale env APP_LOCALE default 'en', fallback 'en'.
 *   Engine menambah fallback chain id-ID -> id -> en di service layer.
 * - phpunit.xml: sqlite :memory: untuk test. Hindari tipe eksotis; string +
 *   boolean + integer saja agar lolos sqlite & mysql.
 *
 * Skema akhir:
 * - languages(id, code UNIQUE, name, native_name, direction ltr|rtl,
 *   is_rtl bool, is_active bool, is_default bool, sort_order, timestamps)
 * - translation_groups(id, slug UNIQUE, description, timestamps)
 * - translation_keys(id, namespace(50), key(190), group_id FK nullable,
 *   description, default_text, timestamps, UNIQUE[namespace,key], INDEX namespace)
 * - translations: legacy dipertahankan + kolom aditif nullable:
 *   language_id FK nullable, translation_key_id FK nullable,
 *   status(20) default 'published', is_verified bool default false,
 *   updated_by bigint nullable; INDEX[translation_key_id,locale],
 *   INDEX[language_id].
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('languages')) {
            Schema::create('languages', function (Blueprint $table) {
                $table->id();
                $table->string('code', 16)->unique();
                $table->string('name', 80);
                $table->string('native_name', 80)->nullable();
                $table->string('direction', 3)->default('ltr');
                $table->boolean('is_rtl')->default(false);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->index(['is_active', 'sort_order']);
            });
        }

        if (! Schema::hasTable('translation_groups')) {
            Schema::create('translation_groups', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 50)->unique();
                $table->string('description', 255)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('translation_keys')) {
            Schema::create('translation_keys', function (Blueprint $table) {
                $table->id();
                $table->string('namespace', 50);
                $table->string('key', 190);
                $table->foreignId('group_id')->nullable()->constrained('translation_groups')->nullOnDelete();
                $table->string('description', 255)->nullable();
                $table->text('default_text')->nullable();
                $table->timestamps();

                $table->unique(['namespace', 'key']);
                $table->index('namespace');
            });
        }

        if (! Schema::hasTable('translations')) {
            // Fresh install (tanpa migrasi legacy): buat penuh legacy + aditif.
            Schema::create('translations', function (Blueprint $table) {
                $table->id();
                $table->string('locale', 16);
                $table->string('group', 50);
                $table->string('key', 190);
                $table->text('value')->nullable();
                $table->foreignId('language_id')->nullable()->constrained('languages')->nullOnDelete();
                $table->foreignId('translation_key_id')->nullable()->constrained('translation_keys')->nullOnDelete();
                $table->string('status', 20)->default('published');
                $table->boolean('is_verified')->default(false);
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->unique(['locale', 'group', 'key']);
                $table->index(['translation_key_id', 'locale']);
                $table->index('language_id');
            });

            return;
        }

        // Instalasi existing: ALTER aditif saja.
        Schema::table('translations', function (Blueprint $table) {
            if (! Schema::hasColumn('translations', 'language_id')) {
                $table->foreignId('language_id')->nullable()->after('value')->constrained('languages')->nullOnDelete();
            }
            if (! Schema::hasColumn('translations', 'translation_key_id')) {
                $table->foreignId('translation_key_id')->nullable()->after('language_id')->constrained('translation_keys')->nullOnDelete();
            }
            if (! Schema::hasColumn('translations', 'status')) {
                $table->string('status', 20)->default('published')->after('translation_key_id');
            }
            if (! Schema::hasColumn('translations', 'is_verified')) {
                $table->boolean('is_verified')->default(false)->after('status');
            }
            if (! Schema::hasColumn('translations', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('is_verified');
            }
        });

        $this->addIndexIfMissing('translations', 'i18n_translations_key_locale_idx', ['translation_key_id', 'locale']);
        $this->addIndexIfMissing('translations', 'translations_language_id_index', ['language_id']);
    }

    public function down(): void
    {
        // Hanya rollback milik migrasi ini; tabel legacy `translations`
        // dipertahankan bila kolom legacy masih dibutuhkan migrasi lama.
        if (Schema::hasTable('translations')) {
            $this->dropIndexIfExists('translations', 'i18n_translations_key_locale_idx');
            // Index auto-created oleh foreignId language_id dibiarkan di-drop
            // bersama kolomnya di bawah.

            Schema::table('translations', function (Blueprint $table) {
                // Lepas FK dulu agar dropColumn aman di mysql & sqlite.
                try {
                    $table->dropForeign(['language_id']);
                } catch (\Throwable) {
                }
                try {
                    $table->dropForeign(['translation_key_id']);
                } catch (\Throwable) {
                }

                foreach (['language_id', 'translation_key_id', 'status', 'is_verified', 'updated_by'] as $column) {
                    if (Schema::hasColumn('translations', $column)) {
                        try {
                            $table->dropColumn($column);
                        } catch (\Throwable) {
                        }
                    }
                }
            });
        }

        Schema::dropIfExists('translation_keys');
        Schema::dropIfExists('translation_groups');
        Schema::dropIfExists('languages');
    }

    /**
     * @param  list<string>  $columns
     */
    private function addIndexIfMissing(string $table, string $name, array $columns): void
    {
        try {
            $indexes = Schema::getIndexes($table);
            foreach ($indexes as $index) {
                $indexName = is_array($index) ? ($index['name'] ?? '') : (string) ($index->name ?? '');
                if ($indexName === $name) {
                    return;
                }
            }
            Schema::table($table, function (Blueprint $t) use ($columns, $name) {
                $t->index($columns, $name);
            });
        } catch (\Throwable) {
        }
    }

    private function dropIndexIfExists(string $table, string $name): void
    {
        try {
            Schema::table($table, function (Blueprint $t) use ($name) {
                $t->dropIndex($name);
            });
        } catch (\Throwable) {
        }
    }
};
