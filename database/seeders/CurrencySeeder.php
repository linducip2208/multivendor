<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

/**
 * Seed awal multi-currency engine.
 *
 * - IDR satu-satunya default (rate 1 vs dirinya sendiri).
 * - exchange_rate = IDR per 1 major unit (string desimal, tanpa float).
 * - Rate awal wajar (estimasi, sumber manual-seed) — refresh berkala
 *   via `currency:rates-refresh` setelah punya feed berlisensi.
 * - Murni data statis lokal; tidak ada akses jaringan keluar.
 */
class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // code, name, symbol, decimals, rate(IDR per 1 unit)
            ['IDR', 'Rupiah Indonesia', 'Rp', 0, '1.00000000'],
            ['USD', 'US Dollar', '$', 2, '16500.00000000'],
            ['EUR', 'Euro', '€', 2, '17800.00000000'],
            ['GBP', 'British Pound', '£', 2, '21000.00000000'],
            ['SGD', 'Singapore Dollar', 'S$', 2, '12200.00000000'],
            ['MYR', 'Malaysian Ringgit', 'RM', 2, '3500.00000000'],
            ['THB', 'Thai Baht', '฿', 2, '510.00000000'],
            ['JPY', 'Japanese Yen', '¥', 0, '110.00000000'],
            ['CNY', 'Chinese Yuan', 'CN¥', 2, '2270.00000000'],
            ['AUD', 'Australian Dollar', 'A$', 2, '10800.00000000'],
            ['CAD', 'Canadian Dollar', 'C$', 2, '11900.00000000'],
            ['CHF', 'Swiss Franc', 'Fr', 2, '18700.00000000'],
            ['INR', 'Indian Rupee', '₹', 2, '198.00000000'],
            ['AED', 'UAE Dirham', 'Dh', 2, '4490.00000000'],
            ['SAR', 'Saudi Riyal', 'SR', 2, '4400.00000000'],
        ];

        foreach ($rows as [$code, $name, $symbol, $decimals, $rate]) {
            Currency::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'symbol' => $symbol,
                    'decimal_places' => $decimals,
                    'decimal_separator' => '.',
                    'thousand_separator' => ',',
                    'exchange_rate' => $rate,
                    'rate_source' => 'manual-seed',
                    'rate_updated_at' => now(),
                    'is_default' => $code === 'IDR',
                    'is_active' => true,
                ],
            );
        }

        // Jamin hanya IDR yang default walau seeder dijalankan berulang.
        Currency::query()->where('code', '<>', 'IDR')->update(['is_default' => false]);
    }
}
