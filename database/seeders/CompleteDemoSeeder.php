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
            $post = BlogPost::create([
                'author_id' => User::where('role', 'admin')->first()->id ?? 1,
                'title' => fake()->sentence(rand(4, 8)),
                'slug' => 'blog-post-' . $i . '-' . Str::random(4),
                'content' => '<h2>' . fake()->sentence() . '</h2><p>' . fake()->paragraphs(3, true) . '</p><blockquote>' . fake()->sentence() . '</blockquote><p>' . fake()->paragraphs(2, true) . '</p>',
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
        Provider::firstOrCreate(['name' => 'Midtrans Sandbox'], ['type' => 'payment', 'api_format' => 'midtrans-snap', 'base_url' => 'https://app.sandbox.midtrans.com/snap/v1', 'is_active' => true, 'description' => 'Midtrans Sandbox']);
        Provider::firstOrCreate(['name' => 'RajaOngkir Starter'], ['type' => 'shipping', 'api_format' => 'rajaongkir-starter', 'base_url' => 'https://api.rajaongkir.com/starter', 'is_active' => true, 'description' => 'RajaOngkir Starter']);
        Provider::firstOrCreate(['name' => 'DeepSeek AI'], ['type' => 'ai', 'api_format' => 'openai-compatible', 'base_url' => 'https://api.deepseek.com/v1', 'is_active' => true, 'description' => 'DeepSeek Chat']);
        Provider::firstOrCreate(['name' => 'Gmail SMTP'], ['type' => 'mail', 'api_format' => 'smtp', 'base_url' => 'smtp.gmail.com', 'is_active' => true, 'description' => 'SMTP mail']);

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
