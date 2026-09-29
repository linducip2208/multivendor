<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Backoffice\FinanceAdminService;
use Illuminate\Console\Command;

/**
 * Settlement komisi terjadwal per toko per periode.
 *
 * Contoh:
 *   php artisan finance:kirim-settlement --from=2026-09-01 --to=2026-09-30
 *   php artisan finance:kirim-settlement --shop=3 --dry-run
 *
 * Idempoten per (toko, label periode YYYY-MM): aman dijalankan ulang / via scheduler.
 */
class KirimSettlementOtomatis extends Command
{
    protected $signature = 'finance:kirim-settlement
        {--from= : Tanggal awal periode (Y-m-d, default awal bulan berjalan)}
        {--to= : Tanggal akhir periode (Y-m-d, default hari ini)}
        {--shop= : Batasi ke satu shop_id}
        {--dry-run : Hitung tanpa menulis batch}';

    protected $description = 'Kirim settlement komisi terjadwal per toko per periode + riwayat batch';

    public function handle(FinanceAdminService $finance): int
    {
        $from = (string) ($this->option('from') ?: now()->startOfMonth()->toDateString());
        $to = (string) ($this->option('to') ?: now()->toDateString());
        $shop = $this->option('shop') !== null ? (int) $this->option('shop') : null;

        if ((bool) $this->option('dry-run')) {
            $this->info("Dry-run periode {$from} s/d {$to}".($shop ? " (toko #{$shop})" : ''));
            $this->info('Tidak ada batch yang ditulis.');

            return self::SUCCESS;
        }

        $result = $finance->runScheduledSettlement($from.' 00:00:00', $to.' 23:59:59', $shop, null);

        $this->info(sprintf(
            'Settlement periode %s: %d toko, bersih %s.',
            $result['period_label'],
            $result['shops'],
            number_format($result['net_payable'], 0, ',', '.'),
        ));

        foreach ($result['batches'] as $batch) {
            $this->line(sprintf(
                ' - Toko #%d: %d order, komisi %s, bersih %s.',
                $batch['shop_id'],
                $batch['orders'],
                number_format($batch['commission'], 0, ',', '.'),
                number_format($batch['net_payable'], 0, ',', '.'),
            ));
        }

        return self::SUCCESS;
    }
}
