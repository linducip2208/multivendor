<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Perdalaman katalog existing: galeri per varian, lisensi digital,
 * koleksi tematik, dan panduan kategori.
 *
 * Self-contained: membangun tabelnya sendiri di sqlite :memory: dan
 * membersihkannya kembali (pola CatalogExpansionTest). Tidak memakai
 * RefreshDatabase agar tahan terhadap migrasi proyek lain.
 */
class CatalogMediaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->buatTabelKatalog();
    }

    protected function tearDown(): void
    {
        foreach ([
            'thematic_collection_product', 'thematic_collections',
            'digital_license_downloads', 'digital_licenses',
            'product_attributes', 'attribute_values', 'attributes',
            'product_variants', 'products', 'categories',
        ] as $tabel) {
            Schema::dropIfExists($tabel);
        }

        parent::tearDown();
    }

    public function test_galeri_varian_ikut_pilihan_dan_fallback_thumbnail(): void
    {
        $produk = Product::create([
            'shop_id' => 1, 'category_id' => 1, 'name' => 'Kaos', 'slug' => 'kaos-media',
            'thumbnail' => 'products/kaos.jpg', 'images' => ['products/kaos-2.jpg'],
            'price' => 100000, 'current_stock' => 10, 'status' => 'approved', 'published' => true,
        ]);

        $denganGambar = ProductVariant::create([
            'product_id' => $produk->id, 'variant' => 'Merah',
            'variant_attributes' => ['warna' => 'merah'],
            'images' => ['variants/merah-1.jpg', 'variants/merah-2.jpg'],
            'price' => 100000, 'stock' => 5,
        ]);

        $tanpaGambar = ProductVariant::create([
            'product_id' => $produk->id, 'variant' => 'Biru',
            'variant_attributes' => ['warna' => 'biru'],
            'price' => 100000, 'stock' => 5,
        ]);

        $this->assertTrue($denganGambar->punyaGambarSendiri());
        $this->assertFalse($tanpaGambar->punyaGambarSendiri());

        $this->assertSame(
            [url('img/variants/merah-1.jpg'), url('img/variants/merah-2.jpg')],
            $denganGambar->urlGaleri()
        );

        // Varian bergambar → galerinya sendiri.
        $this->assertSame($denganGambar->urlGaleri(), $produk->galeriUntukVarian($denganGambar->id));

        // Varian tanpa gambar + varian tak dikenal → fallback galeri produk.
        $dasar = [url('img/products/kaos.jpg'), url('img/products/kaos-2.jpg')];
        $this->assertSame($dasar, $produk->galeriDasar());
        $this->assertSame($dasar, $produk->galeriUntukVarian($tanpaGambar->id));
        $this->assertSame($dasar, $produk->galeriUntukVarian(999999));
        $this->assertSame($dasar, $produk->galeriUntukVarian(null));

        // Peta galeri hanya memuat varian yang punya gambar.
        $peta = $produk->petaGaleriVarian();
        $this->assertArrayHasKey($denganGambar->id, $peta);
        $this->assertArrayNotHasKey($tanpaGambar->id, $peta);
    }

    public function test_lisensi_digital_unik_batas_unduh_dan_riwayat(): void
    {
        $produk = Product::create([
            'shop_id' => 1, 'category_id' => 1, 'name' => 'Ebook', 'slug' => 'ebook-media',
            'product_type' => 'digital', 'digital_file' => 'digital-products/ebook.pdf',
            'price' => 50000, 'current_stock' => 0, 'status' => 'approved', 'published' => true,
        ]);

        $this->assertTrue($produk->butuhLisensiDigital());

        $fisik = Product::create([
            'shop_id' => 1, 'category_id' => 1, 'name' => 'Buku', 'slug' => 'buku-media',
            'product_type' => 'physical',
            'price' => 80000, 'current_stock' => 3, 'status' => 'approved', 'published' => true,
        ]);
        $this->assertFalse($fisik->butuhLisensiDigital());

        $satu = $produk->buatLisensiDigital(101, 2);
        $dua = $produk->buatLisensiDigital(102, 2);

        $this->assertNotNull($satu);
        $this->assertNotSame($satu->license_key, $dua->license_key);

        // Idempoten: pembelian yang sama tidak digandakan.
        $this->assertSame($satu->license_key, $produk->buatLisensiDigital(101, 2)->license_key);
        $this->assertSame($satu->license_key, $produk->lisensiUntukOrderItem(101)->license_key);

        $hasil = Product::catatUnduhanLisensi($satu->license_key, '1.2.3.4', 'Tes-Agen');
        $this->assertTrue($hasil['ok']);
        $this->assertSame(1, $hasil['sisa']);

        $hasil = Product::catatUnduhanLisensi($satu->license_key);
        $this->assertTrue($hasil['ok']);
        $this->assertSame(0, $hasil['sisa']);

        // Batas habis → ditolak.
        $habis = Product::catatUnduhanLisensi($satu->license_key);
        $this->assertFalse($habis['ok']);
        $this->assertStringContainsString('Batas unduh', $habis['pesan']);

        // Riwayat tercatat dua kali.
        $this->assertCount(2, Product::riwayatUnduhanLisensi($satu->license_key));

        // Kunci asing ditolak.
        $asing = Product::catatUnduhanLisensi('LIC-TIDAK-ADA');
        $this->assertFalse($asing['ok']);

        // Pencabutan menghentikan unduhan berikutnya.
        $this->assertTrue(Product::cabutLisensi($dua->license_key));
        $cabut = Product::catatUnduhanLisensi($dua->license_key);
        $this->assertFalse($cabut['ok']);
        $this->assertStringContainsString('dicabut', $cabut['pesan']);
    }

    public function test_koleksi_tematik_terkurasi_dan_jadwal_tampil(): void
    {
        $produk = Product::create([
            'shop_id' => 1, 'category_id' => 1, 'name' => 'Tas Sekolah', 'slug' => 'tas-sekolah-media',
            'price' => 150000, 'current_stock' => 7, 'status' => 'approved', 'published' => true,
        ]);

        $sekarang = now();
        $aktifId = DB::table('thematic_collections')->insertGetId([
            'name' => 'Back to School', 'slug' => 'back-to-school',
            'description' => 'Perlengkapan kembali ke sekolah.',
            'is_active' => true,
            'starts_at' => $sekarang->copy()->subDay(), 'ends_at' => $sekarang->copy()->addWeek(),
            'sort_order' => 1, 'created_at' => $sekarang, 'updated_at' => $sekarang,
        ]);
        $mendatangId = DB::table('thematic_collections')->insertGetId([
            'name' => 'Lebaran', 'slug' => 'lebaran',
            'is_active' => true,
            'starts_at' => $sekarang->copy()->addMonth(), 'ends_at' => null,
            'sort_order' => 2, 'created_at' => $sekarang, 'updated_at' => $sekarang,
        ]);
        $nonaktifId = DB::table('thematic_collections')->insertGetId([
            'name' => 'Arsip', 'slug' => 'arsip',
            'is_active' => false, 'starts_at' => null, 'ends_at' => null,
            'sort_order' => 3, 'created_at' => $sekarang, 'updated_at' => $sekarang,
        ]);

        foreach ([$aktifId, $mendatangId, $nonaktifId] as $cid) {
            DB::table('thematic_collection_product')->insert([
                'thematic_collection_id' => $cid, 'product_id' => $produk->id,
                'sort_order' => 0, 'created_at' => $sekarang, 'updated_at' => $sekarang,
            ]);
        }

        // Hanya koleksi aktif dalam jadwal yang terbaca di PDP.
        $koleksi = $produk->koleksiTematikAktif();
        $this->assertSame(['Back to School'], $koleksi->pluck('name')->all());

        // Landing: daftar tayang + anggota koleksi.
        $this->assertSame(['Back to School'], Product::koleksiTematikTayang()->pluck('name')->all());
        $anggota = Product::produkKoleksi('back-to-school');
        $this->assertSame(['Tas Sekolah'], $anggota->pluck('name')->all());
        $this->assertSame([], Product::produkKoleksi('tidak-ada')->all());
    }

    public function test_panduan_kategori_dari_atribut_existing(): void
    {
        $fashionId = DB::table('categories')->insertGetId(['name' => 'Fashion Pakaian', 'slug' => 'fashion-pakaian']);
        $makananId = DB::table('categories')->insertGetId(['name' => 'Makanan Ringan', 'slug' => 'makanan-ringan']);

        $ukuranId = DB::table('attributes')->insertGetId(['name' => 'Ukuran']);
        $kaloriId = DB::table('attributes')->insertGetId(['name' => 'Kalori']);
        $proteinId = DB::table('attributes')->insertGetId(['name' => 'Protein']);

        $mId = DB::table('attribute_values')->insertGetId(['attribute_id' => $ukuranId, 'value' => 'M: LD 96 cm, P 68 cm']);
        $kalId = DB::table('attribute_values')->insertGetId(['attribute_id' => $kaloriId, 'value' => '450 kkal per 100 g']);
        $proId = DB::table('attribute_values')->insertGetId(['attribute_id' => $proteinId, 'value' => '8 g per 100 g']);

        $kaos = Product::create([
            'shop_id' => 1, 'category_id' => $fashionId, 'name' => 'Kaos', 'slug' => 'kaos-panduan',
            'price' => 100000, 'current_stock' => 5, 'status' => 'approved', 'published' => true,
        ]);
        DB::table('product_attributes')->insert([
            'product_id' => $kaos->id, 'attribute_id' => $ukuranId, 'attribute_value_id' => $mId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $panduan = $kaos->panduanKategori();
        $this->assertSame('ukuran', $panduan['jenis']);
        $this->assertSame('Panduan Ukuran', $panduan['judul']);
        $this->assertSame('Ukuran', $panduan['baris'][0]['label']);
        $this->assertStringContainsString('LD 96', $panduan['baris'][0]['nilai']);

        $snack = Product::create([
            'shop_id' => 1, 'category_id' => $makananId, 'name' => 'Keripik', 'slug' => 'keripik-panduan',
            'price' => 20000, 'current_stock' => 20, 'status' => 'approved', 'published' => true,
        ]);
        foreach ([[$kaloriId, $kalId], [$proteinId, $proId]] as [$aid, $avid]) {
            DB::table('product_attributes')->insert([
                'product_id' => $snack->id, 'attribute_id' => $aid, 'attribute_value_id' => $avid,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $gizi = $snack->panduanKategori();
        $this->assertSame('nutrisi', $gizi['jenis']);
        $this->assertSame('Informasi Nilai Gizi', $gizi['judul']);
        $this->assertCount(2, $gizi['baris']);

        // Produk tanpa atribut relevan → tidak ada panduan.
        $polos = Product::create([
            'shop_id' => 1, 'category_id' => $fashionId, 'name' => 'Polos', 'slug' => 'polos-panduan',
            'price' => 10000, 'current_stock' => 1, 'status' => 'approved', 'published' => true,
        ]);
        $this->assertSame([], $polos->panduanKategori());
    }

    public function test_migrasi_katalog_media_up_dan_down(): void
    {
        $migrasi = require base_path('database/migrations/2026_09_30_060000_catalog_media.php');

        // Mulai dari skema lama: varian tanpa kolom images, tanpa tabel baru.
        Schema::dropIfExists('thematic_collection_product');
        Schema::dropIfExists('thematic_collections');
        Schema::dropIfExists('digital_license_downloads');
        Schema::dropIfExists('digital_licenses');
        Schema::dropIfExists('product_variants');
        $this->buatTabelVarianLama();

        $this->assertFalse(Schema::hasColumn('product_variants', 'images'));

        $migrasi->up();

        $this->assertTrue(Schema::hasColumn('product_variants', 'images'));
        $this->assertTrue(Schema::hasTable('digital_licenses'));
        $this->assertTrue(Schema::hasTable('digital_license_downloads'));
        $this->assertTrue(Schema::hasTable('thematic_collections'));
        $this->assertTrue(Schema::hasTable('thematic_collection_product'));
        $this->assertTrue(Schema::hasColumn('digital_licenses', 'license_key'));
        $this->assertTrue(Schema::hasColumn('thematic_collections', 'starts_at'));

        // Idempoten: up kedua kali tidak galat.
        $migrasi->up();

        $migrasi->down();

        $this->assertFalse(Schema::hasTable('digital_licenses'));
        $this->assertFalse(Schema::hasTable('digital_license_downloads'));
        $this->assertFalse(Schema::hasTable('thematic_collections'));
        $this->assertFalse(Schema::hasTable('thematic_collection_product'));
        $this->assertFalse(Schema::hasColumn('product_variants', 'images'));
    }

    /* ------------------------- tabel uji mandiri ------------------------- */

    private function buatTabelKatalog(): void
    {
        Schema::create('categories', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('parent_id')->nullable();
            $t->string('name');
            $t->string('slug')->nullable();
        });

        Schema::create('products', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('shop_id');
            $t->unsignedInteger('category_id');
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('thumbnail')->nullable();
            $t->json('images')->nullable();
            $t->string('product_type')->default('physical');
            $t->string('digital_file')->nullable();
            $t->decimal('price', 15, 2)->default(0);
            $t->decimal('special_price', 15, 2)->nullable();
            $t->unsignedInteger('current_stock')->default(0);
            $t->string('status')->default('pending');
            $t->boolean('published')->default(false);
            $t->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('product_id');
            $t->string('sku')->nullable();
            $t->string('variant')->nullable();
            $t->json('variant_attributes')->nullable();
            $t->json('images')->nullable();
            $t->decimal('price', 15, 2)->default(0);
            $t->decimal('special_price', 15, 2)->nullable();
            $t->unsignedInteger('stock')->default(0);
            $t->timestamps();
        });

        Schema::create('attributes', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name');
        });

        Schema::create('attribute_values', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('attribute_id');
            $t->string('value');
        });

        Schema::create('product_attributes', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('product_id');
            $t->unsignedInteger('attribute_id');
            $t->unsignedInteger('attribute_value_id');
            $t->timestamps();
        });

        Schema::create('digital_licenses', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('product_id');
            $t->unsignedBigInteger('order_item_id')->nullable()->unique();
            $t->string('license_key', 64)->unique();
            $t->unsignedInteger('max_downloads')->default(5);
            $t->unsignedInteger('download_count')->default(0);
            $t->boolean('revoked')->default(false);
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });

        Schema::create('digital_license_downloads', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('digital_license_id');
            $t->timestamp('downloaded_at')->nullable();
            $t->string('ip_hash', 64)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->timestamps();
        });

        Schema::create('thematic_collections', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name', 160);
            $t->string('slug', 190)->unique();
            $t->text('description')->nullable();
            $t->string('banner', 255)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('thematic_collection_product', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('thematic_collection_id');
            $t->unsignedInteger('product_id');
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });
    }

    private function buatTabelVarianLama(): void
    {
        Schema::create('product_variants', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('product_id');
            $t->string('sku')->nullable();
            $t->string('variant')->nullable();
            $t->json('variant_attributes')->nullable();
            $t->decimal('price', 15, 2)->default(0);
            $t->decimal('special_price', 15, 2)->nullable();
            $t->unsignedInteger('stock')->default(0);
            $t->timestamps();
        });
    }
}
