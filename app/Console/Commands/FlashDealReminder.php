<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FlashDeal;
use App\Services\Marketing\RetentionService;
use Illuminate\Console\Command;

/**
 * Beri tahu pelanggan "ingatkan saya" saat flash deal dimulai.
 *
 * Tanpa --deal: pindai seluruh deal yang sudah mulai dan masih punya
 * langganan tertunda. Dengan --deal=: hanya deal tersebut.
 *
 * Sengaja TIDAK didaftarkan ke schedule — integrator yang wiring
 * jadwalnya (mis. tiap 5 menit) setelah meninjau kebutuhan bisnis.
 */
class FlashDealReminder extends Command
{
    protected $signature = 'marketing:pengingat-flashdeal
        {--deal= : ID flash deal tertentu}';

    protected $description = 'Kirim notifikasi mulai-deal ke pelanggan "ingatkan saya"';

    public function handle(RetentionService $retensi): int
    {
        $dealId = trim((string) $this->option('deal'));

        $deals = $dealId !== ''
            ? FlashDeal::query()->whereKey((int) $dealId)->get()
            : FlashDeal::query()
                ->where('status', true)
                ->where('start_date', '<=', now())
                ->get();

        $total = 0;
        foreach ($deals as $deal) {
            if (count($retensi->subscribersForDeal((int) $deal->getKey())) === 0) {
                continue;
            }

            $hasil = $retensi->notifyFlashDealStarted($deal);
            $total += $hasil['sent'];
            $this->info("Deal #{$deal->getKey()}: {$hasil['reason']}");
        }

        $this->info("Total pelanggan diberi tahu: {$total}.");

        return self::SUCCESS;
    }
}
