<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regression for production log errors:
 * - relevance-sort search must run on any SQL driver (was MySQL-only
 *   LEAST(), fatal on sqlite and untestable);
 * - numeric typo tokens (e.g. brand "361") must not TypeError the
 *   SpellCorrector / TypoWindow path;
 * - hero/promo slides payloads with string entries must not 500 home.
 */
class SearchRelevanceTest extends TestCase
{
    use RefreshDatabase;

    private function seedShop(string $suffix = ''): Shop
    {
        $vendor = User::create([
            'name' => 'V'.$suffix, 'email' => "v{$suffix}@t.co",
            'password' => Hash::make('x'), 'role' => 'vendor', 'status' => 'active',
        ]);

        return Shop::create([
            'vendor_id' => $vendor->id, 'name' => 'S'.$suffix, 'slug' => 's'.$suffix,
            'commission_type' => 'percentage', 'commission_value' => 5, 'status' => 'active',
        ]);
    }

    public function test_relevance_sort_runs_and_ranks_exact_match_first(): void
    {
        $shop = $this->seedShop();
        $cat = Category::create(['name' => 'Sepatu', 'slug' => 'sepatu', 'status' => true]);
        Product::create([
            'shop_id' => $shop->id, 'category_id' => $cat->id, 'name' => 'Tas Ransel',
            'slug' => 'tas-ransel', 'price' => 50000, 'current_stock' => 5,
            'status' => 'approved', 'published' => true,
        ]);
        Product::create([
            'shop_id' => $shop->id, 'category_id' => $cat->id, 'name' => 'Sepatu Lari',
            'slug' => 'sepatu-lari', 'price' => 100000, 'current_stock' => 5,
            'status' => 'approved', 'published' => true,
        ]);

        $this->get('/search?q=sepatu&sort=relevance')
            ->assertOk()
            ->assertSee('Sepatu Lari')
            ->assertSeeInOrder(['Sepatu Lari', 'Tas Ransel']);
    }

    public function test_numeric_tokens_do_not_break_search_or_suggest(): void
    {
        $shop = $this->seedShop('n');
        $cat = Category::create(['name' => 'Sepatu', 'slug' => 'sepatu-n', 'status' => true]);
        Product::create([
            'shop_id' => $shop->id, 'category_id' => $cat->id, 'name' => 'Sepatu 361 Derajat',
            'slug' => 'sepatu-361', 'price' => 200000, 'current_stock' => 3,
            'status' => 'approved', 'published' => true,
        ]);

        $this->get('/search?q=361')->assertOk();
        $this->get('/search/suggest?q=361')->assertOk();
        $this->get('/search/suggest?q=sepa')->assertOk();
    }

    public function test_homepage_survives_string_slides_and_banners(): void
    {
        Banner::create([
            'title' => 'Promo', 'image' => 'banners/promo.jpg',
            'position' => 'hero', 'sort_order' => 1, 'status' => true,
        ]);

        $html = $this->blade(
            '<x-storefront.product-card :product="$product" />',
            ['product' => 'sepatu-string']
        );
        $this->assertSame('', trim($html));

        $this->get('/')->assertOk()->assertSee('Promo');
    }
}
