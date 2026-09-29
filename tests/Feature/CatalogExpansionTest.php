<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\FlashDeal;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Search\SearchAnalytics;
use App\Search\SearchManager;
use App\Search\SynonymRepository;
use App\Services\Catalog\ProductCsvService;
use App\Services\Catalog\TrenHarga;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Perdalaman fitur katalog existing (tanpa migrasi baru).
 *
 * Self-contained: membangun tabelnya sendiri di sqlite :memory: dan
 * membersihkannya kembali, mengikuti pola FlashDealOrderingTest.
 * Tidak memakai RefreshDatabase agar tahan terhadap migrasi proyek lain
 * yang tidak kompatibel dengan driver uji.
 */
class CatalogExpansionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        SynonymRepository::flush();

        $this->buatTabelProduk();
        $this->buatTabelFlashDeal();
        $this->buatTabelKupon();
        $this->buatTabelSearch();
        $this->buatTabelOrder();
    }

    protected function tearDown(): void
    {
        foreach (['order_items', 'orders', 'search_synonyms', 'search_queries', 'coupon_usages', 'coupon_category', 'categories', 'coupons', 'flash_deal_products', 'flash_deals', 'product_variants', 'products'] as $tabel) {
            Schema::dropIfExists($tabel);
        }

        SynonymRepository::flush();

        parent::tearDown();
    }

    public function test_varian_kombinasi_duplikat_sku_per_toko_stok_dan_harga(): void
    {
        $produk = Product::create([
            'shop_id' => 1, 'category_id' => 1, 'name' => 'Kaos', 'slug' => 'kaos',
            'price' => 100000, 'current_stock' => 10, 'status' => 'approved', 'published' => true,
        ]);

        ProductVariant::create([
            'product_id' => $produk->id, 'sku' => 'KAOS-HITAM-M',
            'variant_attributes' => ['Warna' => 'Hitam', 'Ukuran' => 'M'],
            'price' => 100000, 'special_price' => 90000, 'stock' => 2, 'low_stock_threshold' => 5,
        ]);

        // Kombinasi sama dengan urutan/huruf berbeda tetap terdeteksi duplikat.
        $this->assertTrue(ProductVariant::kombinasiDuplikat(
            $produk->id, ['ukuran' => 'm', 'warna' => 'hitam']
        ));
        $this->assertFalse(ProductVariant::kombinasiDuplikat(
            $produk->id, ['warna' => 'putih', 'ukuran' => 'm']
        ));

        $this->assertSame(
            ['Duplikat terdeteksi'],
            array_map(fn () => 'Duplikat terdeteksi', array_filter([ProductVariant::kombinasiDuplikat($produk->id, ['warna' => 'HITAM', 'ukuran' => 'M'])]))
        );

        $galat = ProductVariant::validasiBaris(
            ['price' => 100000, 'special_price' => 90000, 'stock' => 2, 'variant_attributes' => ['warna' => 'hitam', 'ukuran' => 'm']],
            $produk->id
        );
        $this->assertContains('Kombinasi atribut varian ini sudah dipakai varian lain pada produk yang sama.', $galat);

        // SKU unik per toko: toko sama menolak, toko lain membolehkan.
        $lainSatuToko = Product::create([
            'shop_id' => 1, 'category_id' => 1, 'name' => 'Kemeja', 'slug' => 'kemeja',
            'price' => 50000, 'current_stock' => 5, 'status' => 'approved', 'published' => true,
        ]);
        $lainBedaToko = Product::create([
            'shop_id' => 2, 'category_id' => 1, 'name' => 'Jaket', 'slug' => 'jaket',
            'price' => 50000, 'current_stock' => 5, 'status' => 'approved', 'published' => true,
        ]);

        $this->assertTrue(ProductVariant::skuSudahDipakaiDiToko('KAOS-HITAM-M', $lainSatuToko->id));
        $this->assertFalse(ProductVariant::skuSudahDipakaiDiToko('KAOS-HITAM-M', $lainBedaToko->id));
        $this->assertFalse(ProductVariant::skuSudahDipakaiDiToko('', $lainSatuToko->id));

        // Stok + ambang per varian.
        $varian = ProductVariant::where('sku', 'KAOS-HITAM-M')->first();
        $this->assertTrue($varian->isLowStock());
        $this->assertFalse($varian->isOutOfStock());
        $this->assertTrue($varian->hasConsistentPricing());

        $varianJanggal = new ProductVariant(['price' => 50000, 'special_price' => 60000, 'stock' => 0]);
        $this->assertFalse($varianJanggal->hasConsistentPricing());
        $this->assertTrue($varianJanggal->isOutOfStock());

        $this->assertSame('{"ukuran":"m","warna":"hitam"}', $varian->combinationKey());
    }

    public function test_csv_template_validasi_per_baris_dan_laporan_hasil(): void
    {
        $layanan = new ProductCsvService;

        $template = $layanan->templateCsv();
        $this->assertStringContainsString('name,slug,sku', $template);
        $this->assertStringContainsString('Contoh Kaos Polos Hitam', $template);

        $csv = "name,slug,sku,price,special_price,current_stock,condition\n"
            ."Kaos Valid,,KAOS-1,100000,90000,10,new\n"
            .",,KAOS-2,50000,,5,new\n"
            ."Kemeja Mahal,,KAOS-1,200000,250000,3,new\n"
            ."Topi,,TOPI-1,-100,,2,baru\n";

        $laporan = $layanan->laporanImpor($csv);

        $this->assertSame(4, $laporan['total']);
        $this->assertSame(1, $laporan['valid']);
        $this->assertSame(3, $laporan['gagal']);

        $semuaGalat = implode(' ', array_merge(...array_column($laporan['baris'], 'errors')));
        $this->assertStringContainsString('wajib diisi', $semuaGalat);
        $this->assertStringContainsString('lebih dari satu kali', $semuaGalat);
        $this->assertStringContainsString('lebih kecil dari', $semuaGalat);

        $this->assertSame([], $laporan['baris'][0]['errors']);
    }

    public function test_tren_harga_dihitung_dari_order_items(): void
    {
        $produk = Product::create([
            'shop_id' => 1, 'category_id' => 1, 'name' => 'Sepatu', 'slug' => 'sepatu',
            'price' => 200000, 'current_stock' => 8, 'status' => 'approved', 'published' => true,
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'order_status' => 'delivered', 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([
            [now()->subDays(3)->toDateTimeString(), 200000],
            [now()->subDays(2)->toDateTimeString(), 180000],
            [now()->subDays(1)->toDateTimeString(), 150000],
        ] as [$waktu, $harga]) {
            DB::table('order_items')->insert([
                'order_id' => $orderId, 'product_id' => $produk->id,
                'price' => $harga, 'created_at' => $waktu, 'updated_at' => $waktu,
            ]);
        }

        // Pesanan batal tidak boleh mengotori tren.
        $batalId = DB::table('orders')->insertGetId([
            'order_status' => 'canceled', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('order_items')->insert([
            'order_id' => $batalId, 'product_id' => $produk->id,
            'price' => 999999, 'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString(),
        ]);

        $tren = (new TrenHarga)->untukProduk($produk->id);

        $this->assertCount(3, $tren['titik']);
        $this->assertSame(150000.0, $tren['terkini']);
        $this->assertSame(150000.0, $tren['terendah']);
        $this->assertSame(200000.0, $tren['tertinggi']);
        $this->assertSame('turun', $tren['arah']);
        $this->assertSame(3, $tren['jumlah_transaksi']);
    }

    public function test_flashdeal_antrean_terjadwal_dan_resolusi_overlap(): void
    {
        $sekarang = now();

        $berjalanBiasa = new FlashDeal([
            'title' => 'Biasa', 'status' => true, 'featured' => false,
            'start_date' => $sekarang->copy()->subDay(), 'end_date' => $sekarang->copy()->addDays(5),
        ]);
        $berjalanUnggulan = new FlashDeal([
            'title' => 'Unggulan', 'status' => true, 'featured' => true,
            'start_date' => $sekarang->copy()->subDay(), 'end_date' => $sekarang->copy()->addDays(5),
        ]);

        // Unggulan menang walau diskonnya lebih kecil.
        $berjalanBiasa->setRelation('products', collect([
            (object) ['pivot' => (object) ['discount_type' => 'percentage', 'discount_value' => 50], 'price' => 100, 'special_price' => null, 'discount_start' => null, 'discount_end' => null],
        ]));
        $berjalanUnggulan->setRelation('products', collect([
            (object) ['pivot' => (object) ['discount_type' => 'percentage', 'discount_value' => 10], 'price' => 100, 'special_price' => null, 'discount_start' => null, 'discount_end' => null],
        ]));

        $pemenang = FlashDeal::selesaikanOverlap([$berjalanBiasa, $berjalanUnggulan]);
        $this->assertSame('Unggulan', $pemenang->title);

        $this->assertTrue($berjalanBiasa->bertabrakanDengan($berjalanUnggulan));

        $jauh = new FlashDeal([
            'title' => 'Jauh', 'status' => true,
            'start_date' => $sekarang->copy()->addDays(10), 'end_date' => $sekarang->copy()->addDays(12),
        ]);
        $this->assertFalse($berjalanBiasa->bertabrakanDengan($jauh));
        $this->assertNull(FlashDeal::selesaikanOverlap([]));

        // Antrean terjadwal dari database: hanya yang belum mulai, terurut.
        $conn = DB::connection();
        $conn->table('flash_deals')->insert([
            'title' => 'Aktif', 'status' => true,
            'start_date' => $sekarang->copy()->subDay(), 'end_date' => $sekarang->copy()->addDay(),
            'created_at' => $sekarang, 'updated_at' => $sekarang,
        ]);
        $conn->table('flash_deals')->insert([
            'title' => 'Besok', 'status' => true,
            'start_date' => $sekarang->copy()->addDay(), 'end_date' => $sekarang->copy()->addDays(2),
            'created_at' => $sekarang, 'updated_at' => $sekarang,
        ]);
        $conn->table('flash_deals')->insert([
            'title' => 'Lusa', 'status' => true,
            'start_date' => $sekarang->copy()->addDays(2), 'end_date' => $sekarang->copy()->addDays(3),
            'created_at' => $sekarang, 'updated_at' => $sekarang,
        ]);

        $antrean = FlashDeal::antreanTerjadwal(5);
        $this->assertSame(['Besok', 'Lusa'], $antrean->pluck('title')->all());
    }

    public function test_kupon_kode_personal_batas_minimum_kategori_dan_laporan(): void
    {
        $kode = Coupon::buatKodePersonal('Hadiah');
        $this->assertMatchesRegularExpression('/^HADIAH-[A-Z0-9-]{4,}$/', $kode);

        $kupon = Coupon::create([
            'code' => $kode, 'title' => 'Hadiah', 'coupon_type' => 'percentage',
            'discount_value' => 10, 'min_purchase' => 100000, 'status' => true,
            'usage_limit' => 10, 'usage_per_customer' => 2,
        ]);

        $this->assertNotSame($kode, Coupon::buatKodePersonal('Hadiah'));

        // Batas pakai per user.
        $this->assertTrue($kupon->bolehDipakaiOleh(7));
        $this->assertSame(2, $kupon->sisaKuotaUntuk(7));

        DB::table('coupon_usages')->insert([
            ['coupon_id' => $kupon->id, 'customer_id' => 7, 'order_id' => 1, 'discount_amount' => 15000, 'created_at' => now(), 'updated_at' => now()],
            ['coupon_id' => $kupon->id, 'customer_id' => 7, 'order_id' => 2, 'discount_amount' => 10000, 'created_at' => now(), 'updated_at' => now()],
            ['coupon_id' => $kupon->id, 'customer_id' => 8, 'order_id' => 3, 'discount_amount' => 5000, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->assertSame(0, $kupon->sisaKuotaUntuk(7));
        $this->assertFalse($kupon->bolehDipakaiOleh(7));
        $this->assertTrue($kupon->bolehDipakaiOleh(8));

        $laporan = $kupon->ringkasanPemakaian();
        $this->assertSame(3, $laporan['total_pakai']);
        $this->assertSame(30000.0, $laporan['total_diskon']);
        $this->assertSame(2, $laporan['pelanggan_unik']);
        $this->assertSame(7, $laporan['sisa_kuota']);

        // Minimum per kategori.
        $kategoriId = DB::table('categories')->insertGetId(['name' => 'Sepatu']);
        DB::table('coupon_category')->insert(['coupon_id' => $kupon->id, 'category_id' => $kategoriId]);

        $kuponSegar = Coupon::with('categories')->find($kupon->id);
        $this->assertTrue($kuponSegar->memenuhiMinimumKategori(150000.0));
        $this->assertFalse($kuponSegar->memenuhiMinimumKategori(50000.0));

        // Tanpa ikatan kategori, total belanja yang dipakai.
        $bebas = Coupon::create([
            'code' => Coupon::buatKodePersonal('Bebas'), 'coupon_type' => 'fixed',
            'discount_value' => 5000, 'min_purchase' => 20000, 'status' => true,
        ]);
        $this->assertTrue($bebas->memenuhiMinimumKategori(5000.0, 25000.0));
        $this->assertFalse($bebas->memenuhiMinimumKategori(5000.0, 10000.0));
    }

    public function test_search_sinonim_nolhasil_dan_boosting_konfig(): void
    {
        $this->assertSame('sepatu bola', SynonymRepository::normalisasiIstilah('  Sepatu-BOLA!! '));
        $this->assertContains(
            'Isi minimal satu sinonim yang berbeda dari istilah utama.',
            SynonymRepository::validasiBaris('sepatu', ['Sepatu'])
        );
        $this->assertSame([], SynonymRepository::validasiBaris('sepatu', ['sepatu bola', 'sneaker']));
        $this->assertContains(
            'Istilah utama wajib diisi.',
            SynonymRepository::validasiBaris('   ', ['sneaker'])
        );

        $sekarang = now()->toDateTimeString();
        foreach ([
            ['sepatu lari', 'sepatu lari', 5, 120],
            ['sneaker lari', 'sneaker lari', 3, 60],
            ['sandal jepit', 'sandal jepit', 4, 0],
        ] as [$query, $normal, $kali, $hits]) {
            for ($i = 0; $i < $kali; $i++) {
                DB::table('search_queries')->insert([
                    'query' => $query, 'normalized_query' => $normal,
                    'query_hash' => hash('sha256', $normal.$i), 'token_count' => 2,
                    'result_count' => $hits, 'results_shown' => 0, 'has_term' => true,
                    'zero_result' => $hits === 0, 'used_typo_tolerance' => false,
                    'source' => 'search', 'driver' => 'database', 'took_ms' => 5,
                    'created_at' => $sekarang, 'updated_at' => $sekarang,
                ]);
            }
        }

        $saran = SearchAnalytics::saranUntukNolHasil('sepatu larii');
        $this->assertNotEmpty($saran);
        $this->assertSame('sepatu lari', $saran[0]['term']);

        $this->assertSame([], SearchAnalytics::saranUntukNolHasil('   '));

        // Usulan sinonim: istilah populer yang belum punya sinonim.
        $usulan = SynonymRepository::usulanDariAnalytics(10);
        $istilah = array_column($usulan, 'term');
        $this->assertContains('sepatu lari', $istilah);

        DB::table('search_synonyms')->insert([
            'term' => 'sepatu lari', 'synonyms' => json_encode(['sneaker lari']),
            'is_active' => true, 'is_system' => false, 'weight' => 1,
            'created_at' => $sekarang, 'updated_at' => $sekarang,
        ]);
        SynonymRepository::flush();

        $this->assertContains('sneaker lari', SynonymRepository::expand('sepatu lari'));
        $this->assertNotContains('sepatu lari', array_column(SynonymRepository::usulanDariAnalytics(10), 'term'));

        // Boosting tetap konfig-driven.
        $bobot = app(SearchManager::class)->bobotPeringkat();
        $this->assertSame((float) config('search.ranking.weights.sku_exact', 1200), $bobot['sku_exact']);
        $this->assertArrayHasKey('name_contains', $bobot);
    }

    public function test_produk_duplikasi_dan_arsip(): void
    {
        $produk = Product::create([
            'shop_id' => 1, 'category_id' => 1, 'name' => 'Tas Ransel', 'slug' => 'tas-ransel',
            'sku' => 'TAS-1', 'price' => 250000, 'current_stock' => 12,
            'sold_count' => 30, 'view_count' => 100, 'rating_average' => 4.5, 'rating_count' => 6,
            'status' => 'approved', 'published' => true,
        ]);
        ProductVariant::create([
            'product_id' => $produk->id, 'sku' => 'TAS-1-HITAM',
            'variant_attributes' => ['warna' => 'hitam'],
            'price' => 250000, 'stock' => 4, 'low_stock_threshold' => 5,
        ]);

        $salinan = $produk->duplikasikan();

        $this->assertNotSame($produk->id, $salinan->id);
        $this->assertSame('Tas Ransel (Salinan)', $salinan->name);
        $this->assertStringStartsWith('tas-ransel', $salinan->slug);
        $this->assertNotSame('tas-ransel', $salinan->slug);
        $this->assertSame('pending', $salinan->status);
        $this->assertFalse((bool) $salinan->published);
        $this->assertNull($salinan->sku);
        $this->assertSame(0, (int) $salinan->sold_count);
        $this->assertSame(0, (int) $salinan->view_count);
        $this->assertCount(1, $salinan->variants);
        $this->assertNull($salinan->variants->first()->sku);

        $this->assertTrue($produk->arsipkan());
        $this->assertTrue($produk->refresh()->diarsipkan());

        $this->assertTrue($produk->pulihkanDariArsip());
        $this->assertFalse($produk->refresh()->diarsipkan());
        $this->assertSame('pending', $produk->status);
    }

    /* ------------------------- tabel uji mandiri ------------------------- */

    private function buatTabelProduk(): void
    {
        Schema::create('products', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('shop_id');
            $t->unsignedInteger('category_id');
            $t->unsignedInteger('brand_id')->nullable();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('sku')->nullable();
            $t->string('barcode')->nullable();
            $t->decimal('price', 15, 2)->default(0);
            $t->decimal('special_price', 15, 2)->nullable();
            $t->unsignedInteger('current_stock')->default(0);
            $t->unsignedInteger('low_stock_threshold')->default(5);
            $t->unsignedInteger('sold_count')->default(0);
            $t->unsignedInteger('view_count')->default(0);
            $t->decimal('rating_average', 3, 2)->default(0);
            $t->unsignedInteger('rating_count')->default(0);
            $t->string('status')->default('pending');
            $t->boolean('published')->default(false);
            $t->unsignedInteger('approved_by')->nullable();
            $t->dateTime('approved_at')->nullable();
            $t->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('product_id');
            $t->string('sku')->nullable();
            $t->string('variant')->nullable();
            $t->json('variant_attributes')->nullable();
            $t->decimal('price', 15, 2)->default(0);
            $t->decimal('special_price', 15, 2)->nullable();
            $t->string('discount_type')->nullable();
            $t->dateTime('discount_start')->nullable();
            $t->dateTime('discount_end')->nullable();
            $t->unsignedInteger('stock')->default(0);
            $t->unsignedInteger('low_stock_threshold')->default(3);
            $t->timestamps();
        });
    }

    private function buatTabelFlashDeal(): void
    {
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

        Schema::create('flash_deal_products', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('flash_deal_id');
            $t->unsignedInteger('product_id');
            $t->string('discount_type')->default('percentage');
            $t->decimal('discount_value', 10, 2)->default(0);
            $t->timestamps();
        });
    }

    private function buatTabelKupon(): void
    {
        Schema::create('categories', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name');
        });

        Schema::create('coupons', function (Blueprint $t) {
            $t->increments('id');
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

        Schema::create('coupon_category', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('coupon_id');
            $t->unsignedInteger('category_id');
            $t->timestamps();
        });

        Schema::create('coupon_usages', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('coupon_id');
            $t->unsignedInteger('customer_id');
            $t->unsignedInteger('order_id')->nullable();
            $t->decimal('discount_amount', 10, 2)->default(0);
            $t->timestamps();
        });
    }

    private function buatTabelSearch(): void
    {
        Schema::create('search_queries', function (Blueprint $t) {
            $t->increments('id');
            $t->string('query', 255)->default('');
            $t->string('normalized_query', 255)->default('');
            $t->string('query_hash', 64)->default('');
            $t->unsignedSmallInteger('token_count')->default(0);
            $t->unsignedInteger('result_count')->default(0);
            $t->unsignedInteger('results_shown')->default(0);
            $t->boolean('has_term')->default(false);
            $t->boolean('zero_result')->default(false);
            $t->boolean('used_typo_tolerance')->default(false);
            $t->string('source', 20)->default('search');
            $t->string('driver', 30)->default('database');
            $t->unsignedSmallInteger('took_ms')->default(0);
            $t->json('filters')->nullable();
            $t->string('ip_hash', 64)->nullable();
            $t->string('visitor_hash', 64)->nullable();
            $t->string('locale', 10)->nullable();
            $t->string('device', 20)->nullable();
            $t->timestamps();
        });

        Schema::create('search_synonyms', function (Blueprint $t) {
            $t->increments('id');
            $t->string('term', 120)->unique();
            $t->json('synonyms')->nullable();
            $t->string('group_key', 120)->nullable();
            $t->boolean('is_active')->default(true);
            $t->boolean('is_system')->default(false);
            $t->unsignedTinyInteger('weight')->default(1);
            $t->timestamps();
        });
    }

    private function buatTabelOrder(): void
    {
        Schema::create('orders', function (Blueprint $t) {
            $t->increments('id');
            $t->string('order_status')->default('pending');
            $t->timestamps();
        });

        Schema::create('order_items', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('order_id');
            $t->unsignedInteger('product_id');
            $t->decimal('price', 15, 2)->default(0);
            $t->timestamps();
        });
    }
}
