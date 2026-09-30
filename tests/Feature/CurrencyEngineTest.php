<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Currency;
use App\Services\Currency\CurrencyConverter;
use App\Services\Currency\CurrencyService;
use App\Services\Currency\Money;
use App\Services\Currency\MoneyFormatter;
use App\Services\Currency\RateProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Multi-currency engine: Money minor-units + konversi presisi + format locale.
 *
 * Self-contained: membangun tabel currencies sendiri di sqlite :memory:
 * dan membersihkannya kembali (pola CmsConversionTest). Tidak memakai
 * RefreshDatabase agar tahan terhadap migrasi proyek lain.
 *
 * Aturan finansial: TIDAK ada float di Money / CurrencyConverter —
 * semua nominal adalah int minor-units + string desimal kurs.
 */
class CurrencyEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('currencies');
        Schema::create('currencies', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name', 80);
            $table->string('symbol', 12);
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->string('decimal_separator', 2)->default('.');
            $table->string('thousand_separator', 2)->default(',');
            $table->decimal('exchange_rate', 24, 8)->default('1.00000000');
            $table->string('rate_source', 30)->default('manual');
            $table->timestamp('rate_updated_at')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        foreach ([
            ['IDR', 'Rupiah Indonesia', 'Rp', 0, '1.00000000', true],
            ['USD', 'US Dollar', '$', 2, '16500.00000000', false],
            ['JPY', 'Japanese Yen', '¥', 0, '110.00000000', false],
            ['EUR', 'Euro', '€', 2, '17800.00000000', false],
        ] as [$code, $name, $symbol, $dec, $rate, $def]) {
            Currency::create([
                'code' => $code, 'name' => $name, 'symbol' => $symbol,
                'decimal_places' => $dec, 'decimal_separator' => '.',
                'thousand_separator' => ',',
                'exchange_rate' => $rate, 'rate_source' => 'manual-seed',
                'rate_updated_at' => $now, 'is_default' => $def, 'is_active' => true,
            ]);
        }

        CurrencyService::clearCache();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        CurrencyService::clearCache();
        Cache::flush();
        Schema::dropIfExists('currencies');

        parent::tearDown();
    }

    // ---------- 1. Money minor-units ----------

    public function test_money_minor_units_tanpa_float(): void
    {
        $a = Money::fromMinor(1000, 'USD', 2); // $10.00
        $b = Money::fromMinor(250, 'USD', 2);  // $2.50

        $this->assertSame(1000, $a->minor());
        $this->assertSame('10.00', $a->toMajorString());
        $this->assertSame(1250, $a->add($b)->minor());
        $this->assertSame(750, $a->sub($b)->minor());
        $this->assertSame(2000, $a->multiply(2)->minor());
        $this->assertTrue($a->add($b)->equals(Money::fromMinor(1250, 'USD', 2)));

        // fromMajor hanya terima string (float ditolak oleh type system).
        $this->assertSame('9.09', Money::fromMajor('9.09', 'USD', 2)->toMajorString());
        $this->assertSame(909, Money::fromMajor('9.09', 'USD', 2)->minor());
        $this->assertSame(150000, Money::fromMajor('150000', 'IDR', 0)->minor());

        // Beda mata uang tidak boleh di-add.
        $this->expectException(\DomainException::class);
        Money::fromMinor(100, 'USD', 2)->add(Money::fromMinor(100, 'IDR', 0));
    }

    // ---------- 2. Konversi presisi ----------

    public function test_konversi_idr_ke_usd_presisi_dan_balik(): void
    {
        // Rp150.000 -> 150000/16500 = 9.0909... USD -> 909 minor ($9.09).
        $idr = Money::fromMinor(150000, 'IDR', 0);
        $usd = CurrencyConverter::convert($idr, 'USD', 2, '1.00000000', '16500.00000000');

        $this->assertSame('USD', $usd->code());
        $this->assertSame(2, $usd->decimals());
        $this->assertSame(909, $usd->minor());
        $this->assertSame('9.09', $usd->toMajorString());

        // $10.00 genap -> Rp165.000 tepat (tanpa susut).
        $back = CurrencyConverter::convert(
            Money::fromMinor(1000, 'USD', 2), 'IDR', 0, '16500.00000000', '1.00000000'
        );
        $this->assertSame(165000, $back->minor());
    }

    public function test_rounding_half_up(): void
    {
        // 1 * 3/2 = 1.5 -> 2 (half-up, sintetis tanpa kurs riil).
        $this->assertSame(2, CurrencyConverter::convertMinor(1, 0, 0, '3.00000000', '2.00000000'));
        $this->assertSame(3, CurrencyConverter::roundHalfUpToInt('2.5'));
        $this->assertSame(2, CurrencyConverter::roundHalfUpToInt('2.4'));
        $this->assertSame(6, CurrencyConverter::convertMinor(1000, 0, 2, '1.00000000', '16500.00000000'));
    }

    public function test_jpy_nol_desimal(): void
    {
        // $100.00 -> 100*16500/110 = 15000 JPY (desimal 0).
        $jpy = CurrencyConverter::convert(
            Money::fromMinor(10000, 'USD', 2), 'JPY', 0, '16500.00000000', '110.00000000'
        );

        $this->assertSame(0, $jpy->decimals());
        $this->assertSame(15000, $jpy->minor());
        $this->assertSame('15000', $jpy->toMajorString());
        $this->assertSame(15000, Money::fromMajor('15000', 'JPY', 0)->minor());
    }

    // ---------- 3. Format locale ----------

    public function test_format_id_dan_en(): void
    {
        $idr = Money::fromMinor(150000, 'IDR', 0);
        $usd = Money::fromMinor(909, 'USD', 2);

        $this->assertSame('Rp150.000', MoneyFormatter::format($idr, 'id-ID', 'Rp'));
        $this->assertSame('Rp150,000', MoneyFormatter::format($idr, 'en-US', 'Rp'));
        $this->assertSame('$9,09', MoneyFormatter::format($usd, 'id-ID', '$'));
        $this->assertSame('$9.09', MoneyFormatter::format($usd, 'en-US', '$'));
    }

    // ---------- 4. Checkout-safe integer math ----------

    public function test_checkout_integer_math_aman(): void
    {
        // Keranjang: 2x $10.00 + fee $2.50 = $22.50 (2250 minor).
        $line = Money::fromMinor(1000, 'USD', 2)->multiply(2);
        $total = $line->add(Money::fromMinor(250, 'USD', 2));
        $this->assertSame(2250, $total->minor());

        // Konversi total ke IDR untuk payment: 22.50*16500 = 371250 (int tepat).
        $inIdr = CurrencyConverter::convert($total, 'IDR', 0, '16500.00000000', '1.00000000');
        $this->assertSame(371250, $inIdr->minor());
        $this->assertIsInt($inIdr->minor());
    }

    // ---------- 5. Service + RateProvider ----------

    public function test_service_active_default_convert_format(): void
    {
        $this->assertSame('IDR', CurrencyService::default()?->code);
        $this->assertSame(4, CurrencyService::active()->count());
        $this->assertSame(0, CurrencyService::decimalsFor('JPY'));
        $this->assertSame('$', CurrencyService::symbolFor('USD'));

        $money = CurrencyService::moneyFromMinor(150000, 'IDR');
        $usd = CurrencyService::convert($money, 'USD');
        $this->assertSame(909, $usd->minor());

        $this->assertSame('Rp150.000', CurrencyService::format($money, 'id-ID'));
        $this->assertSame('$9.09', CurrencyService::format($usd, 'en-US'));
    }

    public function test_rate_provider_manual_tanpa_kredensial(): void
    {
        $provider = new RateProvider;

        $this->assertSame('16500.00000000', $provider->getRate('USD'));
        $this->assertSame(['USD' => '16500'], RateProvider::parseCliPairs(['USD=16500', 'rusak', 'EUR=abc']));

        $updated = $provider->refreshFromArray(['USD' => '17000', 'XXX' => '1', 'EUR' => 'invalid'], 'manual-test');
        $this->assertSame(1, $updated);
        $this->assertSame('17000.00000000', $provider->getRate('USD'));

        $provider->setRate('USD', '16500.00000000', 'manual');
        $this->assertSame('16500.00000000', $provider->getRate('USD'));

        $this->expectException(\InvalidArgumentException::class);
        $provider->setRate('USD', 'bukan-angka');
    }

    // ---------- 6. Kontrak file: migrasi + seeder + inti tanpa float ----------

    public function test_migrasi_dan_seeder_sesuai_kontrak(): void
    {
        $migration = database_path('migrations/2026_09_30_110000_currency_engine.php');
        $seeder = database_path('seeders/CurrencySeeder.php');
        $this->assertFileExists($migration);
        $this->assertFileExists($seeder);

        $source = (string) file_get_contents($migration);
        foreach (['currencies', 'exchange_rate', 'rate_source', 'is_default', 'is_active',
            'decimal_places', 'unique', 'index', 'function down', 'dropIfExists'] as $needle) {
            $this->assertStringContainsString($needle, $source);
        }

        $seed = (string) file_get_contents($seeder);
        foreach (['IDR', 'USD', 'EUR', 'GBP', 'SGD', 'MYR', 'THB', 'JPY', 'CNY',
            'AUD', 'CAD', 'CHF', 'INR', 'AED', 'SAR', 'is_default'] as $needle) {
            $this->assertStringContainsString($needle, $seed);
        }
        // Seeder rate awal: tanpa kredensial / HTTP call.
        $this->assertDoesNotMatchRegularExpression(
            '/password|secret|api[_-]?key|token|http/i', $seed
        );

        // Command skeleton ada tapi TIDAK terdaftar di scheduler.
        $this->assertFileExists(app_path('Console/Commands/CurrencyRefreshRates.php'));
        $bootstrap = (string) file_get_contents(base_path('bootstrap/app.php'));
        $this->assertStringNotContainsString('currency:rates-refresh', $bootstrap);
    }

    public function test_inti_finansial_tanpa_float(): void
    {
        foreach (['Money.php', 'CurrencyConverter.php'] as $file) {
            $source = (string) file_get_contents(app_path('Services/Currency/'.$file));
            $this->assertStringNotContainsString('(float)', $source, $file.' dilarang cast float');
            $this->assertStringNotContainsString('(double)', $source, $file.' dilarang cast double');
            $this->assertStringNotContainsString('floatval', $source, $file.' dilarang floatval');
            $this->assertStringNotContainsString('doubleval', $source, $file.' dilarang doubleval');
            $this->assertStringNotContainsString('->toFloat', $source, $file.' dilarang toFloat');
        }
    }
}
