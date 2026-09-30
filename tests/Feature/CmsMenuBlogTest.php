<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\BlogController;
use App\Models\BlogPost;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Cms\MenuRenderer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * CMS: menu builder visual (nested 1 level + validasi URL) + render
 * storefront + jadwal terbit blog.
 *
 * Self-contained: membangun tabelnya sendiri di sqlite :memory: dan
 * membersihkannya kembali (pola CatalogMediaTest). Tidak memakai
 * RefreshDatabase agar tahan terhadap migrasi proyek lain.
 */
class CmsMenuBlogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table): void {
            $table->id();
            $table->integer('author_id')->nullable();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content');
            $table->text('excerpt')->nullable();
            $table->string('featured_image')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();
        });

        MenuRenderer::flushAll();
    }

    protected function tearDown(): void
    {
        MenuRenderer::flushAll();
        Cache::flush();
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('system_settings');

        parent::tearDown();
    }

    private function admin(): User
    {
        $admin = new User([
            'name' => 'Admin CMS',
            'email' => 'admin-cms@multivendor.test',
            'role' => 'admin',
            'status' => 'active',
        ]);
        $admin->id = 1;

        return $admin;
    }

    // ---------- MenuRenderer ----------

    public function test_renderer_normalisasi_bersarang_dan_menolak_url_tidak_aman(): void
    {
        SystemSetting::set('menu_main', json_encode([
            ['label' => 'Beranda', 'url' => '/', 'target' => '_self'],
            ['label' => 'Promo', 'url' => '/promo', 'children' => [
                ['label' => 'Flash <Sale>', 'url' => '/promo/flash', 'target' => '_blank'],
                ['label' => 'Kosong', 'url' => ''],
            ]],
            ['label' => 'Jahat', 'url' => 'javascript:alert(1)'],
            ['label' => '', 'url' => '/tanpa-label'],
        ], JSON_UNESCAPED_UNICODE));

        $items = MenuRenderer::items('main');

        $this->assertCount(2, $items);
        $this->assertSame('Beranda', $items[0]['label']);
        $this->assertCount(1, $items[1]['children']);
        $this->assertSame('Flash <Sale>', $items[1]['children'][0]['label']);
        $this->assertSame('_blank', $items[1]['children'][0]['target']);
    }

    public function test_renderer_menghasilkan_ul_bersarang_escape_dan_tandai_aktif(): void
    {
        SystemSetting::set('menu_main', json_encode([
            ['label' => '<b>Promo</b>', 'url' => '/promo', 'children' => [
                ['label' => 'Anak', 'url' => '/promo'],
            ]],
        ], JSON_UNESCAPED_UNICODE));

        $html = MenuRenderer::render('main', ['ul_class' => 'tes-menu']);

        $this->assertStringContainsString('<ul class="tes-menu">', $html);
        $this->assertStringContainsString('<ul class="sf-menu__submenu">', $html);
        $this->assertStringNotContainsString('<b>Promo</b>', $html);
        $this->assertStringContainsString('&lt;b&gt;Promo&lt;/b&gt;', $html);

        // Aktif-state: request ke /promo menandai tautan aktif.
        $this->get('/promo');
        $htmlAktif = MenuRenderer::render('main');
        $this->assertStringContainsString('is-active', $htmlAktif);
        $this->assertStringContainsString('aria-current="page"', $htmlAktif);
    }

    public function test_renderer_cache_satu_jam_dan_bisa_flush(): void
    {
        SystemSetting::set('menu_footer', json_encode([['label' => 'Awal', 'url' => '/awal']]));

        $this->assertStringContainsString('Awal', MenuRenderer::render('footer'));

        SystemSetting::set('menu_footer', json_encode([['label' => 'Baru', 'url' => '/baru']]));
        // Masih cache lama.
        $this->assertStringContainsString('Awal', MenuRenderer::render('footer'));

        MenuRenderer::flush('footer');
        $this->assertStringContainsString('Baru', MenuRenderer::render('footer'));
    }

    public function test_renderer_kosong_agar_fallback_statis_tetap_tampil(): void
    {
        $this->assertSame([], MenuRenderer::items('main'));
        $this->assertSame('', MenuRenderer::render('main'));
        $this->assertSame([], MenuRenderer::items('header'), 'alias header -> main');
    }

    // ---------- Admin menu builder (updateMenus) ----------

    public function test_update_menus_menyimpan_children_dan_target(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $response = $this->from('/admin/menus')->put('/admin/menus', [
            'key' => 'main',
            'items' => [
                ['label' => 'Promo', 'url' => '/promo', 'target' => '_self', 'children' => [
                    ['label' => 'Flash Sale', 'url' => 'https://example.com/flash', 'target' => '_blank'],
                ]],
            ],
        ]);

        $response->assertRedirect('/admin/menus');
        $response->assertSessionHasNoErrors();

        $stored = json_decode((string) SystemSetting::get('menu_main'), true);
        $this->assertSame('Promo', $stored[0]['label']);
        $this->assertSame('Flash Sale', $stored[0]['children'][0]['label']);
        $this->assertSame('_blank', $stored[0]['children'][0]['target']);
    }

    public function test_update_menus_menolak_url_javascript(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $response = $this->from('/admin/menus')->put('/admin/menus', [
            'key' => 'main',
            'items' => [['label' => 'Jahat', 'url' => 'javascript:alert(1)']],
        ]);

        $response->assertSessionHasErrors('items.0.url');
        $this->assertNull(SystemSetting::get('menu_main'));
    }

    public function test_update_menus_membatasi_maksimal_50_item(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $items = [];
        for ($i = 0; $i < 51; $i++) {
            $items[] = ['label' => 'Item '.$i, 'url' => '/item-'.$i];
        }

        $response = $this->from('/admin/menus')->put('/admin/menus', ['key' => 'main', 'items' => $items]);

        $response->assertSessionHasErrors('items');
    }

    // ---------- Jadwal terbit blog ----------

    public function test_status_jadwal_draf_terjadwal_terbit(): void
    {
        $draf = new BlogPost(['is_published' => false, 'published_at' => null]);
        $terjadwal = new BlogPost(['is_published' => true, 'published_at' => now()->addDay()]);
        $terbit = new BlogPost(['is_published' => true, 'published_at' => now()->subMinute()]);

        $this->assertSame('Draf', BlogController::scheduleStatus($draf)['label']);
        $this->assertSame('warning', BlogController::scheduleStatus($terjadwal)['color']);
        $this->assertSame('Terjadwal', BlogController::scheduleStatus($terjadwal)['label']);
        $this->assertSame('Terbit', BlogController::scheduleStatus($terbit)['label']);
    }

    public function test_storefront_menyembunyikan_terjadwal_tanpa_command(): void
    {
        // Bukti: PageController memakai filter published_at<=now sehingga
        // penerbitan terjadi otomatis — tidak ada command/scheduler baru.
        $pageController = file_get_contents(app_path('Http/Controllers/Storefront/PageController.php'));
        $this->assertStringContainsString("->where('published_at', '<=', now())", $pageController);

        BlogPost::create([
            'title' => 'Sudah Terbit', 'slug' => 'sudah-terbit', 'content' => 'x',
            'is_published' => true, 'published_at' => now()->subHour(),
        ]);
        BlogPost::create([
            'title' => 'Terjadwal', 'slug' => 'terjadwal', 'content' => 'x',
            'is_published' => true, 'published_at' => now()->addDay(),
        ]);

        $terlihat = BlogPost::query()
            ->where('is_published', true)
            ->where('published_at', '<=', now())
            ->pluck('slug')
            ->all();

        $this->assertContains('sudah-terbit', $terlihat);
        $this->assertNotContains('terjadwal', $terlihat);
    }

    public function test_store_blog_menerima_jadwal_masa_depan(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $jadwal = now()->addDays(2)->format('Y-m-d\TH:i');
        $response = $this->post('/admin/blog', [
            'title' => 'Artikel Terjadwal',
            'content' => '<p>Halo</p>',
            'is_published' => '1',
            'published_at' => $jadwal,
        ]);

        $response->assertRedirect(route('admin.blog.index'));
        $post = BlogPost::where('slug', 'artikel-terjadwal')->firstOrFail();

        $this->assertTrue($post->is_published);
        $this->assertTrue($post->published_at->isFuture());
        $this->assertSame('Terjadwal', BlogController::scheduleStatus($post)['label']);
    }
}
