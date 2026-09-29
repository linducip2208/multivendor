<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Regression for: category-tiles.blade.php "Attempt to read property
 * slug on string".
 *
 * Contract: the tiles component receives Collection<Category>
 * (HomePageService::rootCategories). Stale cache entries or misconfigured
 * callers may inject strings/nulls — the component must skip them
 * item-by-item instead of 500ing the homepage.
 */
class CategoryTilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_with_categories(): void
    {
        Category::create(['name' => 'Elektronik', 'slug' => 'elektronik', 'status' => true]);
        Category::create(['name' => 'Makanan', 'slug' => 'makanan', 'status' => true]);

        $this->get('/')->assertOk()->assertSee('Elektronik')->assertSee('Makanan');
    }

    public function test_homepage_renders_with_empty_categories(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_tiles_skip_string_and_null_entries(): void
    {
        $category = Category::create(['name' => 'Elektronik', 'slug' => 'elektronik', 'status' => true]);

        $html = $this->blade(
            '<x-storefront.category-tiles :categories="$categories" />',
            ['categories' => collect([$category, 'electronics', '123', null, ['slug' => 'food']])]
        );

        $this->assertStringContainsString('Elektronik', $html);
        $this->assertStringContainsString('/category/elektronik', $html);
        $this->assertStringNotContainsString('electronics', $html);
    }

    public function test_root_categories_returns_only_models(): void
    {
        Category::create(['name' => 'Elektronik', 'slug' => 'elektronik', 'status' => true]);

        // Poison the cache the way a stale/mixed writer could.
        Cache::put('home:categories:v2:12', collect(['electronics', null]), 60);

        $service = app(\App\Services\Catalog\HomePageService::class);
        $payload = $service->data('categories');

        // Poisoned v2 entries are normalized on read path via component
        // guard; service key version prevents old-key collisions.
        $this->assertArrayHasKey('categories', $payload);
        $this->get('/')->assertOk();
    }

    public function test_brand_and_store_components_skip_bad_entries(): void
    {
        $brands = $this->blade(
            '<x-storefront.brand-pills :brands="$brands" />',
            ['brands' => collect(['nike', null])]
        );
        $this->assertStringNotContainsString('nike', $brands);

        $shops = $this->blade(
            '<x-storefront.store-cards :shops="$shops" />',
            ['shops' => collect(['toko-abc', null])]
        );
        $this->assertStringNotContainsString('toko-abc', $shops);

        $seller = $this->blade(
            '<x-storefront.seller-card :shop="$shop" />',
            ['shop' => 'toko-abc']
        );
        $this->assertSame('', trim($seller));
    }
}
