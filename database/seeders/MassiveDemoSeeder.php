<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MassiveDemoSeeder extends Seeder
{
    /** Kata sifat nama toko khas marketplace Indonesia */
    private array $tokoKata = ['Berkah', 'Jaya', 'Makmur', 'Sejahtera', 'Abadi', 'Sentosa', 'Maju', 'Laris', 'Barokah', 'Sukses', 'Mitra', 'Cahaya', 'Sinar', 'Tunas', 'Wira'];

    private array $tokoJenis = ['Toko', 'Grosir', 'Olshop', 'Lapakan', 'Galerai'];

    private array $kota = ['Jakarta', 'Bandung', 'Surabaya', 'Medan', 'Semarang', 'Yogyakarta', 'Makassar', 'Denpasar', 'Palembang', 'Bekasi'];

    private array $jalan = ['Jl. Merdeka', 'Jl. Sudirman', 'Jl. Pahlawan', 'Jl. Diponegoro', 'Jl. Ahmad Yani', 'Jl. Gajah Mada', 'Jl. Kartini', 'Jl. Melati', 'Jl. Kenanga', 'Jl. Anggrek'];

    private array $deskripsiToko = [
        'Toko terpercaya dengan ribuan produk original dan garansi resmi. Pengiriman cepat ke seluruh Indonesia.',
        'Menyediakan kebutuhan harian dengan harga grosir murah. Pelayanan ramah dan pengemasan rapi.',
        'Pusat belanja online murah dengan promo setiap hari. Semua produk dicek kualitas sebelum dikirim.',
        'Toko online amanah, sudah melayani puluhan ribu pembeli. Bisa retur jika barang tidak sesuai.',
        'Spesialis produk original dengan garansi toko. Chat kami untuk konsultasi sebelum membeli.',
    ];

    private array $varianSatu = ['Pro', 'Max', 'Lite', 'Ultra', 'Plus', 'Neo', 'Elit', 'Dasar', 'Premium', 'Standar'];

    private array $varianDua = ['Seri', 'Edisi', 'Versi', 'Model', 'Tipe', 'Varian', 'Koleksi', 'Paket', 'Bundel', 'Set'];

    private function namaToko(int $i): string
    {
        return $this->tokoJenis[array_rand($this->tokoJenis)] . ' ' . $this->tokoKata[array_rand($this->tokoKata)] . ' ' . $this->tokoKata[array_rand($this->tokoKata)] . ' ' . $i;
    }

    private function deskripsiTokoAcak(): string
    {
        return $this->deskripsiToko[array_rand($this->deskripsiToko)];
    }

    private function alamatTokoAcak(): string
    {
        return $this->jalan[array_rand($this->jalan)] . ' No. ' . rand(1, 200) . ', ' . $this->kota[array_rand($this->kota)];
    }

    public function run(): void
    {
        $this->command->info('Creating 100 brands...');
        $brandNames = ['Apple','Samsung','Xiaomi','Oppo','Vivo','Huawei','Asus','Lenovo','HP','Dell','Acer','MSI','Sony','LG','Panasonic','Toshiba','Sharp','Philips','Canon','Nikon','Adidas','Nike','Puma','Reebok','Converse','Vans','Zara','H&M','Uniqlo','Levi','Gucci','Prada','Chanel','Dior','Hermes','Rolex','Casio','Seiko','Timex','Swatch','IKEA','Informa','ACE','Mitra10','Lotte','Unilever','P&G','Wings','Mayora','Indofood','Nestle','Danone','Frisian Flag','Sari Roti','Yamaha','Honda','Kawasaki','Suzuki','Toyota','Daihatsu','Mitsubishi','Eiger','Consina','Rei','Osprey','Northface','Columbia','Patagonia','Carhartt','Timberland','Crocs','Skechers','New Balance','Under Armour','Wilson','Spalding','Mikasa','Mizuno','Yonex','Li-Ning','Anta','361','Peak','Warrior','Aqua','Le Minerale','Club','Cleo','Vit','Cimory','Greenfields','Ultra Milk','Teh Botol','ABC','Kecap Bango','Sasa','Royco','Masako','Kraft','Heinz','Orang Tua'];
        $categories = Category::where('status', true)->get();
        if ($categories->isEmpty()) { $this->command->warn('No categories, create them first!'); return; }

        $bar = $this->command->getOutput()->createProgressBar(100);
        $brands = [];
        foreach (array_slice($brandNames, 0, 100) as $name) {
            $brands[] = Brand::firstOrCreate(['name' => $name], ['slug' => Str::slug($name), 'status' => true]);
            $bar->advance();
        }
        $bar->finish();
        $this->command->info("\n100 brands created.");

        $this->command->info('Creating 100 vendors with shops...');
        $bar2 = $this->command->getOutput()->createProgressBar(100);
        $shops = [];
        for ($i = 1; $i <= 100; $i++) {
            $namaToko = $this->namaToko($i);
            $vendor = User::firstOrCreate(['email' => "vendor{$i}@multivendor.test"], [
                'name' => "Vendor {$i} - " . $namaToko,
                'password' => 'password',
                'phone' => '08' . rand(100000000, 999999999),
                'role' => 'vendor', 'status' => 'active',
            ]);
            Wallet::firstOrCreate(['user_id' => $vendor->id], ['balance' => rand(100000, 5000000)]);
            $shop = Shop::firstOrCreate(['vendor_id' => $vendor->id], [
                'name' => $namaToko,
                'slug' => 'shop-' . $i . '-' . Str::random(4),
                'description' => $this->deskripsiTokoAcak(),
                'address' => $this->alamatTokoAcak(),
                'phone' => '08' . rand(100000000, 999999999),
                'commission_type' => ['percentage', 'fixed'][rand(0, 1)],
                'commission_value' => rand(2, 15),
                'status' => 'active',
            ]);
            $shops[] = $shop;
            $bar2->advance();
        }
        $bar2->finish();
        $this->command->info("\n100 vendors created.");

        $this->command->info('Creating 30 products per vendor (3000 total)...');
        $bar3 = $this->command->getOutput()->createProgressBar(100);
        foreach ($shops as $shop) {
            for ($j = 1; $j <= 30; $j++) {
                $category = $categories->random();
                $brand = $brands[array_rand($brands)];
                $price = rand(10000, 5000000);
                $varian1 = $this->varianSatu[array_rand($this->varianSatu)];
                $varian2 = $this->varianDua[array_rand($this->varianDua)];
                $namaProduk = $brand->name . ' - ' . $varian1 . ' ' . $varian2;
                $slugProduk = Str::slug($brand->name . '-' . Str::random(4));
                // Ilustrasi unik sesuai kategori + nama produk (bukan placeholder generik).
                $gambar = [];
                for ($v = 0; $v < 3; $v++) {
                    $gambar[] = Support\DemoProductImage::forProduct(
                        $namaProduk, $brand->name, $category->slug, $category->name, $slugProduk, $v
                    );
                }
                $product = Product::create([
                    'shop_id' => $shop->id, 'category_id' => $category->id, 'brand_id' => $brand->id,
                    'name' => $namaProduk,
                    'slug' => $slugProduk,
                    'description' => '<p>Produk berkualitas dengan garansi resmi. Tersedia dalam berbagai varian dan ukuran. Pengiriman cepat ke seluruh Indonesia dengan jasa kirim terpercaya.</p><ul><li>Kualitas terjamin</li><li>Harga bersaing</li><li>Garansi resmi</li><li>Pengiriman cepat</li></ul>',
                    'short_description' => '<p>Produk berkualitas terbaik dengan harga terjangkau. Tersedia di platform multivendor kami.</p>',
                    'thumbnail' => $gambar[0],
                    'images' => json_encode($gambar),
                    'price' => $price, 'current_stock' => rand(0, 500),
                    'sku' => strtoupper(Str::random(8)),
                    'status' => 'approved', 'published' => true, 'created_by' => 'vendor',
                    'meta_title' => 'Jual ' . $namaProduk . ' Murah Garansi Resmi',
                    'meta_description' => 'Beli ' . $namaProduk . ' original dengan harga termurah, garansi resmi, dan pengiriman cepat ke seluruh Indonesia.',
                ]);
                if (rand(0, 3) === 0) {
                    $product->update(['special_price' => $price * (1 - rand(10, 50) / 100), 'discount_type' => 'percentage', 'discount_start' => now(), 'discount_end' => now()->addDays(rand(1, 30))]);
                }
                if (rand(0, 5) === 0) $product->update(['featured' => true]);
            }
            $bar3->advance();
        }
        $bar3->finish();
        $this->command->info("\n3000 products created!");
    }
}
