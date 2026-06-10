<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Coupon;
use App\Models\CustomerAddress;
use App\Models\DealOfTheDay;
use App\Models\FlashDeal;
use App\Models\GroupBuy;
use App\Models\LoyaltyPoint;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\PriceAlert;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductReview;
use App\Models\Provider;
use App\Models\PushNotification;
use App\Models\ShopFollower;
use App\Models\SocialFeed;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VatTax;
use App\Models\Wallet;
use App\Models\Wishlist;
use App\Models\Shop;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CompleteDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('=== COMPLETE DEMO DATA SEEDER ===');

        // 1. More Customers
        $this->command->info('Creating customers...');
        $customers = [];
        for ($i = 1; $i <= 1000; $i++) {
            $customers[] = User::firstOrCreate(['email' => "customer{$i}@demo.test"], [
                'name' => fake()->name(), 'password' => 'password',
                'phone' => '08' . rand(100000000, 999999999),
                'role' => 'customer', 'status' => 'active',
                'referral_code' => Str::random(8),
            ]);
            Wallet::firstOrCreate(['user_id' => User::where('email', "customer{$i}@demo.test")->first()->id], ['balance' => rand(0, 5000000)]);
        }

        // 2. Delivery Men
        $this->command->info('Creating delivery men...');
        $deliveryMen = [];
        for ($i = 1; $i <= 50; $i++) {
            $dm = User::firstOrCreate(['email' => "delivery{$i}@demo.test"], [
                'name' => fake()->name(), 'password' => 'password',
                'phone' => '08' . rand(100000000, 999999999),
                'role' => 'delivery', 'status' => 'active',
            ]);
            Wallet::firstOrCreate(['user_id' => $dm->id], ['balance' => rand(0, 2000000)]);
            $deliveryMen[] = $dm;
        }

        // 3. Employees
        $this->command->info('Creating employees...');
        for ($i = 1; $i <= 50; $i++) {
            User::firstOrCreate(['email' => "employee{$i}@demo.test"], [
                'name' => fake()->name(), 'password' => 'password',
                'role' => 'employee', 'status' => 'active',
            ]);
        }

        $shops = Shop::where('status', 'active')->get();
        $products = Product::where('status', 'approved')->get();
        $allCustomers = User::where('role', 'customer')->get();

        // 4. Coupons (1000)
        $this->command->info('Creating 1000 coupons...');
        for ($i = 1; $i <= 1000; $i++) {
            Coupon::firstOrCreate(['code' => 'CPN' . str_pad($i, 5, '0', STR_PAD_LEFT)], [
                'title' => fake()->sentence(3),
                'coupon_type' => ['percentage', 'fixed', 'free_shipping'][rand(0, 2)],
                'discount_value' => rand(5, 50),
                'min_purchase' => rand(10000, 500000),
                'max_discount' => rand(1, 5) === 1 ? rand(10000, 100000) : null,
                'start_date' => now()->subDays(rand(0, 30)),
                'end_date' => now()->addDays(rand(1, 90)),
                'usage_limit' => rand(1, 5) === 1 ? null : rand(10, 1000),
                'usage_count' => rand(0, 100),
                'status' => rand(1, 10) > 2,
                'shop_id' => rand(1, 3) === 1 ? $shops->random()->id : null,
            ]);
        }

        // 5. Flash Deals
        $this->command->info('Creating flash deals...');
        for ($i = 1; $i <= 50; $i++) {
            $deal = FlashDeal::create([
                'title' => 'Flash Deal #' . $i . ' - ' . fake()->words(2, true),
                'start_date' => now()->subDays(rand(0, 3)),
                'end_date' => now()->addDays(rand(1, 7)),
                'status' => true,
                'featured' => rand(1, 3) === 1,
            ]);
            $dealProducts = $products->random(rand(3, 10));
            foreach ($dealProducts as $p) {
                $deal->products()->attach($p->id, [
                    'discount_type' => ['flat', 'percentage'][rand(0, 1)],
                    'discount_value' => rand(10, 40),
                ]);
            }
        }

        // 6. Deal of the Day
        $this->command->info('Creating deal of the day...');
        for ($i = 1; $i <= 100; $i++) {
            DealOfTheDay::create([
                'product_id' => $products->random()->id,
                'discount_type' => ['flat', 'percentage'][rand(0, 1)],
                'discount_value' => rand(5, 30),
                'date' => now()->subDays(rand(0, 30)),
            ]);
        }

        // 7. Banners
        $this->command->info('Creating banners...');
        $positions = ['hero', 'sidebar', 'footer', 'popup'];
        for ($i = 1; $i <= 100; $i++) {
            Banner::create([
                'title' => 'Banner #' . $i,
                'subtitle' => fake()->sentence(),
                'image' => 'https://picsum.photos/seed/banner' . $i . '/800/400',
                'link' => rand(1, 2) === 1 ? 'https://example.com' : null,
                'position' => $positions[array_rand($positions)],
                'sort_order' => $i,
                'status' => true,
            ]);
        }

        // 8. Blog Posts
        $this->command->info('Creating blog posts...');
        $blogCat = BlogCategory::firstOrCreate(['name' => 'Umum'], ['slug' => 'umum']);
        $blogCat2 = BlogCategory::firstOrCreate(['name' => 'Tips Bisnis'], ['slug' => 'tips-bisnis']);
        for ($i = 1; $i <= 100; $i++) {
            $title = match($i % 10) {
                1 => 'Cara Memulai Bisnis Online Tanpa Modal di Tahun ' . date('Y'),
                2 => '10 Strategi Marketing Digital untuk Meningkatkan Penjualan',
                3 => 'Panduan Lengkap Memilih Payment Gateway untuk Toko Online',
                4 => 'Kenapa Marketplace Multivendor Lebih Menguntungkan',
                5 => 'Tips Mengelola Stok Produk agar Tidak Kehabisan',
                6 => 'Cara Meningkatkan Traffic Toko Online dengan SEO',
                7 => 'Perbandingan Jasa Pengiriman: JNE vs J&T vs SiCepat',
                8 => 'Bagaimana AI Membantu Analisis Penjualan Bisnis Anda',
                9 => 'Tren E-Commerce Indonesia yang Harus Anda Tahu',
                default => 'Rahasia Sukses Vendor di Platform Multivendor',
            };
            $slug = Str::slug($title) . '-' . $i;
            $content = match($i % 5) {
                0 => '<h2>Memulai Bisnis Online</h2><p>Bisnis online di Indonesia terus berkembang pesat. Dengan platform multivendor, Anda bisa memulai toko online tanpa perlu membuat website dari nol. Cukup daftar sebagai vendor, upload produk, dan mulai jualan.</p><p>Keuntungan menggunakan platform multivendor: tidak perlu pusing mikirin payment gateway, ongkos kirim sudah terintegrasi, dan Anda bisa fokus ke produk.</p>',
                1 => '<h2>Payment Gateway Indonesia</h2><p>Memilih payment gateway yang tepat sangat penting untuk kelancaran transaksi. Platform kami mendukung 10 payment gateway Indonesia: Midtrans, Xendit, Tripay, Duitku, OY Indonesia, iPaymu, Faspay, DOKU, dan ESIA Pay.</p><p>Semua gateway bisa diaktifkan dengan API key Anda sendiri. Sistem pembayaran aman dengan enkripsi AES-256.</p>',
                2 => '<h2>Ongkos Kirim & Logistik</h2><p>Pengiriman adalah salah satu faktor penentu kepuasan pelanggan. Platform kami terintegrasi dengan 16 jasa pengiriman: JNE, J&T Express, SiCepat, TIKI, POS Indonesia, AnterAja, Lion Parcel, dan lainnya.</p><p>Customer bisa membandingkan ongkir dari berbagai kurir sebelum checkout, sehingga mendapatkan harga terbaik.</p>',
                3 => '<h2>AI untuk Bisnis</h2><p>Kecerdasan buatan (AI) kini bisa membantu analisis bisnis Anda. Platform kami mendukung 10 AI provider termasuk DeepSeek, OpenAI, Groq, Mistral, dan Ollama (gratis, self-hosted).</p><p>AI bisa menganalisis produk paling laris, memberikan rekomendasi strategi penjualan, dan memprediksi tren pasar.</p>',
                default => '<h2>Tips Sukses Berjualan Online</h2><p>Kunci sukses berjualan online: foto produk yang menarik, deskripsi yang jelas, harga yang kompetitif, dan pelayanan yang responsif. Gunakan fitur kupon dan flash deal untuk menarik pembeli.</p><p>Jangan lupa untuk selalu update stok dan merespon pesanan dengan cepat. Customer yang puas akan kembali berbelanja.</p>',
            };
            $post = BlogPost::create([
                'author_id' => User::where('role', 'admin')->first()->id ?? 1,
                'title' => $title,
                'slug' => $slug,
                'content' => $content,
                'excerpt' => fake()->sentence(15),
                'is_published' => true,
                'published_at' => now()->subDays(rand(0, 365)),
                'meta_title' => fake()->sentence(),
                'meta_description' => fake()->sentence(10),
            ]);
            $post->categories()->attach(rand(1, 3) === 1 ? $blogCat2->id : $blogCat->id);
        }

        // 9. Push Notifications
        $this->command->info('Creating push notifications...');
        for ($i = 1; $i <= 100; $i++) {
            PushNotification::create([
                'title' => fake()->sentence(4),
                'description' => fake()->sentence(10),
                'target_type' => ['all', 'customer', 'vendor'][rand(0, 2)],
                'sent' => rand(1, 2) === 1,
                'sent_at' => rand(1, 2) === 1 ? now()->subDays(rand(0, 30)) : null,
            ]);
        }

        // 10. VAT/Tax
        $this->command->info('Creating VAT/tax data...');
        VatTax::create(['name' => 'PPN 11%', 'rate' => 11.00, 'is_active' => true]);
        VatTax::create(['name' => 'PPN 0% (Non-BKP)', 'rate' => 0, 'is_active' => true]);

        // 11. Support Tickets
        $this->command->info('Creating support tickets...');
        for ($i = 1; $i <= 100; $i++) {
            $ticket = SupportTicket::create([
                'customer_id' => $allCustomers->random()->id,
                'subject' => fake()->sentence(),
                'type' => ['order', 'product', 'payment', 'account', 'other'][rand(0, 4)],
                'priority' => ['low', 'medium', 'high', 'urgent'][rand(0, 3)],
                'description' => fake()->paragraph(),
                'status' => ['open', 'in_progress', 'resolved', 'closed'][rand(0, 3)],
            ]);
            if (rand(1, 2) === 1) {
                SupportTicketReply::create(['support_ticket_id' => $ticket->id, 'user_id' => 1, 'message' => fake()->paragraph()]);
            }
        }

        // 12. Product Reviews
        $this->command->info('Creating 1000 product reviews...');
        for ($i = 1; $i <= 1000; $i++) {
            ProductReview::create([
                'product_id' => $products->random()->id,
                'customer_id' => $allCustomers->random()->id,
                'rating' => rand(1, 5),
                'comment' => fake()->sentence(rand(3, 15)),
                'status' => true,
            ]);
        }

        // 13. Wishlist
        $this->command->info('Creating wishlist items...');
        for ($i = 1; $i <= 1000; $i++) {
            try {
                Wishlist::create([
                    'customer_id' => $allCustomers->random()->id,
                    'product_id' => $products->random()->id,
                ]);
            } catch (\Exception $e) {}
        }

        // 14. Shop Followers
        $this->command->info('Creating shop followers...');
        for ($i = 1; $i <= 500; $i++) {
            try {
                ShopFollower::create([
                    'customer_id' => $allCustomers->random()->id,
                    'shop_id' => $shops->random()->id,
                ]);
            } catch (\Exception $e) {}
        }

        // 15. Provider presets
        $this->command->info('Creating provider presets...');
        $providers = [
            ['name'=>'Midtrans Sandbox','type'=>'payment','api_format'=>'midtrans-snap','base_url'=>'https://app.sandbox.midtrans.com/snap/v1','is_active'=>true,'description'=>'Midtrans Snap — isi Server Key'],
            ['name'=>'Midtrans Core','type'=>'payment','api_format'=>'midtrans-core','base_url'=>'https://api.sandbox.midtrans.com/v2','is_active'=>true,'description'=>'Midtrans Core API — isi Server Key'],
            ['name'=>'Xendit Sandbox','type'=>'payment','api_format'=>'xendit-invoice','base_url'=>'https://api.xendit.co','is_active'=>true,'description'=>'Xendit Invoice — isi API Key'],
            ['name'=>'Tripay Sandbox','type'=>'payment','api_format'=>'tripay-closed','base_url'=>'https://tripay.co.id/api-sandbox','is_active'=>true,'config'=>['merchant_code'=>'T50251'],'description'=>'Tripay Closed — isi API Key + Private Key'],
            ['name'=>'RajaOngkir Starter','type'=>'shipping','api_format'=>'rajaongkir-starter','base_url'=>'https://api.rajaongkir.com/starter','is_active'=>true,'description'=>'RajaOngkir Starter — isi API Key'],
            ['name'=>'RajaOngkir Pro','type'=>'shipping','api_format'=>'rajaongkir-pro','base_url'=>'https://pro.rajaongkir.com/api','is_active'=>true,'description'=>'RajaOngkir Pro — isi API Key'],
            ['name'=>'JNE Shipping','type'=>'shipping','api_format'=>'courier-rest','base_url'=>'https://api.jne.co.id','is_active'=>true,'description'=>'JNE direct API — isi API Key'],
            ['name'=>'J&T Express','type'=>'shipping','api_format'=>'courier-rest','base_url'=>'https://api.jtexpress.co.id','is_active'=>true,'description'=>'J&T direct API — isi API Key'],
            ['name'=>'SiCepat','type'=>'shipping','api_format'=>'courier-rest','base_url'=>'https://api.sicepat.com','is_active'=>true,'description'=>'SiCepat direct API — isi API Key'],
            ['name'=>'DeepSeek AI','type'=>'ai','api_format'=>'openai-compatible','base_url'=>'https://api.deepseek.com/v1','is_active'=>true,'config'=>['default_model'=>'deepseek-chat'],'description'=>'DeepSeek — isi API Key'],
            ['name'=>'OpenAI','type'=>'ai','api_format'=>'openai-compatible','base_url'=>'https://api.openai.com/v1','is_active'=>true,'config'=>['default_model'=>'gpt-4.1-nano'],'description'=>'OpenAI — isi API Key'],
            ['name'=>'Groq','type'=>'ai','api_format'=>'openai-compatible','base_url'=>'https://api.groq.com/openai/v1','is_active'=>true,'config'=>['default_model'=>'llama-4-scout-17b-16e-instruct'],'description'=>'Groq — isi API Key'],
            ['name'=>'Ollama (Self-hosted)','type'=>'ai','api_format'=>'openai-compatible','base_url'=>'http://localhost:11434/v1','is_active'=>false,'config'=>['default_model'=>'llama3.2'],'description'=>'Ollama self-hosted — gratis, tanpa API key'],
            ['name'=>'OpenRouter','type'=>'ai','api_format'=>'openai-compatible','base_url'=>'https://openrouter.ai/api/v1','is_active'=>true,'config'=>['default_model'=>'openai/gpt-4.1-nano'],'description'=>'OpenRouter — isi API Key'],
            ['name'=>'Gmail SMTP','type'=>'mail','api_format'=>'smtp','base_url'=>'smtp.gmail.com','is_active'=>false,'description'=>'SMTP Gmail'],
        ];
        foreach ($providers as $data) {
            Provider::firstOrCreate(['name' => $data['name']], $data);
        }

        // 16. Product Bundles
        $this->command->info('Creating product bundles...');
        for ($i = 1; $i <= 30; $i++) {
            $bundle = ProductBundle::create(['title' => 'Bundle Hemat #' . $i, 'discount_percentage' => rand(5, 25), 'is_active' => true]);
            $bundle->products()->attach($products->random(rand(2, 5))->pluck('id')->toArray());
        }

        // 17. Social Feed
        $this->command->info('Creating social feed...');
        for ($i = 1; $i <= 100; $i++) {
            SocialFeed::create([
                'product_id' => $products->random()->id,
                'shop_id' => $shops->random()->id,
                'caption' => fake()->sentence(),
                'is_active' => true,
                'views' => rand(100, 10000),
                'likes' => rand(10, 1000),
            ]);
        }

        // 18. Group Buys
        $this->command->info('Creating group buys...');
        for ($i = 1; $i <= 50; $i++) {
            $p = $products->random();
            GroupBuy::create([
                'product_id' => $p->id,
                'target_count' => rand(5, 20),
                'current_count' => rand(0, 10),
                'discount_percentage' => rand(15, 50),
                'special_price' => $p->price * (1 - rand(15, 50) / 100),
                'end_date' => now()->addDays(rand(1, 14)),
                'is_active' => true,
            ]);
        }

        // 19. Loyalty Points
        $this->command->info('Creating loyalty points...');
        foreach ($allCustomers->random(200) as $c) {
            LoyaltyPoint::firstOrCreate(['customer_id' => $c->id], ['points' => rand(100, 5000)]);
        }

        $this->command->info('=== COMPLETE DEMO DATA DONE ===');
    }
}
