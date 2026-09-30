<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed dasar mesin i18n (BARU; tidak mengubah seeder existing).
 *
 * - Bahasa: id (default) + en.
 * - Namespace: common.* + validation.* dasar.
 * Dijalankan manual: php artisan db:seed --class=Database\\Seeders\\I18nBaseSeeder
 */
class I18nBaseSeeder extends Seeder
{
    /**
     * @return array<string, array<string, string>> locale => [fullKey => value]
     */
    public static function dataset(): array
    {
        return [
            'id' => [
                'common.app_name' => 'Pasar Multi-Vendor',
                'common.save' => 'Simpan',
                'common.cancel' => 'Batal',
                'common.delete' => 'Hapus',
                'common.edit' => 'Ubah',
                'common.search' => 'Cari',
                'common.cart' => 'Keranjang',
                'common.checkout' => 'Checkout',
                'common.welcome' => 'Selamat datang, :name',
                'common.currency' => 'Rupiah',
                'common.language' => 'Bahasa',
                'common.home' => 'Beranda',
                'validation.required' => 'Kolom :attribute wajib diisi.',
                'validation.email' => 'Kolom :attribute harus berupa alamat email yang valid.',
                'validation.min.string' => 'Kolom :attribute minimal :min karakter.',
                'validation.max.string' => 'Kolom :attribute maksimal :max karakter.',
                'validation.confirmed' => 'Konfirmasi kolom :attribute tidak cocok.',
                'validation.unique' => 'Kolom :attribute sudah digunakan.',
                'validation.numeric' => 'Kolom :attribute harus berupa angka.',
                'validation.date' => 'Kolom :attribute bukan tanggal yang valid.',
            ],
            'en' => [
                'common.app_name' => 'Multi-Vendor Market',
                'common.save' => 'Save',
                'common.cancel' => 'Cancel',
                'common.delete' => 'Delete',
                'common.edit' => 'Edit',
                'common.search' => 'Search',
                'common.cart' => 'Cart',
                'common.checkout' => 'Checkout',
                'common.welcome' => 'Welcome, :name',
                'common.currency' => 'Rupiah',
                'common.language' => 'Language',
                'common.home' => 'Home',
                'validation.required' => 'The :attribute field is required.',
                'validation.email' => 'The :attribute must be a valid email address.',
                'validation.min.string' => 'The :attribute must be at least :min characters.',
                'validation.max.string' => 'The :attribute may not be greater than :max characters.',
                'validation.confirmed' => 'The :attribute confirmation does not match.',
                'validation.unique' => 'The :attribute has already been taken.',
                'validation.numeric' => 'The :attribute must be a number.',
                'validation.date' => 'The :attribute is not a valid date.',
            ],
        ];
    }

    public function run(): void
    {
        $this->seedLanguages();
        $this->seedTranslations();

        Cache::forget('i18n:languages:active');
        foreach (['id', 'en'] as $locale) {
            Cache::forget('i18n:'.$locale.':*');
        }
    }

    private function seedLanguages(): void
    {
        if (! Schema::hasTable('languages')) {
            return;
        }

        foreach ([
            ['code' => 'id', 'name' => 'Indonesian', 'native_name' => 'Bahasa Indonesia', 'direction' => 'ltr', 'is_rtl' => false, 'is_active' => true, 'is_default' => true, 'sort_order' => 0],
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'is_rtl' => false, 'is_active' => true, 'is_default' => false, 'sort_order' => 1],
        ] as $row) {
            Language::updateOrCreate(['code' => $row['code']], $row + ['created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function seedTranslations(): void
    {
        if (! Schema::hasTable('translations')) {
            return;
        }

        $hasKeyId = Schema::hasColumn('translations', 'translation_key_id');
        $hasLangId = Schema::hasColumn('translations', 'language_id');

        $languageIds = [];
        if ($hasLangId && Schema::hasTable('languages')) {
            $languageIds = DB::table('languages')->pluck('id', 'code')->all();
        }

        foreach (self::dataset() as $locale => $pairs) {
            foreach ($pairs as $full => $value) {
                $pos = strpos($full, '.');
                $namespace = $pos === false ? 'common' : substr($full, 0, $pos);
                $key = $pos === false ? $full : substr($full, $pos + 1);

                $keyId = null;
                if (Schema::hasTable('translation_keys')) {
                    $keyId = DB::table('translation_keys')->where('namespace', $namespace)->where('key', $key)->value('id');
                    if ($keyId === null) {
                        $keyId = DB::table('translation_keys')->insertGetId([
                            'namespace' => $namespace,
                            'key' => $key,
                            'group_id' => Schema::hasTable('translation_groups')
                                ? DB::table('translation_groups')->where('slug', $namespace)->value('id')
                                : null,
                            'default_text' => $value,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                $row = [
                    'locale' => $locale,
                    'group' => $namespace,
                    'key' => $key,
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if ($hasKeyId) {
                    $row['translation_key_id'] = $keyId;
                }
                if ($hasLangId) {
                    $row['language_id'] = $languageIds[$locale] ?? null;
                }

                DB::table('translations')->updateOrInsert(
                    ['locale' => $locale, 'group' => $namespace, 'key' => $key],
                    $row
                );
            }
        }
    }
}
