<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\B2b\B2bCartGuard;
use App\Services\B2b\B2bPricingService;
use App\Services\B2b\B2bQuoteService;
use App\Services\B2b\B2bSalesmanService;
use App\Services\B2b\B2bTerminService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Kapabilitas B2B/grosir di atas arsitektur existing.
 *
 * Self-contained: membangun tabel minimal sendiri di sqlite :memory:
 * (mengikuti pola CatalogExpansionTest) agar tahan terhadap migrasi
 * proyek lain yang tidak kompatibel dengan driver uji.
 * Tidak menyentuh pricing/checkout existing — hanya membaca harga ecer
 * efektif sebagai fallback.
 */
class B2bExpansionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('products', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('shop_id')->default(1);
            $t->unsignedInteger('category_id')->default(1);
            $t->string('name')->default('');
            $t->string('slug')->unique();
            $t->decimal('price', 15, 2)->default(0);
            $t->decimal('special_price', 15, 2)->nullable();
            $t->unsignedInteger('min_qty')->default(1);
            $t->unsignedInteger('max_qty')->default(0);
            $t->unsignedInteger('current_stock')->default(0);
            $t->string('unit')->nullable();
            $t->string('status')->default('approved');
            $t->boolean('published')->default(true);
            $t->timestamps();
        });

        Schema::create('b2b_price_tiers', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('product_id');
            $t->unsignedInteger('shop_id')->default(1);
            $t->unsignedInteger('min_qty');
            $t->decimal('price', 15, 2);
            $t->string('note', 255)->nullable();
            $t->timestamps();
            $t->unique(['product_id', 'min_qty']);
        });

        Schema::create('b2b_termins', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('order_id');
            $t->unsignedInteger('shop_id')->default(1);
            $t->unsignedInteger('sequence')->default(1);
            $t->string('label', 120)->nullable();
            $t->decimal('amount', 15, 2);
            $t->decimal('paid_amount', 15, 2)->default(0);
            $t->string('status', 20)->default('scheduled');
            $t->timestamp('due_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('reminder_sent_at')->nullable();
            $t->string('note', 500)->nullable();
            $t->timestamps();
            $t->unique(['order_id', 'sequence']);
        });

        Schema::create('users', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name')->default('');
            $t->string('email')->unique();
            $t->string('password')->default('');
            $t->string('referral_code')->nullable();
            $t->unsignedInteger('referred_by')->nullable();
            $t->timestamps();
        });

        Schema::create('orders', function (Blueprint $t) {
            $t->increments('id');
            $t->string('order_number', 50)->unique();
            $t->unsignedInteger('customer_id');
            $t->unsignedInteger('shop_id')->default(1);
            $t->decimal('total', 15, 2)->default(0);
            $t->string('payment_status')->default('unpaid');
            $t->string('order_status')->default('pending');
            $t->timestamps();
        });
    }

    protected function tearDown(): void
    {
        foreach (['orders', 'users', 'b2b_termins', 'b2b_price_tiers', 'products'] as $tabel) {
            Schema::dropIfExists($tabel);
        }

        parent::tearDown();
    }

    private function produkGrosir(): Product
    {
        $produk = Product::create([
            'shop_id' => 1, 'category_id' => 1, 'name' => 'Kopi Robusta Karungan',
            'slug' => 'kopi-robusta-karungan', 'unit' => 'kg',
            'price' => 100000, 'min_qty' => 5, 'max_qty' => 0,
            'current_stock' => 1000, 'status' => 'approved', 'published' => true,
        ]);

        app(B2bPricingService::class)->simpanTiers($produk, [
            ['min_qty' => 10, 'price' => 90000],
            ['min_qty' => 50, 'price' => 80000, 'note' => 'Partai besar'],
        ]);

        return $produk->refresh();
    }

    public function test_tier_harga_per_qty_dan_tabel_pdp(): void
    {
        $layanan = app(B2bPricingService::class);
        $produk = $this->produkGrosir();

        // Di bawah tier → harga ecer existing (tidak rusak).
        $this->assertSame(100000.0, $layanan->unitPriceFor($produk, 5));
        $this->assertSame(90000.0, $layanan->unitPriceFor($produk, 10));
        $this->assertSame(90000.0, $layanan->unitPriceFor($produk, 49));
        $this->assertSame(80000.0, $layanan->unitPriceFor($produk, 50));
        $this->assertSame(80000.0, $layanan->unitPriceFor($produk, 500));

        $baris = $layanan->tierTableRows($produk);
        $this->assertCount(2, $baris);
        $this->assertSame(10, $baris[0]['min_qty']);
        $this->assertSame(10.0, $baris[0]['hemat_pct']);
        $this->assertSame(20.0, $baris[1]['hemat_pct']);

        $html = $layanan->renderTierTableHtml($produk);
        $this->assertStringContainsString('Min. Jumlah', $html);
        $this->assertStringContainsString('Rp 90.000', $html);
        $this->assertStringContainsString('table', $html);

        // Produk tanpa tier → string kosong, PDP existing tak berubah.
        $eceran = Product::create([
            'shop_id' => 1, 'category_id' => 1, 'name' => 'Kopi Sachet',
            'slug' => 'kopi-sachet', 'price' => 5000,
            'current_stock' => 10, 'status' => 'approved', 'published' => true,
        ]);
        $this->assertSame('', $layanan->renderTierTableHtml($eceran));
        $this->assertSame([], $layanan->tierTableRows($eceran));
    }

    public function test_validasi_tier_menolak_input_rusak(): void
    {
        $layanan = app(B2bPricingService::class);
        $produk = $this->produkGrosir();

        $this->expectException(ValidationException::class);
        $layanan->simpanTiers($produk, [
            ['min_qty' => 10, 'price' => 90000],
            ['min_qty' => 10, 'price' => 80000],
        ]);
    }

    public function test_validasi_tier_menolak_harga_nol_dan_tidak_menurun(): void
    {
        $layanan = app(B2bPricingService::class);
        $produk = $this->produkGrosir();

        try {
            $layanan->simpanTiers($produk, [['min_qty' => 10, 'price' => 0]]);
            $this->fail('Harga nol harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        try {
            // Harga naik saat qty naik → bukan grosir.
            $layanan->simpanTiers($produk, [
                ['min_qty' => 10, 'price' => 80000],
                ['min_qty' => 50, 'price' => 90000],
            ]);
            $this->fail('Harga menaik harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    public function test_guard_moq_berbasis_min_qty_dan_tier(): void
    {
        $guard = app(B2bCartGuard::class);
        $produk = $this->produkGrosir();

        // Di bawah min_qty existing (5) → tolak.
        try {
            $guard->validateLine($produk, 3);
            $this->fail('Qty di bawah min_qty harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('minimal 5', (string) json_encode($e->errors()));
        }

        // Di atas min_qty tapi di bawah tier terkecil (10) → LOLOS dengan
        // harga ecer (tier = diskon volume, bukan blokir ritel).
        $ecer = $guard->validateLine($produk, 7);
        $this->assertSame(100000.0, $ecer['unit_price']);
        $this->assertSame(700000.0, $ecer['subtotal']);

        // Tepat di tier → lolos dengan harga tier.
        $ok = $guard->validateLine($produk, 10);
        $this->assertSame(90000.0, $ok['unit_price']);
        $this->assertSame(900000.0, $ok['subtotal']);

        $this->assertSame(10, $guard->effectiveMoq($produk));
    }

    public function test_quote_menghitung_subtotal_hemat_dan_wiring_checkout(): void
    {
        $produk = $this->produkGrosir();
        $lain = Product::create([
            'shop_id' => 1, 'category_id' => 1, 'name' => 'Gula Karungan',
            'slug' => 'gula-karungan', 'unit' => 'kg', 'price' => 20000,
            'min_qty' => 1, 'max_qty' => 0, 'current_stock' => 500,
            'status' => 'approved', 'published' => true,
        ]);

        $quote = app(B2bQuoteService::class)->build([
            ['product' => $produk, 'quantity' => 50], // 50 × 80.000
            ['product' => $lain, 'quantity' => 10],   // 10 × 20.000 (ecer)
        ]);

        $this->assertSame(60, $quote['total_qty']);
        $this->assertSame(4200000.0, $quote['subtotal']);
        // Hemat hanya dari baris bertier: (100.000-80.000) × 50.
        $this->assertSame(1000000.0, $quote['total_savings']);

        $checkout = app(B2bQuoteService::class)->toCheckoutLines($quote);
        $this->assertSame([
            ['product_id' => $produk->id, 'quantity' => 50, 'unit_price' => 80000.0],
            ['product_id' => $lain->id, 'quantity' => 10, 'unit_price' => 20000.0],
        ], $checkout);

        // Harga ecer produk tidak berubah (pricing existing utuh).
        $this->assertSame(100000.0, (float) $produk->refresh()->price);
    }

    private function orderTermin(float $total = 1000000.0): Order
    {
        $customer = User::create([
            'name' => 'PT Maju', 'email' => 'maju@example.com', 'password' => 'secret',
        ]);

        return Order::create([
            'order_number' => 'ORD-B2B-1', 'customer_id' => $customer->id,
            'shop_id' => 1, 'total' => $total,
            'payment_status' => 'unpaid', 'order_status' => 'confirmed',
        ]);
    }

    public function test_termin_jadwal_harus_pas_total_dan_pelunasan_mengubah_status(): void
    {
        $layanan = app(B2bTerminService::class);
        $order = $this->orderTermin();

        // Total termin ≠ total order → tolak.
        try {
            $layanan->buatJadwal($order, [['amount' => 500000]]);
            $this->fail('Selisih total harus ditolak.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $jadwal = $layanan->buatJadwal($order, [
            ['label' => 'DP 60%', 'amount' => 600000, 'due_at' => now()->addDays(7)->toDateTimeString()],
            ['label' => 'Pelunasan', 'amount' => 400000, 'due_at' => now()->addDays(30)->toDateTimeString()],
        ]);

        $this->assertCount(2, $jadwal);
        $this->assertSame('partial', $order->refresh()->payment_status);

        // Kelebihan bayar → tolak.
        try {
            $layanan->catatPembayaran($jadwal[0]->refresh(), 700000);
            $this->fail('Kelebihan bayar harus ditolak.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $layanan->catatPembayaran($jadwal[0]->refresh(), 600000);
        $this->assertSame('partial', $order->refresh()->payment_status);

        $layanan->catatPembayaran($jadwal[1]->refresh(), 400000);
        $this->assertSame('paid', $order->refresh()->payment_status);
    }

    public function test_termin_pengingat_dan_ringkasan_vendor(): void
    {
        $layanan = app(B2bTerminService::class);
        $order = $this->orderTermin(600000.0);

        $layanan->buatJadwal($order, [
            ['label' => 'Termin 1', 'amount' => 300000, 'due_at' => now()->subDay()->toDateTimeString()],
            ['label' => 'Termin 2', 'amount' => 300000, 'due_at' => now()->addDays(3)->toDateTimeString()],
        ]);

        $ingat = $layanan->pengingat(1);
        $this->assertCount(1, $ingat['terlambat']);
        $this->assertCount(1, $ingat['segera']);

        $ringkas = $layanan->ringkasanVendor(1);
        $this->assertSame(600000.0, $ringkas['tagihan']);
        $this->assertSame(0.0, $ringkas['tertagih']);
        $this->assertSame(600000.0, $ringkas['sisa']);
        $this->assertSame(1, $ringkas['terlambat']);

        $jadwal = $layanan->jadwalOrder($order->id);
        $this->assertCount(2, $jadwal);

        $layanan->tandaiPengingat($jadwal[0]);
        $this->assertNotNull($jadwal[0]->refresh()->reminder_sent_at);
    }

    public function test_salesman_dasbor_read_only_dari_referral_code(): void
    {
        $sales = User::create([
            'name' => 'Sales Andi', 'email' => 'andi@example.com',
            'password' => 'secret', 'referral_code' => 'ANDI-123',
        ]);
        $c1 = User::create([
            'name' => 'Toko A', 'email' => 'a@example.com', 'password' => 'secret',
            'referred_by' => $sales->id,
        ]);
        $c2 = User::create([
            'name' => 'Toko B', 'email' => 'b@example.com', 'password' => 'secret',
            'referred_by' => $sales->id,
        ]);
        Order::create([
            'order_number' => 'ORD-S-1', 'customer_id' => $c1->id, 'shop_id' => 1,
            'total' => 500000, 'payment_status' => 'paid', 'order_status' => 'delivered',
        ]);
        Order::create([
            'order_number' => 'ORD-S-2', 'customer_id' => $c2->id, 'shop_id' => 1,
            'total' => 300000, 'payment_status' => 'partial', 'order_status' => 'processing',
        ]);
        // Order batal tidak dihitung.
        Order::create([
            'order_number' => 'ORD-S-3', 'customer_id' => $c2->id, 'shop_id' => 1,
            'total' => 999000, 'payment_status' => 'unpaid', 'order_status' => 'canceled',
        ]);

        $pengguna = User::count();
        $pesanan = Order::count();

        $dasbor = app(B2bSalesmanService::class)->dasbor($sales, 1, 10.0);

        $this->assertSame('ANDI-123', $dasbor['code']);
        $this->assertSame(2, $dasbor['pelanggan']);
        $this->assertSame(2, $dasbor['order']);
        $this->assertSame(800000.0, $dasbor['omzet']);
        $this->assertSame(80000.0, $dasbor['komisi']);
        $this->assertCount(2, $dasbor['order_terbaru']);

        // Read-only: tidak ada baris baru yang tercipta.
        $this->assertSame($pengguna, User::count());
        $this->assertSame($pesanan, Order::count());
    }

    public function test_product_helper_b2b_aman_tanpa_tier(): void
    {
        $produk = Product::create([
            'shop_id' => 1, 'category_id' => 1, 'name' => 'Teh Kotak',
            'slug' => 'teh-kotak', 'price' => 3000,
            'min_qty' => 2, 'current_stock' => 50,
            'status' => 'approved', 'published' => true,
        ]);

        $this->assertFalse($produk->hasB2bTiers());
        $this->assertSame(3000.0, $produk->b2bUnitPrice(10));
        $this->assertSame(2, app(B2bCartGuard::class)->effectiveMoq($produk));
    }

    public function test_migrasi_b2b_wholesale_benar_dan_rollback_lengkap(): void
    {
        $berkas = base_path('database/migrations/2026_09_30_010000_b2b_wholesale.php');
        $this->assertFileExists($berkas);

        $isi = (string) file_get_contents($berkas);
        $this->assertStringContainsString("create('b2b_price_tiers'", $isi);
        $this->assertStringContainsString("create('b2b_termins'", $isi);
        $this->assertStringContainsString("dropIfExists('b2b_termins'", $isi);
        $this->assertStringContainsString("dropIfExists('b2b_price_tiers'", $isi);
        // Tidak ada alter tabel existing → backward-compatible.
        $this->assertStringNotContainsString("table('products'", $isi);
        $this->assertStringNotContainsString("table('orders'", $isi);
        $this->assertStringNotContainsString("table('users'", $isi);

        // Kolom baru tervalidasi terhadap skema uji yang mirror migrasi.
        $this->assertTrue(Schema::hasColumns('b2b_price_tiers', ['product_id', 'shop_id', 'min_qty', 'price']));
        $this->assertTrue(Schema::hasColumns('b2b_termins', ['order_id', 'shop_id', 'sequence', 'amount', 'paid_amount', 'status']));
    }
}
