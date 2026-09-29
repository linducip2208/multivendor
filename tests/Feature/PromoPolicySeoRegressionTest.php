<?php

namespace Tests\Feature;

use App\Models\FlashDeal;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use App\Support\Currency;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * AGENTS 15+16+17+19 regression suite.
 *
 * Self-contained: builds its own minimal tables on sqlite :memory: and never
 * uses RefreshDatabase (project migrations may be incompatible with the test
 * driver). Covers:
 *  (a) promotion math edge cases (0/1/10/50/99/cap/fixed/expired/invalid),
 *  (b) best-discount ordering never references a missing column,
 *  (c) Product/Order policy scoping (admin/vendor-own/vendor-other/customer),
 *  (d) x-seo components always emit title/meta/canonical/OG + safe JSON-LD.
 */
class PromoPolicySeoRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Currency::flush();

        Schema::dropIfExists('flash_deal_products');
        Schema::dropIfExists('flash_deals');
        Schema::dropIfExists('products');
        Schema::dropIfExists('system_settings');

        // Minimal system_settings so App\Support\Currency::config() (used by
        // x-seo components) resolves defaults without project migrations.
        Schema::create('system_settings', function (Blueprint $t) {
            $t->increments('id');
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->string('type')->default('string');
            $t->timestamps();
        });

        Schema::create('products', function (Blueprint $t) {
            $t->increments('id');
            $t->decimal('price', 10, 2)->default(0);
            $t->decimal('special_price', 10, 2)->nullable();
            $t->dateTime('discount_start')->nullable();
            $t->dateTime('discount_end')->nullable();
        });
        Schema::create('flash_deals', function (Blueprint $t) {
            $t->increments('id');
            $t->string('title')->default('');
            $t->dateTime('start_date')->nullable();
            $t->dateTime('end_date')->nullable();
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

    protected function tearDown(): void
    {
        Schema::dropIfExists('flash_deal_products');
        Schema::dropIfExists('flash_deals');
        Schema::dropIfExists('products');
        Schema::dropIfExists('system_settings');
        Currency::flush();

        parent::tearDown();
    }

    // ---- (a) promotion math edge cases ----

    public function test_effective_percentage_edge_cases(): void
    {
        $this->assertSame(0.0, FlashDeal::effectivePercentage('percentage', 0, 100));
        $this->assertSame(1.0, FlashDeal::effectivePercentage('percentage', 1, 100));
        $this->assertSame(10.0, FlashDeal::effectivePercentage('percentage', 10, 100));
        $this->assertSame(50.0, FlashDeal::effectivePercentage('percentage', 50, 100));
        $this->assertSame(99.0, FlashDeal::effectivePercentage('percentage', 99, 100));
        // Cap: percentage can never exceed 100.
        $this->assertSame(100.0, FlashDeal::effectivePercentage('percentage', 150, 100));
        // Fixed-amount discounts convert against base price, capped at 100.
        $this->assertSame(25.0, FlashDeal::effectivePercentage('flat', 50, 200));
        $this->assertSame(100.0, FlashDeal::effectivePercentage('flat', 500, 200));
        // Degenerate bases never divide by zero (flat path guards $base <= 0;
        // percentage discounts need no base price at all).
        $this->assertSame(0.0, FlashDeal::effectivePercentage('flat', 50, 0));
        $this->assertSame(10.0, FlashDeal::effectivePercentage('percentage', 10, 0));
        // Non-positive / unknown inputs.
        $this->assertSame(0.0, FlashDeal::effectivePercentage('percentage', -5, 100));
        // Null/unknown positive types fall back to flat semantics (locked behavior).
        $this->assertSame(10.0, FlashDeal::effectivePercentage(null, 10, 100));
        $this->assertSame(0.0, FlashDeal::effectivePercentage('bogus', -5, 100));
        // Unknown positive type falls back to flat semantics (locked behavior).
        $this->assertSame(25.0, FlashDeal::effectivePercentage('bogus', 50, 200));
    }

    public function test_effective_percentage_special_price_window(): void
    {
        // Active special price becomes the base: 50 flat on 100 special => 50%.
        $this->assertSame(
            50.0,
            FlashDeal::effectivePercentage('flat', 50, 200, 100, now()->subDay(), now()->addDay())
        );
        // Expired window falls back to regular price: 50 flat on 200 => 25%.
        $this->assertSame(
            25.0,
            FlashDeal::effectivePercentage('flat', 50, 200, 100, now()->subDays(5), now()->subDay())
        );
        // Future window likewise falls back to regular price.
        $this->assertSame(
            25.0,
            FlashDeal::effectivePercentage('flat', 50, 200, 100, now()->addDay(), now()->addDays(5))
        );
        // Zero special price never becomes the base.
        $this->assertSame(
            25.0,
            FlashDeal::effectivePercentage('flat', 50, 200, 0, null, null)
        );
    }

    // ---- (b) ordering + best-discount over own tables ----

    public function test_order_by_best_discount_ranks_flat_vs_percentage(): void
    {
        $conn = DB::connection();
        $p1 = $conn->table('products')->insertGetId(['price' => 100]);
        $p2 = $conn->table('products')->insertGetId(['price' => 200]);

        $low = $conn->table('flash_deals')->insertGetId([
            'title' => 'PromoLow', 'status' => true,
            'start_date' => now()->subDay(), 'end_date' => now()->addDay(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $high = $conn->table('flash_deals')->insertGetId([
            'title' => 'PromoHigh', 'status' => true,
            'start_date' => now()->subDay(), 'end_date' => now()->addDay(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $conn->table('flash_deal_products')->insert([
            'flash_deal_id' => $low, 'product_id' => $p1,
            'discount_type' => 'percentage', 'discount_value' => 10,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        // 100 flat on 200 price => 50% effective => must rank first.
        $conn->table('flash_deal_products')->insert([
            'flash_deal_id' => $high, 'product_id' => $p2,
            'discount_type' => 'flat', 'discount_value' => 100,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $deal = FlashDeal::query()->where('status', true)->orderByBestDiscount()->first();

        $this->assertNotNull($deal);
        $this->assertSame('PromoHigh', $deal->title);
        $this->assertStringNotContainsString(
            'discount_percentage',
            FlashDeal::query()->orderByBestDiscount()->toSql()
        );
    }

    public function test_best_discount_percentage_over_own_tables(): void
    {
        $conn = DB::connection();
        $p1 = $conn->table('products')->insertGetId(['price' => 100]);
        $p2 = $conn->table('products')->insertGetId(['price' => 200]);

        $dealId = $conn->table('flash_deals')->insertGetId([
            'title' => 'PromoMix', 'status' => true,
            'start_date' => now()->subDay(), 'end_date' => now()->addDay(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $conn->table('flash_deal_products')->insert([
            ['flash_deal_id' => $dealId, 'product_id' => $p1, 'discount_type' => 'percentage', 'discount_value' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['flash_deal_id' => $dealId, 'product_id' => $p2, 'discount_type' => 'flat', 'discount_value' => 100, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $deal = FlashDeal::query()->find($dealId);
        $this->assertNotNull($deal);
        $this->assertSame(50.0, $deal->bestDiscountPercentage());
    }

    // ---- (c) policy scoping ----

    public function test_product_policy_scoping(): void
    {
        $policy = new ProductPolicy();

        $admin = $this->makeUser('admin', 1, null);
        $owner = $this->makeUser('vendor', 2, 7);
        $stranger = $this->makeUser('vendor', 3, 8);
        $shopLess = $this->makeUser('vendor', 4, null);
        $customer = $this->makeUser('customer', 5, null);

        $own = $this->makeProduct(7);
        $other = $this->makeProduct(8);

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->viewAny($owner));
        $this->assertFalse($policy->viewAny($customer));

        $this->assertTrue($policy->view($admin, $other));
        $this->assertTrue($policy->view($owner, $own));
        $this->assertFalse($policy->view($owner, $other));
        $this->assertFalse($policy->view($stranger, $own));
        $this->assertFalse($policy->view($customer, $own));

        $this->assertTrue($policy->update($admin, $other));
        $this->assertTrue($policy->update($owner, $own));
        $this->assertFalse($policy->update($owner, $other));
        $this->assertFalse($policy->update($customer, $own));

        $this->assertTrue($policy->create($admin));
        $this->assertTrue($policy->create($owner));
        $this->assertFalse($policy->create($shopLess));
        $this->assertFalse($policy->create($customer));

        $this->assertTrue($policy->delete($admin, $other));
        $this->assertTrue($policy->delete($owner, $own));
        $this->assertFalse($policy->delete($owner, $other));

        $this->assertTrue($policy->restore($owner, $own));
        $this->assertFalse($policy->restore($owner, $other));

        $this->assertTrue($policy->forceDelete($admin, $other));
        $this->assertFalse($policy->forceDelete($owner, $own));
    }

    public function test_order_policy_scoping(): void
    {
        $policy = new OrderPolicy();

        $admin = $this->makeUser('admin', 1, null);
        $owner = $this->makeUser('vendor', 2, 7);
        $stranger = $this->makeUser('vendor', 3, 8);
        $customer = $this->makeUser('customer', 9, null);
        $otherCustomer = $this->makeUser('customer', 10, null);

        $ownShopOrder = $this->makeOrder(7, 9);
        $otherShopOrder = $this->makeOrder(8, 10);

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->viewAny($owner));
        $this->assertTrue($policy->viewAny($customer));

        $this->assertTrue($policy->view($admin, $otherShopOrder));
        $this->assertTrue($policy->view($owner, $ownShopOrder));
        $this->assertFalse($policy->view($owner, $otherShopOrder));
        $this->assertFalse($policy->view($stranger, $ownShopOrder));
        $this->assertTrue($policy->view($customer, $ownShopOrder));
        $this->assertFalse($policy->view($customer, $otherShopOrder));
        $this->assertFalse($policy->view($otherCustomer, $ownShopOrder));

        $this->assertTrue($policy->update($admin, $otherShopOrder));
        $this->assertTrue($policy->update($owner, $ownShopOrder));
        $this->assertFalse($policy->update($owner, $otherShopOrder));
        $this->assertFalse($policy->update($customer, $ownShopOrder));

        $this->assertTrue($policy->create($admin));
        $this->assertTrue($policy->create($owner));
        $this->assertTrue($policy->create($customer));

        // Financial records: only backoffice may delete/restore.
        $this->assertTrue($policy->delete($admin, $ownShopOrder));
        $this->assertFalse($policy->delete($owner, $ownShopOrder));
        $this->assertFalse($policy->delete($customer, $ownShopOrder));
        $this->assertTrue($policy->restore($admin, $ownShopOrder));
        $this->assertFalse($policy->restore($owner, $ownShopOrder));
        $this->assertTrue($policy->forceDelete($admin, $ownShopOrder));
        $this->assertFalse($policy->forceDelete($owner, $ownShopOrder));
    }

    // ---- (d) SEO component defaults ----

    public function test_seo_head_always_emits_title_meta_canonical_og(): void
    {
        $html = Blade::render('<x-seo.head :title="$t" />', ['t' => 'Promo Spesial']);

        $this->assertStringContainsString('<title>', $html);
        $this->assertStringContainsString('Promo Spesial', $html);
        $this->assertStringContainsString('name="description"', $html);
        $this->assertStringContainsString('rel="canonical"', $html);
        $this->assertStringContainsString('property="og:title"', $html);
        $this->assertStringContainsString('property="og:description"', $html);
        $this->assertStringContainsString('property="og:url"', $html);
        $this->assertStringContainsString('name="twitter:card"', $html);
        $this->assertStringContainsString('name="robots"', $html);
    }

    public function test_seo_json_ld_escapes_quotes_safely(): void
    {
        $html = Blade::render('<x-seo.json-ld :data="$d" />', ['d' => [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => 'TV 55" 4K',
            'offers' => ['@type' => 'Offer', 'priceCurrency' => 'IDR', 'price' => '1000000'],
        ]]);

        $this->assertStringContainsString('application/ld+json', $html);
        // A raw double quote must never survive into the script payload.
        $this->assertStringNotContainsString('TV 55" 4K', $html);
        $this->assertStringContainsString('TV 55\\u0022 4K', $html);
    }

    // ---- helpers (no DB I/O; relations pre-loaded so no lazy queries occur) ----

    private function makeUser(string $role, int $id, ?int $shopId): User
    {
        $user = new User();
        $user->id = $id;
        $user->role = $role;

        if ($shopId !== null) {
            $shop = new Shop();
            $shop->id = $shopId;
            $user->setRelation('shop', $shop);
        } else {
            $user->setRelation('shop', null);
        }

        return $user;
    }

    private function makeProduct(int $shopId): Product
    {
        $product = new Product();
        $product->shop_id = $shopId;

        return $product;
    }

    private function makeOrder(int $shopId, int $customerId): Order
    {
        $order = new Order();
        $order->shop_id = $shopId;
        $order->customer_id = $customerId;

        return $order;
    }
}
