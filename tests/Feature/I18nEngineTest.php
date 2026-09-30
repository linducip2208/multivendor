<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Localization\LanguageService;
use App\Services\Localization\LocaleNegotiator;
use App\Services\Localization\MissingDetector;
use App\Services\Localization\TranslationRepository;
use Database\Seeders\I18nBaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class I18nEngineTest extends TestCase
{
    use RefreshDatabase;

    private TranslationRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        MissingDetector::flushRuntime();

        $this->repo = app(TranslationRepository::class);
    }

    public function test_migration_creates_i18n_tables_with_constraints(): void
    {
        foreach (['languages', 'translation_groups', 'translation_keys', 'translations'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "tabel {$table} hilang");
        }

        foreach (['code', 'is_rtl', 'direction', 'is_active', 'is_default'] as $column) {
            $this->assertTrue(Schema::hasColumn('languages', $column), "kolom languages.{$column} hilang");
        }

        $this->assertTrue(Schema::hasColumn('translation_keys', 'namespace'), 'kolom translation_keys.namespace hilang');

        // Kolom legacy tetap ada (backward-compatible).
        foreach (['locale', 'group', 'key', 'value'] as $column) {
            $this->assertTrue(Schema::hasColumn('translations', $column), "kolom legacy translations.{$column} hilang");
        }

        // Kolom aditif engine.
        foreach (['language_id', 'translation_key_id', 'status', 'is_verified'] as $column) {
            $this->assertTrue(Schema::hasColumn('translations', $column), "kolom aditif translations.{$column} hilang");
        }

        // Unique composite legacy tetap terjaga.
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('translations')->insert([
            'locale' => 'en', 'group' => 'common', 'key' => 'dup',
            'value' => 'A', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('translations')->insert([
            'locale' => 'en', 'group' => 'common', 'key' => 'dup',
            'value' => 'B', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_fallback_chain_id_id_to_id_to_en(): void
    {
        $this->assertSame(['id-ID', 'id', 'en'], $this->repo->fallbackChain('id-ID'));
        $this->assertSame(['id', 'en'], $this->repo->fallbackChain('id'));
        $this->assertSame(['en'], $this->repo->fallbackChain('en'));

        $this->repo->set('en', 'common', 'only_en', 'English only');
        $this->repo->set('id', 'common', 'only_id', 'Hanya Indonesia');

        // id-ID jatuh ke id, lalu en.
        $this->assertSame('Hanya Indonesia', $this->repo->get('common.only_id', 'id-ID'));
        $this->assertSame('English only', $this->repo->get('common.only_en', 'id-ID'));
        $this->assertSame('English only', $this->repo->get('common.only_en', 'id'));

        // Prioritas: nilai id-ID menang atas id & en.
        $this->repo->set('id-ID', 'common', 'save', 'Simpan ID-ID');
        $this->repo->set('id', 'common', 'save', 'Simpan');
        $this->repo->set('en', 'common', 'save', 'Save');
        $this->assertSame('Simpan ID-ID', $this->repo->get('common.save', 'id-ID'));

        // Replacements.
        $this->repo->set('id', 'common', 'welcome', 'Halo, :name');
        $this->assertSame('Halo, Budi', $this->repo->get('common.welcome', 'id', ['name' => 'Budi']));
    }

    public function test_unlimited_languages_with_rtl_flag(): void
    {
        $service = app(LanguageService::class);

        $service->register('ar', 'Arabic', 'العربية');
        $service->register('xx-pirate', 'Pirate', 'Pirate');

        $this->assertContains('ar', $service->activeCodes());
        $this->assertNotNull($service->find('xx-pirate'));
        $this->assertContains('xx-PIRATE', $service->activeCodes());
        $this->assertTrue($service->isRtl('ar'));
        $this->assertSame('rtl', $service->direction('ar'));
        $this->assertFalse($service->isRtl('id'));
        $this->assertSame('ltr', $service->direction('en'));

        $this->repo->set('ar', 'common', 'save', 'حفظ');
        $this->assertSame('حفظ', $this->repo->get('common.save', 'ar'));
    }

    public function test_locale_negotiator_priority_session_user_browser(): void
    {
        $service = app(LanguageService::class);
        $service->register('id', 'Indonesian', 'Bahasa Indonesia');
        $service->register('en', 'English', 'English');

        $negotiator = app(LocaleNegotiator::class);

        // Session menang atas segalanya.
        $this->assertSame('en', $negotiator->negotiate('en', 'id', 'id-ID,id;q=0.9', 'id'));
        // Tanpa session: user menang atas browser.
        $this->assertSame('id', $negotiator->negotiate(null, 'id', 'en;q=0.9', 'en'));
        // Tanpa session+user: Accept-Language dengan q-value.
        $this->assertSame('id', $negotiator->negotiate(null, null, 'en;q=0.5,id;q=0.9', 'en'));
        // Basis fallback: id-ID tersedia sebagai id.
        $this->assertSame('id', $negotiator->negotiate(null, null, 'id-ID,id;q=0.8', 'en'));
        // Tidak cocok: kembali ke default.
        $this->assertSame('en', $negotiator->negotiate(null, null, 'fr-FR,fr;q=0.9', 'en'));

        $this->assertSame(['id-ID', 'id', 'en'], $negotiator->parseAcceptLanguage('id-ID,id;q=0.8,en;q=0.5'));
    }

    public function test_missing_detector_and_coverage_report(): void
    {
        $this->repo->set('en', 'common', 'a', 'A');
        $this->repo->set('en', 'common', 'b', 'B');
        $this->repo->set('id', 'common', 'a', 'A-id');

        $detector = app(MissingDetector::class);
        $missing = $detector->missingKeys('id');

        $this->assertCount(1, $missing);
        $this->assertSame('common.b', $missing[0]['full']);

        $coverage = $this->repo->coverage('id');
        $this->assertSame(2, $coverage['total_keys']);
        $this->assertSame(1, $coverage['translated']);
        $this->assertSame(50.0, $coverage['percent']);
        $this->assertArrayHasKey('common', $coverage['per_namespace']);

        $report = $detector->report('id');
        $this->assertSame(1, $report['missing_count']);
        $this->assertSame(50.0, $report['coverage']['percent']);

        // Runtime miss tercatat saat get() gagal di semua fallback.
        $this->assertSame('common.missing_key', $this->repo->get('common.missing_key', 'id'));
        $this->assertNotEmpty($detector->runtimeMisses());
    }

    public function test_import_export_json_csv_roundtrip(): void
    {
        $this->repo->set('id', 'common', 'save', 'Simpan');
        $this->repo->set('id', 'validation', 'required', 'Wajib diisi.');

        $json = $this->repo->exportJson('id');
        $this->assertSame('Simpan', $json['common.save']);

        $csv = $this->repo->exportCsv('id');
        $this->assertStringContainsString('namespace,key,locale,value', $csv);
        $this->assertStringContainsString('common,save,id,Simpan', $csv);

        $imported = $this->repo->importJson('en', ['common.save' => 'Save', 'validation.required' => 'Required.']);
        $this->assertSame(2, $imported);
        $this->assertSame('Save', $this->repo->get('common.save', 'en'));

        $count = $this->repo->importCsv('en', "namespace,key,locale,value\ncommon,hello,en,Hello\n");
        $this->assertSame(1, $count);
        $this->assertSame('Hello', $this->repo->get('common.hello', 'en'));
    }

    public function test_invalid_namespace_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->repo->set('en', 'not_a_namespace', 'x', 'X');
    }

    public function test_base_seeder_seeds_id_and_en(): void
    {
        (new I18nBaseSeeder)->run();

        $this->assertSame('Simpan', $this->repo->get('common.save', 'id'));
        $this->assertSame('Save', $this->repo->get('common.save', 'en'));
        $this->assertStringContainsString('wajib diisi', $this->repo->get('validation.required', 'id'));
        $this->assertStringContainsString('required', $this->repo->get('validation.required', 'en'));

        $coverage = $this->repo->coverage('id');
        $this->assertGreaterThanOrEqual(20, $coverage['total_keys']);
        $this->assertSame(100.0, $coverage['percent']);
    }
}
