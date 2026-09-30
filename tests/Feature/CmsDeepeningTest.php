<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Cms\CmsFormAutomationService;
use App\Services\Cms\CmsFormService;
use App\Services\Cms\ContentWorkflowService;
use App\Services\Cms\MediaMetaService;
use App\Services\Cms\PageBlockRenderer;
use App\Services\HtmlSanitizer;
use App\Services\Localization\LanguageService;
use App\Services\Localization\TranslationRepository;
use App\Services\Seo\SitemapStatusService;
use App\Services\Theme\ThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsDeepeningTest extends TestCase
{
    use RefreshDatabase;

    private function renderer(): PageBlockRenderer
    {
        return new PageBlockRenderer(new HtmlSanitizer);
    }

    public function test_new_block_types_normalize_and_render_safely(): void
    {
        $blocks = PageBlockRenderer::normalize([
            ['type' => 'product-carousel', 'title' => 'Pilihan', 'limit' => 8, 'autoplay' => true],
            ['type' => 'testimonials', 'items' => [['name' => '<b>A</b>', 'text' => 'Bagus', 'rating' => 5]]],
            ['type' => 'countdown', 'title' => 'Promo', 'ends_at' => '2026-12-31 23:59:59', 'button_label' => 'Beli', 'button_url' => '/products'],
            ['type' => 'newsletter', 'title' => 'Kabar'],
            ['type' => 'map', 'title' => 'Toko', 'address' => 'Jl. Merdeka', 'stores' => [['name' => 'Cabang A', 'phone' => '0812']]],
            ['type' => 'pricing', 'title' => 'Paket', 'plans' => [
                ['name' => 'Basic', 'price' => 'Rp10rb', 'features' => ['A']],
                ['name' => 'Pro', 'price' => 'Rp20rb', 'features' => ['A', 'B']],
            ]],
            ['type' => 'faq-accordion', 'title' => 'FAQ', 'items' => [['q' => 'Q?', 'a' => 'A']]],
            ['type' => 'javascript:evil'],
        ]);

        $types = array_column($blocks, 'type');
        $this->assertContains('product-carousel', $types);
        $this->assertContains('testimonials', $types);
        $this->assertContains('countdown', $types);
        $this->assertContains('newsletter', $types);
        $this->assertContains('map', $types);
        $this->assertContains('pricing', $types);
        // faq-accordion dikanonik ke faq.
        $this->assertContains('faq', $types);
        $this->assertNotContains('javascript:evil', $types);

        $html = $this->renderer()->render($blocks);
        $this->assertStringContainsString('Kabar', $html);
        $this->assertStringContainsString('&lt;b&gt;A&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('javascript:', $html);

        // Map embed javascript ditolak (tak ada iframe berbahaya).
        $mapBad = PageBlockRenderer::normalize([['type' => 'map', 'title' => 'M', 'embed_url' => 'javascript:alert(1)']]);
        $this->assertStringNotContainsString('javascript:', $this->renderer()->render($mapBad));
        $this->assertSame('', $this->renderer()->render([['type' => 'map']]));
    }

    public function test_faq_and_howto_schemas(): void
    {
        $faq = ['type' => 'faq', 'items' => [['q' => 'Ongkir?', 'a' => 'Gratis']]];
        $schema = PageBlockRenderer::faqSchema($faq);
        $this->assertStringContainsString('FAQPage', $schema);
        $this->assertStringContainsString('Ongkir?', $schema);
        $this->assertSame('', PageBlockRenderer::faqSchema(['type' => 'faq', 'items' => []]));

        $pricing = ['type' => 'pricing', 'title' => 'Paket', 'plans' => [
            ['name' => 'A', 'price' => '10'], ['name' => 'B', 'price' => '20'],
        ]];
        $howto = PageBlockRenderer::howToSchema($pricing);
        $this->assertStringContainsString('HowTo', $howto);
        $this->assertSame('', PageBlockRenderer::howToSchema(['type' => 'pricing', 'plans' => [['name' => 'A']]]));

        $combined = PageBlockRenderer::aeoSchemas([$faq, $pricing]);
        $this->assertStringContainsString('FAQPage', $combined);
        $this->assertStringContainsString('HowTo', $combined);
    }

    public function test_workflow_transition_and_scheduled_auto_publish(): void
    {
        $workflow = app(ContentWorkflowService::class);

        $draft = $workflow->transition('page', 'about', 'id', 'draft');
        $this->assertSame('draft', $draft['state']);
        $this->assertFalse($draft['visible']);

        $review = $workflow->transition('page', 'about', 'en', 'review');
        $this->assertSame('review', $review['state']);
        $this->assertFalse($review['visible']);

        // Scheduled masa lalu -> terbaca published (terbit otomatis).
        $past = now()->subHour()->format('Y-m-d H:i:s');
        $scheduled = $workflow->transition('page', 'about', 'id', 'scheduled', $past);
        $this->assertSame('published', $scheduled['state']);
        $this->assertTrue($scheduled['visible']);

        $this->expectException(\InvalidArgumentException::class);
        $workflow->transition('page', 'about', 'id', 'scheduled', 'bukan-tanggal');
    }

    public function test_form_builder_validate_submit_and_automation(): void
    {
        $forms = app(CmsFormService::class);
        $automation = app(CmsFormAutomationService::class);

        $forms->saveForm('newsletter', 'Newsletter', [
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ['name' => 'nama', 'label' => 'Nama', 'type' => 'text', 'required' => false],
        ]);

        $bad = $forms->validateSubmission('newsletter', ['email' => 'bukan-email']);
        $this->assertFalse($bad['valid']);

        $good = $forms->validateSubmission('newsletter', ['email' => 'a@b.id', 'nama' => 'Budi']);
        $this->assertTrue($good['valid']);

        $id = $forms->submit('newsletter', $good['clean']);
        $this->assertGreaterThan(0, $id);
        $this->assertCount(1, $forms->submissions('newsletter'));

        $automation->saveRules('newsletter', [
            ['action' => 'email_log', 'template' => 'welcome', 'is_active' => true],
            ['action' => 'notify', 'message' => 'Ada pendaftar baru', 'is_active' => true],
        ]);
        $result = $automation->run('newsletter', $good['clean'], $id);
        $this->assertSame(2, $result['ran']);
        $this->assertTrue($result['results'][0]['ok']);
    }

    public function test_media_meta_tag_bulk_alt_and_inventory(): void
    {
        $meta = app(MediaMetaService::class);

        $meta->tag('uploads/a.jpg', ['promo', 'hero'], 'homepage', 'Banner promo');
        $meta->bulkAlt(['uploads/a.jpg' => 'Banner promo utama', 'uploads/b.jpg' => 'Foto produk']);

        $all = $meta->all();
        $this->assertSame('Banner promo utama', $all['uploads/a.jpg']['alt']);
        $this->assertContains('promo', $all['uploads/a.jpg']['tags']);

        $inventory = $meta->inventory();
        $this->assertArrayHasKey('items', $inventory);
        $this->assertArrayHasKey('unused', $inventory);
        $this->assertArrayHasKey('collections', $inventory);
    }

    public function test_language_add_default_and_coverage(): void
    {
        $languages = app(LanguageService::class);
        $repo = app(TranslationRepository::class);

        $repo->set('en', 'common', 'save', 'Save');
        $repo->set('id', 'common', 'save', 'Simpan');

        $languages->addLanguage('id', 'Indonesian', ['native_name' => 'Bahasa Indonesia']);
        $languages->addLanguage('en', 'English');
        $languages->addLanguage('ms', 'Malay', ['native_name' => 'Bahasa Melayu']);
        $this->assertTrue($languages->isRtl('ar'));
        $this->assertSame('ltr', $languages->direction('ms'));

        $def = $languages->setDefault('ms');
        $this->assertSame('ms', $def->code);
        $this->assertSame('ms', $languages->defaultCode());

        $summary = $languages->coverageSummary();
        $this->assertArrayHasKey('ms', $summary);
        // Kembali ke id agar test lain stabil.
        $languages->setDefault('id');
    }

    public function test_translation_import_export_roundtrip(): void
    {
        $repo = app(TranslationRepository::class);
        $count = $repo->importJson('en', ['common.hello' => 'Hello', 'common.bye' => 'Bye']);
        $this->assertSame(2, $count);

        $exported = $repo->exportJson('en');
        $this->assertSame('Hello', $exported['common.hello']);

        $csv = $repo->exportCsv('en');
        $this->assertStringContainsString('namespace,key,locale,value', $csv);

        $csvCount = $repo->importCsv('id', "namespace,key,locale,value\ncommon,hello,id,Halo");
        $this->assertSame(1, $csvCount);
        $this->assertSame('Halo', $repo->get('common.hello', 'id'));
    }

    public function test_theme_validate_snapshot_schedule(): void
    {
        $themes = app(ThemeManager::class);

        $this->assertTrue($themes->validate('default')['ok']);
        $this->assertFalse($themes->validate('../evil')['ok']);

        $snap = $themes->snapshot('uji', 1);
        $this->assertSame('uji', $snap['label']);
        $this->assertNotEmpty($themes->snapshots());

        $entry = $themes->scheduleActivation('default', now()->addHour()->format('Y-m-d H:i:s'));
        $this->assertSame('default', $entry['theme']);
        // Belum jatuh tempo -> null.
        $this->assertNull($themes->runScheduledActivation());
    }

    public function test_sitemap_hreflang_audit_structure(): void
    {
        $sitemaps = app(SitemapStatusService::class);

        $audit = $sitemaps->hreflangAudit();
        $this->assertArrayHasKey('default', $audit);
        $this->assertArrayHasKey('locales', $audit);
        $this->assertArrayHasKey('samples', $audit);
        $this->assertArrayHasKey('issues', $audit);
        $this->assertNotEmpty($audit['samples']);
        $first = $audit['samples'][0];
        $this->assertArrayHasKey('x-default', $first['alternates']);

        $indexes = $sitemaps->localizedIndexes();
        $this->assertArrayHasKey($audit['default'], $indexes);
    }
}
