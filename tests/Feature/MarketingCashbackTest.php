<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\Campaign;
use App\Models\Coupon;
use App\Models\LoyaltyPoint;
use App\Models\LoyaltyTransaction;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Marketing\CampaignService;
use App\Services\NotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Perdalaman marketing existing: cashback + stack tebus + referral bagikan + WA.
 *
 * Self-contained: membangun tabelnya sendiri di sqlite :memory: dan
 * membersihkannya kembali, mengikuti pola RetentionExpansionTest.
 * Tidak memakai RefreshDatabase agar tahan terhadap migrasi proyek lain
 * yang tidak kompatibel dengan driver uji. Tanpa migrasi baru — kolom
 * cerminan migrasi existing:
 * - campaigns: 2026_09_28_000002 (discount_value, discount_type, periode, kuota)
 * - coupons: 2026_06_09_000012 + 000020
 * - loyalty_*: 2026_06_09_000021 (reference_type/reference_id)
 * - wallets: 2026_06_09_000011 + 2026_09_09_000001 (operation, reference_key)
 * - affiliates: 2026_09_28_000002
 * - sms-gateway: system_settings (sms_provider, sms_api_key)
 */
class MarketingCashbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->buatTabel();
    }

    protected function tearDown(): void
    {
        foreach (['user_notifications', 'affiliate_clicks', 'affiliates', 'wallet_transactions', 'wallets', 'loyalty_transactions', 'loyalty_points', 'coupon_usages', 'coupons', 'campaigns', 'system_settings', 'users'] as $tabel) {
            Schema::dropIfExists($tabel);
        }

        parent::tearDown();
    }

    private function layanan(): CampaignService
    {
        return app(CampaignService::class);
    }

    private function pelanggan(string $nama = 'Budi', array $extra = []): User
    {
        return User::create(array_merge([
            'name' => $nama,
            'email' => strtolower($nama).'@contoh.id',
            'password' => 'rahasia123',
            'role' => 'customer',
            'phone' => '081200000001',
        ], $extra));
    }

    private function kampanyeCashback(array $extra = []): Campaign
    {
        return Campaign::create(array_merge([
            'name' => 'Cashback Harvey',
            'slug' => 'cashback-harvey-'.uniqid(),
            'type' => 'cashback',
            'discount_value' => 10,
            'discount_type' => 'percentage',
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeek(),
        ], $extra));
    }

    /* ---------------- 1. Cashback persen/nominal, idempoten, periode ---------------- */

    public function test_hitung_cashback_persen_dan_nominal_dibatasi_total(): void
    {
        $persen = $this->kampanyeCashback(['discount_value' => 10, 'discount_type' => 'percentage']);
        $this->assertSame(10000.0, $this->layanan()->hitungCashback($persen, 100000));

        $nominal = $this->kampanyeCashback(['slug' => 'cb-nom-'.uniqid(), 'discount_value' => 25000, 'discount_type' => 'flat']);
        $this->assertSame(25000.0, $this->layanan()->hitungCashback($nominal, 100000));
        // Nominal tak boleh melebihi nilai pesanan.
        $this->assertSame(5000.0, $this->layanan()->hitungCashback($nominal, 5000));

        // Bukan kampanye cashback → nol.
        $promo = $this->kampanyeCashback(['slug' => 'promo-'.uniqid(), 'type' => 'promotion']);
        $this->assertSame(0.0, $this->layanan()->hitungCashback($promo, 100000));
    }

    public function test_hitung_cashback_nol_di_luar_periode_atau_nonaktif(): void
    {
        $belumMulai = $this->kampanyeCashback(['slug' => 'cb-future-'.uniqid(), 'starts_at' => now()->addDay(), 'ends_at' => now()->addWeek()]);
        $this->assertSame(0.0, $this->layanan()->hitungCashback($belumMulai, 100000));

        $berakhir = $this->kampanyeCashback(['slug' => 'cb-past-'.uniqid(), 'starts_at' => now()->subWeek(), 'ends_at' => now()->subDay()]);
        $this->assertSame(0.0, $this->layanan()->hitungCashback($berakhir, 100000));

        $jeda = $this->kampanyeCashback(['slug' => 'cb-paused-'.uniqid(), 'status' => 'paused']);
        $this->assertSame(0.0, $this->layanan()->hitungCashback($jeda, 100000));
    }

    public function test_berikan_cashback_dompet_idempoten_dan_hormati_batas(): void
    {
        $user = $this->pelanggan('Budi');
        $kampanye = $this->kampanyeCashback(['discount_value' => 10, 'discount_type' => 'percentage', 'per_user_limit' => 1]);

        $satu = $this->layanan()->berikanCashback($kampanye, $user, 101, 200000, 'dompet');
        $this->assertTrue($satu['dikredit']);
        $this->assertSame(20000.0, $satu['nominal']);
        $this->assertSame(20000.0, (float) Wallet::where('user_id', $user->id)->value('balance'));

        // Replay order sama → idempoten, saldo tak bertambah.
        $ulang = $this->layanan()->berikanCashback($kampanye->fresh(), $user, 101, 200000, 'dompet');
        $this->assertFalse($ulang['dikredit']);
        $this->assertTrue($ulang['sudah_ada']);
        $this->assertSame(20000.0, (float) Wallet::where('user_id', $user->id)->value('balance'));
        $this->assertSame(1, WalletTransaction::query()->where('reference_key', 'cashback-'.$kampanye->id.'-order-101')->count());

        // Order lain tetapi per_user_limit=1 → ditolak.
        $kedua = $this->layanan()->berikanCashback($kampanye->fresh(), $user, 102, 200000, 'dompet');
        $this->assertFalse($kedua['dikredit']);
        $this->assertStringContainsString('batas per pelanggan', $kedua['status']);
    }

    public function test_berikan_cashback_poin_idempoten(): void
    {
        $user = $this->pelanggan('Siti');
        $kampanye = $this->kampanyeCashback(['slug' => 'cb-poin-'.uniqid(), 'discount_value' => 5000, 'discount_type' => 'flat']);

        $satu = $this->layanan()->berikanCashback($kampanye, $user, 201, 100000, 'poin');
        $this->assertTrue($satu['dikredit']);
        $this->assertSame(5000, $satu['poin']);
        $this->assertSame(5000, (int) LoyaltyPoint::where('customer_id', $user->id)->value('points'));

        $ulang = $this->layanan()->berikanCashback($kampanye->fresh(), $user, 201, 100000, 'poin');
        $this->assertFalse($ulang['dikredit']);
        $this->assertTrue($ulang['sudah_ada']);
        $this->assertSame(5000, (int) LoyaltyPoint::where('customer_id', $user->id)->value('points'));
        $this->assertSame(1, LoyaltyTransaction::query()->where('customer_id', $user->id)->where('reference_type', 'cashback')->where('reference_id', 201)->count());
    }

    public function test_berikan_cashback_menolak_kuota_habis(): void
    {
        $user = $this->pelanggan('Andi');
        $kampanye = $this->kampanyeCashback(['slug' => 'cb-kuota-'.uniqid(), 'usage_limit' => 1, 'used_count' => 1]);

        $hasil = $this->layanan()->berikanCashback($kampanye, $user, 301, 100000, 'dompet');
        $this->assertFalse($hasil['dikredit']);
        $this->assertStringContainsString('kuota', $hasil['status']);
    }

    /* ---------------- 2. Stack tebus poin + kupon ---------------- */

    public function test_stack_kupon_dulu_lalu_poin_tak_pernah_minus(): void
    {
        $kupon = Coupon::create([
            'code' => 'HEMAT20', 'title' => 'Hemat 20rb', 'coupon_type' => 'fixed',
            'discount_value' => 20000, 'min_purchase' => 0, 'status' => true,
        ]);

        $simulasi = $this->layanan()->simulasiStackCheckout(100000, $kupon, 50000);
        $this->assertSame(20000.0, $simulasi['diskon_kupon']);
        $this->assertSame(50000, $simulasi['poin_dipakai']);
        $this->assertSame(30000.0, $simulasi['total_bayar']);

        // Poin raksasa + kupon besar: total dikunci nol, bukan minus.
        $kuponBesar = Coupon::create([
            'code' => 'JUMBO', 'title' => 'Jumbo', 'coupon_type' => 'fixed',
            'discount_value' => 90000, 'min_purchase' => 0, 'status' => true,
        ]);
        $kecil = $this->layanan()->simulasiStackCheckout(50000, $kuponBesar, 999999);
        $this->assertSame(0.0, $kecil['total_bayar']);
        $this->assertGreaterThanOrEqual(0, $kecil['poin_dipakai']);
        $this->assertSame(50000.0, $kecil['hemat']);

        // Tanpa kupon: murni poin.
        $murni = $this->layanan()->simulasiStackCheckout(10000, null, 3000);
        $this->assertSame(7000.0, $murni['total_bayar']);
    }

    public function test_model_stack_helpers_konsisten(): void
    {
        $kupon = Coupon::create([
            'code' => 'PERSEN10', 'title' => '10%', 'coupon_type' => 'percentage',
            'discount_value' => 10, 'min_purchase' => 0, 'max_discount' => 15000, 'status' => true,
        ]);
        $this->assertSame(10000.0, $kupon->diskonUntukStack(100000));
        $this->assertSame(90000.0, $kupon->sisaBayarSetelahKupon(100000));

        $user = $this->pelanggan('Rina');
        LoyaltyPoint::earn($user, 8000, 'Uji');
        $lp = LoyaltyPoint::where('customer_id', $user->id)->first();
        $tukar = $lp->nilaiTukarUntukStack(20000, 5000.0);
        $this->assertSame(5000, $tukar['poin_dipakai']);
        $this->assertSame(0.0, $tukar['sisa_bayar']);
    }

    /* ---------------- 3. Referral bagikan tanpa QR ---------------- */

    public function test_referral_tautan_pesan_poster_tanpa_qr(): void
    {
        $user = $this->pelanggan('Dewi', ['referral_code' => 'DEWI123']);
        $affiliate = Affiliate::create([
            'user_id' => $user->id, 'code' => 'DEWI123', 'name' => 'Dewi',
            'email' => $user->email, 'status' => 'active', 'commission_rate' => 5,
        ]);

        // Tanpa paket QR/barcode: qrTersedia() false — pakai tautan + salin + poster.
        $this->assertFalse($affiliate->qrTersedia());

        $tautan = $affiliate->tautanBagikan('https://toko.contoh/produk');
        $this->assertStringContainsString('ref=DEWI123', $tautan);

        $tautanWa = $affiliate->tautanBagikan('https://toko.contoh/produk', 'whatsapp');
        $this->assertStringContainsString('utm_source=whatsapp', $tautanWa);

        $this->assertStringContainsString('DEWI123', $affiliate->pesanBagikan('https://toko.contoh/produk'));

        $svg = $affiliate->posterSvg('https://toko.contoh/produk');
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('DEWI123', $svg);
    }

    /* ---------------- 4. Kanal WA fallback log + status ---------------- */

    public function test_wa_fallback_log_bila_gateway_belum_dikonfigurasi(): void
    {
        SystemSetting::set('sms_provider', 'none');
        SystemSetting::set('sms_api_key', '');

        $notif = app(NotificationService::class);
        $this->assertFalse($notif->waTersedia());

        $hasil = $notif->kirimWa('081200000001', 'Promo cashback minggu ini!', ['user_id' => $this->pelanggan('Eko')->id]);
        $this->assertTrue($hasil['ok']);
        $this->assertSame('whatsapp', $hasil['channel']);
        $this->assertSame('tercatat_log', $hasil['status']);
        $this->assertSame(1, UserNotification::query()->where('channel', 'whatsapp')->count());
    }

    public function test_wa_siap_bila_kredensial_sms_gateway_ada(): void
    {
        SystemSetting::set('sms_provider', 'zenziva');
        SystemSetting::set('sms_api_key', 'kunci-rahasia');

        $notif = app(NotificationService::class);
        $this->assertTrue($notif->waTersedia());

        $hasil = $notif->kirimWaCashback($this->pelanggan('Farah'), 15000, 'dompet Anda');
        $this->assertTrue($hasil['ok']);
        $this->assertSame('terkirim_via_gateway', $hasil['status']);

        // Pesan kosong tak pernah dikirim.
        $kosong = $notif->kirimWa('0812', '   ');
        $this->assertFalse($kosong['ok']);
    }

    /* ---------------- Tabel uji ---------------- */

    private function buatTabel(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('role', 20)->default('customer');
            $t->string('referral_code', 40)->nullable();
            $t->unsignedInteger('referred_by')->nullable();
            $t->timestamps();
        });

        Schema::create('system_settings', function (Blueprint $t) {
            $t->increments('id');
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->string('type')->default('string');
            $t->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name', 160);
            $t->string('slug', 160)->unique();
            $t->string('type', 30)->default('promotion');
            $t->text('description')->nullable();
            $t->json('rules')->nullable();
            $t->json('audience')->nullable();
            $t->decimal('budget', 18, 2)->nullable();
            $t->decimal('discount_value', 18, 2)->default(0);
            $t->string('discount_type', 20)->default('percentage');
            $t->unsignedInteger('usage_limit')->nullable();
            $t->unsignedInteger('used_count')->default(0);
            $t->unsignedInteger('per_user_limit')->nullable();
            $t->string('status', 20)->default('draft');
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->unsignedInteger('clicks')->default(0);
            $t->unsignedInteger('conversions')->default(0);
            $t->decimal('revenue', 18, 2)->default(0);
            $t->unsignedBigInteger('shop_id')->nullable();
            $t->string('code', 60)->nullable();
            $t->string('banner')->nullable();
            $t->timestamp('activated_at')->nullable();
            $t->unsignedInteger('activated_by')->nullable();
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

        Schema::create('coupon_usages', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('coupon_id');
            $t->unsignedInteger('customer_id')->nullable();
            $t->unsignedInteger('order_id')->nullable();
            $t->decimal('discount_amount', 12, 2)->default(0);
            $t->timestamps();
        });

        Schema::create('loyalty_points', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('customer_id');
            $t->integer('points')->default(0);
            $t->timestamps();
        });

        Schema::create('loyalty_transactions', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('customer_id');
            $t->integer('points');
            $t->string('type', 20);
            $t->string('description')->nullable();
            $t->string('reference_type')->nullable();
            $t->unsignedBigInteger('reference_id')->nullable();
            $t->timestamps();
        });

        Schema::create('wallets', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('user_id');
            $t->decimal('balance', 15, 2)->default(0);
            $t->decimal('pending_balance', 15, 2)->default(0);
            $t->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('wallet_id');
            $t->decimal('amount', 15, 2);
            $t->string('type', 20);
            $t->string('operation', 20)->nullable();
            $t->string('reference_type')->nullable();
            $t->unsignedBigInteger('reference_id')->nullable();
            $t->string('reference_key', 120)->nullable();
            $t->string('description')->nullable();
            $t->decimal('balance_before', 15, 2)->default(0);
            $t->decimal('balance_after', 15, 2)->default(0);
            $t->string('status', 20)->default('completed');
            $t->timestamps();
        });

        Schema::create('affiliates', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('user_id')->nullable();
            $t->string('code', 40)->unique();
            $t->string('name', 120);
            $t->string('email', 160)->nullable();
            $t->string('status', 20)->default('pending');
            $t->decimal('commission_rate', 5, 2)->default(5);
            $t->decimal('total_commission', 18, 2)->default(0);
            $t->unsignedInteger('total_orders')->default(0);
            $t->decimal('total_revenue', 18, 2)->default(0);
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
        });

        Schema::create('affiliate_clicks', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('affiliate_id');
            $t->unsignedInteger('customer_id')->nullable();
            $t->string('landing_path', 400)->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent', 400)->nullable();
            $t->unsignedBigInteger('converted_order_id')->nullable();
            $t->timestamp('converted_at')->nullable();
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
    }
}
