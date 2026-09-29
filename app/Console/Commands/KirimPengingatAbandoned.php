<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Marketing\RetentionService;
use Illuminate\Console\Command;

/**
 * Kirim pengingat abandoned cart bertahap berikutnya.
 *
 * Sengaja TIDAK didaftarkan ke schedule — integrator yang wiring
 * jadwalnya (mis. tiap jam) setelah meninjau volume notifikasi.
 */
class KirimPengingatAbandoned extends Command
{
    protected $signature = 'marketing:pengingat-abandoned
        {--limit=100 : Maksimal keranjang yang diproses}
        {--force : Abaikan jeda minimum antar pengingat}';

    protected $description = 'Kirim pengingat bertahap untuk keranjang tertinggal yang jatuh tempo';

    public function handle(RetentionService $retensi): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $force = (bool) $this->option('force');

        $terkirim = 0;
        $dilewati = 0;

        foreach ($retensi->dueCarts($limit) as $cart) {
            $hasil = $retensi->sendStagedReminder($cart, null, $force);

            if ($hasil['queued']) {
                $terkirim++;
            } else {
                $dilewati++;
            }
        }

        $this->info("Pengingat terkirim: {$terkirim}, dilewati: {$dilewati}.");

        return self::SUCCESS;
    }
}
