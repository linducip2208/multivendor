<?php

namespace Tests\Feature;

use App\Models\AbandonedCart;
use App\Models\Banner;
use App\Models\Coupon;
use App\Models\CustomerSegment;
use App\Models\CustomerSegmentMember;
use App\Models\FlashDeal;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Marketing\RetentionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Perdalaman pemasaran existing (tanpa migrasi baru).
 *
 * Self-contained: membangun tabelnya sendiri di sqlite :memory: dan
 * membersihkannya kembali, mengikuti pola CatalogExpansionTest.
 * Tidak memakai RefreshDatabase agar tahan terhadap migrasi proyek lain
 * yang tidak kompatibel dengan driver uji.
 */
class RetentionExpansionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->buatTabel();
    }

    protected function tearDown(): void
    {
        foreach (['user_notifications', 'flash_deals', 'customer_segment_members', 'customer_segments', 'banners', 'coupons', 'abandoned_carts', 'system_settings', 'users'] as $tabel) {
            Schema::dropIfExists($tabel);
        }

        parent::tearDown();
    }

    private function layanan(): RetentionService
    {
        return app(RetentionService::class);
    }

    private function pelanggan(string $nama = 'Budi', array $extra = []): User
    {
        return User::create(array_merge([
            'name' => $nama,
            'email' => strtolower($nama).'@contoh.id',
            'password' => 'rahasia123',
            'role' => 'customer',
        ], $extra));
    }

    /* ---------------- 1. Abandoned cart bertahap ---------------- */

    public function test_pengingat_bertahap_naik_tahap_dan_beri_kupon_di_akhir(): void
    {
        $user = $this->pelanggan('Budi');
        $cart = AbandonedCart::create([
            'customer_id' => $user->id,
            'email' => $user->email,
            'item_count' => 2,
            'amount' => 250000,
            'items' => [['name' => 'Kaos', 'quantity' => 2]],
            'reminder_count' => 0,
        ]);
        $cart->forceFill([
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ])->save();

        $satu = $this->layanan()->sendStagedReminder($cart->fresh(), null, true);
        $this->assertTrue($satu['queued']);
        $this->assertSame(1, $satu['stage']);
        $this->assertNull($satu['coupon_code']);

        // Tahap 2 butuh jeda — mundurkan pengingat terakhir.
        $cart->fresh()->forceFill(['last_reminder_at' => now()->subHours(25)])->save();
        $dua = $this->layanan()->sendStagedReminder($cart->fresh(), null, true);
        $this->assertTrue($dua['queued']);
        $this->assertSame(2, $dua['stage']);
        $this->assertNull($dua['coupon_code']);

        $cart->fresh()->forceFill(['last_reminder_at' => now()->subHours(50)])->save();
        $tiga = $this->layanan()->sendStagedReminder($cart->fresh(), null, true);
        $this->assertTrue($tiga['queued']);
        $this->assertSame(3, $tiga['stage']);
        $this->assertNotNull($tiga['coupon_code']);
        $this->assertDatabaseHas('coupons', ['code' => $tiga['coupon_code']]);

        $this->assertSame(3, (int) $cart->fresh()->reminder_count);
        $this->assertSame(3, UserNotification::query()->where('category', 'marketing')->count());
    }

    public function test_pengingat_berhenti_saat_pulih_atau_batas_tercapai(): void
    {
        $user = $this->pelanggan('Siti');

        $pulih = AbandonedCart::create([
            'customer_id' => $user->id, 'email' => $user->email,
            'item_count' => 1, 'amount' => 50000, 'reminder_count' => 1,
            'recovered_at' => now(),
        ]);
        $hasil = $this->layanan()->sendStagedReminder($pulih, null, true);
        $this->assertFalse($hasil['queued']);
        $this->assertSame(1, (int) $pulih->fresh()->reminder_count);

        $penuh = AbandonedCart::create([
            'customer_id' => $user->id, 'email' => $user->email,
            'item_count' => 1, 'amount' => 50000, 'reminder_count' => 3,
            'last_reminder_at' => now()->subDays(10),
        ]);
        $hasil = $this->layanan()->sendStagedReminder($penuh, null, true);
        $this->assertFalse($hasil['queued']);

        // Tanpa force: jeda minimum dihormati.
        $baru = AbandonedCart::create([
            'customer_id' => $user->id, 'email' => $user->email,
            'item_count' => 1, 'amount' => 50000, 'reminder_count' => 1,
            'last_reminder_at' => now()->subHour(),
        ]);
        $baru->forceFill([
            'created_at' => now()->subDays(2), 'updated_at' => now()->subHour(),
        ])->save();
        $hasil = $this->layanan()->sendStagedReminder($baru);
        $this->assertFalse($hasil['queued']);
        $this->assertSame(1, (int) $baru->fresh()->reminder_count);
    }

    public function test_due_carts_hanya_kembalikan_yang_jatuh_tempo(): void
    {
        $user = $this->pelanggan('Andi');

        $jatuhTempo = AbandonedCart::create([
            'customer_id' => $user->id, 'email' => $user->email,
            'item_count' => 1, 'amount' => 10000, 'reminder_count' => 0,
        ]);
        $jatuhTempo->forceFill([
            'created_at' => now()->subHours(5), 'updated_at' => now()->subHours(5),
        ])->save();
        $terlaluBaru = AbandonedCart::create([
            'customer_id' => $user->id, 'email' => $user->email,
            'item_count' => 1, 'amount' => 10000, 'reminder_count' => 0,
        ]);
        $terlaluBaru->forceFill([
            'created_at' => now()->subMinutes(10), 'updated_at' => now()->subMinutes(10),
        ])->save();

        $ids = $this->layanan()->dueCarts(10)->pluck('id')->all();
        $this->assertContains((int) $jatuhTempo->id, $ids);
        $this->assertCount(1, $ids);
    }

    /* ---------------- 2. Voucher ulang tahun ---------------- */

    public function test_voucher_ultah_fallback_bulan_registrasi_dan_idempoten(): void
    {
        // Tanpa kolom tanggal lahir → fallback bulan registrasi.
        $this->assertNull($this->layanan()->birthdateColumn());

        Carbon::setTestNow(Carbon::create(2026, 5, 15, 9));
        try {
            $ultah = $this->pelanggan('Rina');
            $ultah->forceFill(['created_at' => Carbon::create(2024, 5, 2, 10)])->save();
            $lain = $this->pelanggan('Joko');
            $lain->forceFill(['created_at' => Carbon::create(2024, 8, 2, 10)])->save();

            $kandidat = $this->layanan()->birthdayCandidates(now())->pluck('id')->all();
            $this->assertContains((int) $ultah->id, $kandidat);
            $this->assertNotContains((int) $lain->id, $kandidat);

            $hasil = $this->layanan()->issueBirthdayVouchers(now());
            $this->assertSame(1, $hasil['issued']);
            $this->assertSame(['ULTAH-'.$ultah->id.'-2026'], $hasil['coupons']);
            $this->assertDatabaseHas('coupons', ['code' => 'ULTAH-'.$ultah->id.'-2026']);

            // Idempoten: jalan kedua tidak menerbitkan ganda.
            $kedua = $this->layanan()->issueBirthdayVouchers(now());
            $this->assertSame(0, $kedua['issued']);
            $this->assertSame(1, Coupon::query()->where('code', 'ULTAH-'.$ultah->id.'-2026')->count());

            // Notifikasi ultah tepat satu.
            $this->assertSame(1, UserNotification::query()->where('dedupe_key', 'ultah:'.$ultah->id.':2026')->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    /* ---------------- 3. Banner per segmen ---------------- */

    public function test_banner_personalisasi_hanya_untuk_anggota_segmen(): void
    {
        $anggota = $this->pelanggan('Anggota');
        $luar = $this->pelanggan('Luar');

        $segmen = CustomerSegment::create([
            'name' => 'Nilai Tinggi', 'slug' => 'nilai-tinggi', 'type' => 'rule',
        ]);
        CustomerSegmentMember::create([
            'customer_segment_id' => $segmen->id, 'customer_id' => $anggota->id,
        ]);

        $publik = Banner::create(['title' => 'Promo Umum', 'position' => 'hero', 'status' => true, 'sort_order' => 1]);
        $vip = Banner::create(['title' => 'Promo VIP', 'position' => 'hero', 'status' => true, 'sort_order' => 2]);

        $this->layanan()->setBannerSegments((int) $vip->id, [(int) $segmen->id]);

        $untukAnggota = $this->layanan()->visibleBannersForCustomer((int) $anggota->id, 'hero', 10)->pluck('id')->all();
        $this->assertContains((int) $publik->id, $untukAnggota);
        $this->assertContains((int) $vip->id, $untukAnggota);

        $untukLuar = $this->layanan()->visibleBannersForCustomer((int) $luar->id, 'hero', 10)->pluck('id')->all();
        $this->assertContains((int) $publik->id, $untukLuar);
        $this->assertNotContains((int) $vip->id, $untukLuar);

        $untukTamu = $this->layanan()->visibleBannersForCustomer(null, 'hero', 10)->pluck('id')->all();
        $this->assertContains((int) $publik->id, $untukTamu);
        $this->assertNotContains((int) $vip->id, $untukTamu);
    }

    /* ---------------- 4. Ingatkan-saya flash sale ---------------- */

    public function test_flash_subscribe_dan_notifikasi_saat_mulai(): void
    {
        $user = $this->pelanggan('Dewi');

        $mendatang = FlashDeal::create([
            'title' => 'Flash 12.12', 'status' => true,
            'start_date' => now()->addDay(), 'end_date' => now()->addDays(2),
        ]);
        $berjalan = FlashDeal::create([
            'title' => 'Flash Hari Ini', 'status' => true,
            'start_date' => now()->subHour(), 'end_date' => now()->addHour(),
        ]);

        $ok = $this->layanan()->subscribeFlashReminder((int) $mendatang->id, (int) $user->id);
        $this->assertTrue($ok['subscribed']);

        // Idempoten: daftar dua kali tetap satu.
        $lagi = $this->layanan()->subscribeFlashReminder((int) $mendatang->id, (int) $user->id);
        $this->assertTrue($lagi['subscribed']);
        $this->assertCount(1, $this->layanan()->subscribersForDeal((int) $mendatang->id));

        // Deal berjalan tidak bisa dilanggan.
        $telat = $this->layanan()->subscribeFlashReminder((int) $berjalan->id, (int) $user->id);
        $this->assertFalse($telat['subscribed']);

        // Deal belum mulai → belum ada notifikasi.
        $belum = $this->layanan()->notifyFlashDealStarted($mendatang);
        $this->assertSame(0, $belum['sent']);

        // Majukan waktu: deal mulai → pelanggan diberi tahu sekali.
        $mendatang->forceFill(['start_date' => now()->subMinute()])->save();
        $mulai = $this->layanan()->notifyFlashDealStarted($mendatang->fresh());
        $this->assertSame(1, $mulai['sent']);
        $this->assertSame(1, UserNotification::query()
            ->where('dedupe_key', 'flash:'.$mendatang->id.':mulai:'.$user->id)->count());

        // Langganan dibersihkan; jalan ulang tidak mengirim ganda.
        $this->assertSame([], $this->layanan()->subscribersForDeal((int) $mendatang->id));
        $ulang = $this->layanan()->notifyFlashDealStarted($mendatang->fresh());
        $this->assertSame(0, $ulang['sent']);
    }

    public function test_unsubscribe_membatalkan_pengingat(): void
    {
        $user = $this->pelanggan('Batal');
        $deal = FlashDeal::create([
            'title' => 'Flash Batal', 'status' => true,
            'start_date' => now()->addDay(), 'end_date' => now()->addDays(2),
        ]);

        $this->layanan()->subscribeFlashReminder((int) $deal->id, (int) $user->id);
        $this->layanan()->unsubscribeFlashReminder((int) $deal->id, (int) $user->id);

        $this->assertSame([], $this->layanan()->subscribersForDeal((int) $deal->id));
    }

    /* ---------------- Tabel uji ---------------- */

    private function buatTabel(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password')->nullable();
            $t->string('role', 20)->default('customer');
            $t->timestamps();
        });

        Schema::create('system_settings', function (Blueprint $t) {
            $t->increments('id');
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->string('type')->default('string');
            $t->timestamps();
        });

        Schema::create('abandoned_carts', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('customer_id')->nullable();
            $t->string('email', 160)->nullable();
            $t->string('session_id', 80)->nullable();
            $t->unsignedInteger('item_count')->default(0);
            $t->decimal('amount', 18, 2)->default(0);
            $t->json('items')->nullable();
            $t->unsignedTinyInteger('reminder_count')->default(0);
            $t->timestamp('last_reminder_at')->nullable();
            $t->timestamp('recovered_at')->nullable();
            $t->unsignedInteger('recovered_order_id')->nullable();
            $t->timestamps();
        });

        Schema::create('coupons', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('shop_id')->nullable();
            $t->string('code', 50)->unique();
            $t->string('title')->nullable();
            $t->string('coupon_type')->default('percentage');
            $t->decimal('discount_value', 10, 2)->default(0);
            $t->decimal('min_purchase', 10, 2)->default(0);
            $t->decimal('max_discount', 10, 2)->nullable();
            $t->dateTime('start_date')->nullable();
            $t->dateTime('end_date')->nullable();
            $t->integer('usage_limit')->nullable();
            $t->integer('usage_per_customer')->default(1);
            $t->integer('usage_count')->default(0);
            $t->boolean('status')->default(true);
            $t->timestamps();
        });

        Schema::create('user_notifications', function (Blueprint $t) {
            $t->increments('id');
            $t->string('uuid', 40)->unique();
            $t->string('notifiable_type', 120);
            $t->unsignedInteger('notifiable_id');
            $t->string('channel', 20)->default('database');
            $t->string('category', 40);
            $t->string('title', 180);
            $t->text('body')->nullable();
            $t->string('action_url', 500)->nullable();
            $t->string('action_label', 60)->nullable();
            $t->json('data')->nullable();
            $t->string('dedupe_key', 120)->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
        });

        Schema::create('banners', function (Blueprint $t) {
            $t->increments('id');
            $t->string('title');
            $t->string('subtitle')->nullable();
            $t->string('image')->nullable();
            $t->string('link')->nullable();
            $t->string('position', 20)->default('hero');
            $t->integer('sort_order')->default(0);
            $t->boolean('status')->default(true);
            $t->timestamps();
        });

        Schema::create('customer_segments', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name', 120);
            $t->string('slug', 120)->unique();
            $t->string('description', 255)->nullable();
            $t->string('type', 40)->default('manual');
            $t->json('rules')->nullable();
            $t->unsignedInteger('member_count')->default(0);
            $t->boolean('is_dynamic')->default(false);
            $t->timestamps();
        });

        Schema::create('customer_segment_members', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('customer_segment_id');
            $t->unsignedInteger('customer_id');
            $t->decimal('lifetime_value', 18, 2)->default(0);
            $t->unsignedInteger('order_count')->default(0);
            $t->timestamp('last_order_at')->nullable();
            $t->timestamps();
        });

        Schema::create('flash_deals', function (Blueprint $t) {
            $t->increments('id');
            $t->string('title')->default('');
            $t->dateTime('start_date')->nullable();
            $t->dateTime('end_date')->nullable();
            $t->string('banner')->nullable();
            $t->boolean('status')->default(false);
            $t->boolean('featured')->default(false);
            $t->timestamps();
        });
    }
}
