<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\TaxRule;
use App\Services\Geo\CountryService;
use App\Services\Geo\TaxEngine;
use Database\Seeders\GeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CountryTaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_creates_country_tax_tables(): void
    {
        foreach (['countries', 'regions', 'cities', 'tax_rules'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "tabel {$table} hilang");
        }

        $this->assertTrue(Schema::hasColumn('countries', 'iso2'));
        $this->assertTrue(Schema::hasColumn('tax_rules', 'is_inclusive'));
        $this->assertTrue(Schema::hasColumn('tax_rules', 'is_compound'));
    }

    public function test_country_service_resolves_currency_language_and_hints(): void
    {
        $this->seed(GeoSeeder::class);
        $service = app(CountryService::class);

        $context = $service->contextFor('id'); // case-insensitive

        $this->assertSame('ID', $context['iso2']);
        $this->assertSame('IDR', $context['currency_code']);
        $this->assertSame('id', $context['locale']);
        $this->assertSame('id', $context['language']);
        $this->assertSame('Asia/Jakarta', $context['timezone']);
        $this->assertNotEmpty($context['payment_hints']);
        $this->assertNotEmpty($context['shipping_hints']);
        $this->assertFalse($context['is_fallback']);

        $this->assertSame('USD', $service->currencyFor('us'));
        $this->assertSame('de', $service->languageFor('DE'));
    }

    public function test_country_service_fallback_for_unknown_country(): void
    {
        $this->seed(GeoSeeder::class);
        $service = app(CountryService::class);

        $context = $service->contextFor('XX');

        $this->assertTrue($context['is_fallback']);
        $this->assertNotEmpty($context['currency_code']);
        $this->assertNotEmpty($context['locale']);

        // TaxEngine tidak boleh crash untuk negara tak dikenal → mode none.
        $quote = app(TaxEngine::class)->calculateByIso(100000.0, 'XX');

        $this->assertSame('none', $quote['mode']);
        $this->assertSame(0.0, $quote['tax']);
        $this->assertSame(100000.0, $quote['gross']);
        $this->assertSame([], $quote['breakdown']);
    }

    public function test_tax_engine_inclusive_id_11_percent(): void
    {
        $this->seed(GeoSeeder::class);

        // Gross 111.000 sudah termasuk PPN 11% → net 100.000, pajak 11.000.
        $quote = app(TaxEngine::class)->calculateByIso(111000.0, 'ID');

        $this->assertSame('inclusive', $quote['mode']);
        $this->assertEqualsWithDelta(100000.0, $quote['net'], 0.01);
        $this->assertEqualsWithDelta(11000.0, $quote['tax'], 0.01);
        $this->assertEqualsWithDelta(111000.0, $quote['gross'], 0.01);
        $this->assertCount(1, $quote['breakdown']);
    }

    public function test_tax_engine_exclusive_eu_sample(): void
    {
        $this->seed(GeoSeeder::class);

        // DE standar 19% exclusive: net 100 → pajak 19, gross 119.
        $quote = app(TaxEngine::class)->calculateByIso(100.0, 'DE');

        $this->assertSame('exclusive', $quote['mode']);
        $this->assertEqualsWithDelta(100.0, $quote['net'], 0.001);
        $this->assertEqualsWithDelta(19.0, $quote['tax'], 0.001);
        $this->assertEqualsWithDelta(119.0, $quote['gross'], 0.001);
    }

    public function test_tax_engine_us_zero_base(): void
    {
        $this->seed(GeoSeeder::class);

        $quote = app(TaxEngine::class)->calculateByIso(250.0, 'US');

        $this->assertEqualsWithDelta(0.0, $quote['tax'], 0.001);
        $this->assertEqualsWithDelta(250.0, $quote['net'], 0.001);
        $this->assertEqualsWithDelta(250.0, $quote['gross'], 0.001);
    }

    public function test_tax_engine_compound_exclusive_stacks(): void
    {
        $this->seed(GeoSeeder::class);

        $country = Country::where('iso2', 'SG')->firstOrFail();
        TaxRule::create([
            'country_id' => $country->id, 'region_id' => null,
            'tax_class' => 'standard', 'rate' => 10.0,
            'is_inclusive' => false, 'is_compound' => true,
            'priority' => 1, 'is_active' => true,
        ]);

        // SG standar 9% (priority 0) + 10% compound (priority 1):
        // net 100 → t1 9.00, t2 (100+9)*10% = 10.90 → total 19.90, gross 119.90.
        $quote = app(TaxEngine::class)->calculateByIso(100.0, 'SG');

        $this->assertSame('exclusive', $quote['mode']);
        $this->assertEqualsWithDelta(19.90, $quote['tax'], 0.01);
        $this->assertEqualsWithDelta(119.90, $quote['gross'], 0.01);
        $this->assertCount(2, $quote['breakdown']);
    }

    public function test_geo_seeder_covers_required_countries(): void
    {
        $this->seed(GeoSeeder::class);

        foreach (['ID', 'US', 'DE', 'FR', 'SG', 'MY', 'JP'] as $iso2) {
            $this->assertNotNull(
                Country::where('iso2', $iso2)->first(),
                "negara {$iso2} belum di-seed",
            );
        }

        $this->assertGreaterThanOrEqual(7, Country::count());
        $this->assertGreaterThanOrEqual(7, TaxRule::count());
    }
}
