<?php

namespace Tests\Feature;

use App\Http\Middleware\CaptureUtm;
use App\Services\Cms\BannerExperimentService;
use App\Services\Cms\LandingTrackingService;
use App\Services\Cms\PopupService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * CMS konversi & promo: popup builder + A/B banner + landing UTM.
 *
 * Self-contained: membangun tabelnya sendiri di sqlite :memory: dan
 * membersihkannya kembali (pola CmsMenuBlogTest). Tidak memakai
 * RefreshDatabase agar tahan terhadap migrasi proyek lain.
 */
class CmsConversionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('popups', function (Blueprint $table): void {
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
        });

        Schema::create('banners', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('image');
            $table->string('link')->nullable();
            $table->string('position')->default('hero');
            $table->integer('sort_order')->default(0);
            $table->boolean('status')->default(true);
            $table->string('experiment_key', 80)->nullable();
            $table->unsignedInteger('weight')->default(100);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->timestamps();
        });

        Schema::create('landing_views', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->string('utm_source', 120)->nullable();
            $table->string('utm_medium', 120)->nullable();
            $table->string('utm_campaign', 120)->nullable();
            $table->string('session_id', 120)->nullable();
            $table->string('url', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number')->unique();
            $table->decimal('total', 12, 2)->default(0);
            $table->string('utm_source', 120)->nullable();
            $table->string('utm_medium', 120)->nullable();
            $table->string('utm_campaign', 120)->nullable();
            $table->timestamps();
        });

        PopupService::flush();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        PopupService::flush();
        Cache::flush();
        Schema::dropIfExists('orders');
        Schema::dropIfExists('landing_views');
        Schema::dropIfExists('banners');
        Schema::dropIfExists('popups');

        parent::tearDown();
    }

    // ---------- 1. Popup builder ----------

    public function test_popup_sanitasi_membuang_script_dan_href_berbahaya(): void
    {
        $dirty = '<p>Halo <strong>dunia</strong></p><script>alert(1)</script>'
            .'<a href="javascript:alert(2)" onclick="evil()">Klik</a>'
            .'<a href="/promo">Promo</a>';

        $clean = PopupService::sanitize($dirty);

        $this->assertStringContainsString('<p>Halo <strong>dunia</strong></p>', $clean);
        $this->assertStringNotContainsString('<script>', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringContainsString('<a href="/promo">Promo</a>', $clean);
    }

    public function test_popup_aktif_menghormati_jadwal_targeting_dan_status(): void
    {
        DB::table('popups')->insert([
            'title' => 'Promo Home', 'body_html' => '<p>Diskon!</p>',
            'targeting' => 'home', 'is_active' => true,
            'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('popups')->insert([
            'title' => 'Nonaktif', 'targeting' => 'all', 'is_active' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('popups')->insert([
            'title' => 'Kadaluarsa', 'targeting' => 'all', 'is_active' => true,
            'starts_at' => now()->subDays(3), 'ends_at' => now()->subDay(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $home = PopupService::activeForPage('home');
        $this->assertNotNull($home);
        $this->assertSame('Promo Home', $home['title']);

        // Halaman checkout: popup targeting home tidak ikut.
        PopupService::flush('checkout');
        $this->assertNull(PopupService::activeForPage('checkout'));
    }

    public function test_popup_counter_tayang_dan_klik_bertambah(): void
    {
        $id = (int) DB::table('popups')->insertGetId([
            'title' => 'Counter', 'targeting' => 'all', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        PopupService::recordView($id);
        PopupService::recordView($id);
        PopupService::recordClick($id);

        $row = DB::table('popups')->find($id);

        $this->assertSame(2, (int) $row->views_count);
        $this->assertSame(1, (int) $row->clicks_count);
    }

    // ---------- 2. A/B banner ----------

    public function test_banner_pick_terbobot_dan_laporan_ctr_dengan_pemenang(): void
    {
        DB::table('banners')->insert([
            'title' => 'Varian A', 'image' => '/a.jpg', 'status' => true,
            'experiment_key' => 'hero-q4', 'weight' => 75,
            'impressions' => 200, 'clicks' => 10,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('banners')->insert([
            'title' => 'Varian B', 'image' => '/b.jpg', 'status' => true,
            'experiment_key' => 'hero-q4', 'weight' => 25,
            'impressions' => 200, 'clicks' => 40,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Pick selalu salah satu varian grup (bobot 75:25, 40x coba toleran).
        $titles = [];
        for ($i = 0; $i < 40; $i++) {
            $pick = BannerExperimentService::pick('hero-q4');
            $this->assertNotNull($pick);
            $titles[] = $pick['title'];
        }
        $this->assertContains('Varian A', $titles);
        $this->assertContains('Varian B', $titles);

        $report = BannerExperimentService::report('hero-q4');

        $this->assertCount(2, $report['variants']);
        $this->assertSame(5.0, $report['variants'][0]['ctr']);
        $this->assertSame(20.0, $report['variants'][1]['ctr']);

        $winnerId = (int) DB::table('banners')->where('title', 'Varian B')->value('id');
        $this->assertSame($winnerId, $report['winner_id']);

        // Min. sampel belum terpenuhi → belum ada pemenang.
        $this->assertNull(BannerExperimentService::report('hero-q4', 500)['winner_id']);
    }

    public function test_banner_impresi_dan_klik_tercatat(): void
    {
        $id = (int) DB::table('banners')->insertGetId([
            'title' => 'Ukur', 'image' => '/u.jpg', 'status' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        BannerExperimentService::recordImpression($id);
        BannerExperimentService::recordClick($id);

        $row = DB::table('banners')->find($id);

        $this->assertSame(1, (int) $row->impressions);
        $this->assertSame(1, (int) $row->clicks);
        $this->assertSame(100.0, BannerExperimentService::ctr(1, 1));
        $this->assertSame(0.0, BannerExperimentService::ctr(0, 0));
    }

    // ---------- 3. Landing + UTM ----------

    public function test_capture_utm_hanya_menyimpan_yang_ada_di_query(): void
    {
        $middleware = new CaptureUtm;
        $request = Request::create('/?utm_source=google&utm_campaign=ramadan', 'GET');
        $request->setLaravelSession(app('session.store'));

        $middleware->handle($request, fn ($req) => response('ok'));

        $this->assertSame('google', session('utm.source'));
        $this->assertSame('ramadan', session('utm.campaign'));
        $this->assertNull(session('utm.medium'));

        // Query tanpa UTM tidak menimpa sesi yang sudah ada.
        $request2 = Request::create('/', 'GET');
        $request2->setLaravelSession(app('session.store'));
        $middleware->handle($request2, fn ($req) => response('ok'));

        $this->assertSame('google', session('utm.source'));
    }

    public function test_landing_agregat_tayang_menjadi_order_dan_omzet(): void
    {
        LandingTrackingService::recordView(null, [
            'utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'ramadan',
        ], 'sess-1', '/promo/ramadan');
        LandingTrackingService::recordView(null, [
            'utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'ramadan',
        ], 'sess-2', '/promo/ramadan');

        DB::table('orders')->insert([
            'order_number' => 'ORD-UTM-1', 'total' => 150000,
            'utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'ramadan',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $rows = LandingTrackingService::aggregate();

        $this->assertCount(1, $rows);
        $this->assertSame('ramadan', $rows[0]['campaign']);
        $this->assertSame('google', $rows[0]['source']);
        $this->assertSame(2, $rows[0]['views']);
        $this->assertSame(1, $rows[0]['orders']);
        $this->assertSame(150000.0, $rows[0]['revenue']);
        $this->assertSame(50.0, $rows[0]['conversion_rate']);
    }

    public function test_checkout_menulis_utm_dari_session_ke_order(): void
    {
        // Bukti wiring: CheckoutController menulis utm_* dari session,
        // Order fillable mengizinkan kolom baru migrasi ini.
        $checkout = file_get_contents(app_path('Http/Controllers/Storefront/CheckoutController.php'));
        $this->assertStringContainsString('utmOrderAttributes', $checkout);
        $this->assertStringContainsString('utm_source', $checkout);

        $order = new \App\Models\Order;
        $this->assertContains('utm_source', $order->getFillable());
        $this->assertContains('utm_medium', $order->getFillable());
        $this->assertContains('utm_campaign', $order->getFillable());
    }

    public function test_migrasi_cms_conversion_backward_compatible_dan_bisa_rollback(): void
    {
        $path = database_path('migrations/2026_09_30_090000_cms_conversion.php');
        $this->assertFileExists($path);

        $source = (string) file_get_contents($path);

        foreach (['popups', 'landing_views', 'experiment_key', 'utm_campaign'] as $needle) {
            $this->assertStringContainsString($needle, $source);
        }
        $this->assertStringContainsString('function down', $source);
        $this->assertStringContainsString('hasColumn', $source, 'kolom alter wajib guarded hasColumn');
        $this->assertStringContainsString('dropIfExists', $source);
    }
}
