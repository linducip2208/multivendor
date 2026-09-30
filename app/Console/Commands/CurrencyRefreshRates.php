<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Currency\RateProvider;
use Illuminate\Console\Command;

/**
 * Skeleton refresh kurs manual/terjadwal.
 *
 * Contoh:
 *   php artisan currency:rates-refresh --rate=USD=16500 --rate=EUR=17800
 *   php artisan currency:rates-refresh --rate=USD=16500 --source=manual --dry-run
 *
 * SENGAJA tidak didaftarkan ke scheduler (bootstrap/app.php tidak disentuh).
 * Bila nanti perlu jadwal, daftarkan manual di penjadwalan aplikasi:
 *   Schedule::command('currency:rates-refresh ...')->daily();
 * dan ganti sumber array ini dengan feed berlisensi + kredensial vault.
 */
class CurrencyRefreshRates extends Command
{
    protected $signature = 'currency:rates-refresh'
        .' {--rate=* : Pasangan CODE=RATE, mis. --rate=USD=16500}'
        .' {--source=manual : Label sumber kurs (maks 30 char)}'
        .' {--dry-run : Parse + validasi saja, tanpa tulis DB}';

    protected $description = 'Refresh kurs multi-currency dari pasangan CODE=RATE manual (skeleton terjadwal)';

    public function handle(RateProvider $rates): int
    {
        /** @var string[] $pairs */
        $pairs = (array) $this->option('rate');
        $parsed = RateProvider::parseCliPairs($pairs);

        if ($parsed === []) {
            $this->warn('Tidak ada pasangan CODE=RATE valid. Contoh: --rate=USD=16500');

            return self::FAILURE;
        }

        $source = substr(trim((string) $this->option('source')) === '' ? 'manual' : trim((string) $this->option('source')), 0, 30);

        if ((bool) $this->option('dry-run')) {
            $this->info(sprintf('Dry-run [%s]: %d kurs valid, DB tidak diubah.', $source, count($parsed)));
            foreach ($parsed as $code => $rate) {
                $this->line(sprintf('  %s = %s', $code, $rate));
            }

            return self::SUCCESS;
        }

        $updated = $rates->refreshFromArray($parsed, $source);
        $this->info(sprintf('Kurs diperbarui: %d/%d (source=%s).', $updated, count($parsed), $source));

        return self::SUCCESS;
    }
}
