<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Cms\PageBlockRenderer;
use App\Services\HtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CmsBlocksTest extends TestCase
{
    use RefreshDatabase;

    private function renderer(): PageBlockRenderer
    {
        return new PageBlockRenderer(new HtmlSanitizer);
    }

    public function test_text_block_strips_scripts_but_keeps_safe_tags(): void
    {
        $html = $this->renderer()->render([
            ['type' => 'text', 'html' => '<p>Halo <strong>dunia</strong></p><script>alert(1)</script>'],
        ]);

        $this->assertStringContainsString('<strong>dunia</strong>', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_hero_title_is_escaped_and_bad_url_rejected(): void
    {
        $html = $this->renderer()->render([
            ['type' => 'hero', 'title' => '<script>alert("x")</script>', 'button_label' => 'Klik', 'button_url' => 'javascript:alert(1)'],
        ]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_products_block_only_shows_published_approved(): void
    {
        // Self-contained: buat induk shop + kategori yang valid untuk FK sqlite.
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shopId = DB::table('shops')->insertGetId([
            'vendor_id' => $vendor->id, 'name' => 'Toko Uji', 'slug' => 'toko-uji',
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Kategori Uji', 'slug' => 'kategori-uji',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $visible = Product::create([
            'shop_id' => $shopId, 'category_id' => $categoryId,
            'name' => 'Tayang A', 'slug' => 'tayang-a',
            'price' => 50000, 'current_stock' => 5, 'status' => 'approved', 'published' => true,
        ]);
        $hidden = Product::create([
            'shop_id' => $shopId, 'category_id' => $categoryId,
            'name' => 'Sembunyi B', 'slug' => 'sembunyi-b',
            'price' => 60000, 'current_stock' => 5, 'status' => 'pending', 'published' => false,
        ]);

        $html = $this->renderer()->render([
            ['type' => 'products', 'title' => 'Pilihan', 'product_ids' => [$visible->id, $hidden->id], 'limit' => 4],
        ]);

        $this->assertStringContainsString('Tayang A', $html);
        $this->assertStringNotContainsString('Sembunyi B', $html);
    }

    public function test_gallery_drops_dangerous_schemes_and_faq_escaped(): void
    {
        $html = $this->renderer()->render([
            ['type' => 'gallery', 'images' => ['https://cdn.test/a.jpg', 'javascript:alert(1)']],
            ['type' => 'faq', 'title' => 'FAQ', 'items' => [['q' => '<b>Q?</b>', 'a' => 'Jawaban']]],
        ]);

        $this->assertStringContainsString('https://cdn.test/a.jpg', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('&lt;b&gt;Q?&lt;/b&gt;', $html);
    }

    public function test_normalize_rejects_unknown_types_and_caps_blocks(): void
    {
        $raw = array_merge(
            [['type' => 'nope'], ['type' => 'cta', 'title' => 'Promo', 'button_label' => 'Beli', 'button_url' => '/x']],
            array_fill(0, 40, ['type' => 'text', 'html' => '<p>x</p>'])
        );

        $normalized = PageBlockRenderer::normalize($raw);

        $this->assertCount(PageBlockRenderer::MAX_BLOCKS, $normalized);
        $this->assertSame('cta', $normalized[0]['type']);
    }

    public function test_blocks_roundtrip_through_system_setting(): void
    {
        $blocks = PageBlockRenderer::normalize([
            ['type' => 'text', 'html' => '<p>Tentang kami</p>'],
            ['type' => 'cta', 'title' => 'Gabung', 'button_label' => 'Mulai', 'button_url' => '/register'],
        ]);
        SystemSetting::set('page_blocks_about', json_encode($blocks, JSON_UNESCAPED_UNICODE));

        $loaded = PageBlockRenderer::blocksFor('about');
        $html = $this->renderer()->render('about');

        $this->assertCount(2, $loaded);
        $this->assertStringContainsString('Tentang kami', $html);
        $this->assertStringContainsString('Gabung', $html);
    }

    public function test_empty_blocks_render_empty_for_legacy_fallback(): void
    {
        $this->assertSame('', $this->renderer()->render([]));
        $this->assertSame([], PageBlockRenderer::blocksFor('about'));
    }
}
