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
            $vendor = User::firstOrCreate(['email' => "vendor{$i}@multivendor.test"], [
                'name' => "Vendor {$i} - " . fake()->company(),
                'password' => 'password',
                'phone' => '08' . rand(100000000, 999999999),
                'role' => 'vendor', 'status' => 'active',
            ]);
            Wallet::firstOrCreate(['user_id' => $vendor->id], ['balance' => rand(100000, 5000000)]);
            $shop = Shop::firstOrCreate(['vendor_id' => $vendor->id], [
                'name' => fake()->company() . ' Store',
                'slug' => 'shop-' . $i . '-' . Str::random(4),
                'description' => fake()->paragraph(),
                'address' => fake()->address(),
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
        $placeholderImages = [];
        for ($i = 0; $i < 10; $i++) { $placeholderImages[] = 'products/placeholder' . $i . '.svg'; }
        foreach ($shops as $shop) {
            for ($j = 1; $j <= 30; $j++) {
                $category = $categories->random();
                $brand = $brands[array_rand($brands)];
                $price = rand(10000, 5000000);
                $product = Product::create([
                    'shop_id' => $shop->id, 'category_id' => $category->id, 'brand_id' => $brand->id,
                    'name' => fake()->words(rand(2, 4), true),
                    'slug' => Str::slug(fake()->words(3, true) . '-' . Str::random(4)),
                    'description' => '<p>' . fake()->paragraph(3) . '</p><ul><li>' . implode('</li><li>', fake()->sentences(4)) . '</li></ul>',
                    'short_description' => '<p>' . fake()->sentence() . '</p>',
                    'thumbnail' => $placeholderImages[array_rand($placeholderImages)],
                    'images' => json_encode(array_slice($placeholderImages, 0, rand(3, 5))),
                    'price' => $price, 'current_stock' => rand(0, 500),
                    'sku' => strtoupper(Str::random(8)),
                    'status' => 'approved', 'published' => true, 'created_by' => 'vendor',
                    'meta_title' => fake()->sentence(),
                    'meta_description' => fake()->sentence(10),
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
