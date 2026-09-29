<?php

namespace Tests\Feature;

use App\Models\DealOfTheDay;
use App\Models\FlashDeal;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression: ordering flash deals by "best discount" must never emit
 * ORDER BY `discount_percentage` (no such column on flash_deals /
 * deals_of_the_day — discounts live on flash_deal_products pivot as
 * discount_type {flat,percentage} + discount_value).
 *
 * Self-contained schema (own tables) so it runs even when unrelated
 * project migrations are incompatible with the test driver.
 */
class FlashDealOrderingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('flash_deal_products');
        Schema::dropIfExists('flash_deals');
        Schema::dropIfExists('products');

        Schema::create('products', function (Blueprint $t) {
            $t->increments('id');
            $t->decimal('price', 10, 2)->default(0);
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

        parent::tearDown();
    }

    public function test_active_deal_query_never_orders_by_missing_column(): void
    {
        $conn = DB::connection();

        $p1 = $conn->table('products')->insertGetId(['price' => 100]);
        $p2 = $conn->table('products')->insertGetId(['price' => 200]);

        $low = $conn->table('flash_deals')->insertGetId([
            'title' => 'Low', 'status' => true,
            'start_date' => now()->subDay(), 'end_date' => now()->addDay(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $high = $conn->table('flash_deals')->insertGetId([
            'title' => 'High', 'status' => true,
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

        $deal = FlashDeal::query()
            ->where('status', true)
            ->orderByBestDiscount()
            ->first();

        $this->assertNotNull($deal);
        $this->assertSame('High', $deal->title);
        $this->assertStringNotContainsString(
            'discount_percentage',
            FlashDeal::query()->orderByBestDiscount()->toSql(),
            'Ordering must use the computed pivot expression, never a missing column.'
        );
    }

    public function test_effective_percentage_cases(): void
    {
        $this->assertSame(0.0, FlashDeal::effectivePercentage('percentage', 0, 100));
        $this->assertSame(1.0, FlashDeal::effectivePercentage('percentage', 1, 100));
        $this->assertSame(10.0, FlashDeal::effectivePercentage('percentage', 10, 100));
        $this->assertSame(50.0, FlashDeal::effectivePercentage('percentage', 50, 100));
        $this->assertSame(99.0, FlashDeal::effectivePercentage('percentage', 99, 100));
        $this->assertSame(100.0, FlashDeal::effectivePercentage('percentage', 150, 100));
        $this->assertSame(25.0, FlashDeal::effectivePercentage('flat', 50, 200));
        $this->assertSame(0.0, FlashDeal::effectivePercentage('flat', 50, 0));
        $this->assertSame(0.0, FlashDeal::effectivePercentage('bogus', -5, 100));
    }

    public function test_no_source_orders_flash_tables_by_missing_column(): void
    {
        $files = [
            'app/Services/Catalog/HomePageService.php',
            'app/Http/Controllers/Storefront/CatalogController.php',
            'app/Services/Analytics/MarketingAnalyticsService.php',
            'app/Models/FlashDeal.php',
            'app/Models/DealOfTheDay.php',
        ];

        foreach ($files as $file) {
            $path = \dirname(__DIR__, 2).'/'.$file;
            $this->assertFileExists($path);
            $this->assertDoesNotMatchRegularExpression(
                '/orderBy\w*\(\s*[\'"]discount_percentage[\'"]/',
                (string) file_get_contents($path),
                "Missing-column ORDER BY still present in {$file}"
            );
        }
    }
}
