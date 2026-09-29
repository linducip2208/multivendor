<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\Catalog\HomePageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Seluruh payload homepage harus berupa model; entri cache basi/campuran
 * (string/null) dinormalisasi di service dan dilewati di Blade sehingga
 * tidak pernah 500 (featured_image/slug/price on string).
 */
class HomePayloadGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_survives_poisoned_section_caches(): void
    {
        $vendor = User::create([
            'name' => 'V', 'email' => 'v@t.co', 'password' => Hash::make('x'),
            'role' => 'vendor', 'status' => 'active',
        ]);
        $shop = Shop::create([
            'vendor_id' => $vendor->id, 'name' => 'S', 'slug' => 's',
            'commission_type' => 'percentage', 'commission_value' => 5, 'status' => 'active',
        ]);
        $cat = Category::create(['name' => 'Sepatu', 'slug' => 'sepatu', 'status' => true]);
        Product::create([
            'shop_id' => $shop->id, 'category_id' => $cat->id, 'name' => 'Sepatu Lari',
            'slug' => 'sepatu-lari', 'price' => 100000, 'current_stock' => 5,
            'status' => 'approved', 'published' => true,
        ]);
        Brand::create(['name' => 'B', 'slug' => 'b', 'status' => true]);
        BlogPost::create([
            'author_id' => $vendor->id, 'title' => 'Tips', 'slug' => 'tips',
            'content' => 'Isi artikel.', 'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        // Racuni cache dengan payload campuran seperti cache basi.
        Cache::put('home:blog:v2:4', collect(['tips-string', null]), 60);
        Cache::put('home:categories:v2:12', collect(['elektronik']), 60);
        Cache::put('home:brands:12', collect(['nike']), 60);

        $this->get('/')->assertOk();

        $service = app(HomePageService::class);
        foreach ($service->data('blog')['posts'] ?? [] as $post) {
            $this->assertInstanceOf(BlogPost::class, $post);
        }
    }
}
