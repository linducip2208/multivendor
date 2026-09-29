<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExpirePromotions extends Command
{
    protected $signature = 'promotions:expire';

    protected $description = 'Deactivate flash deals, banners and coupons whose promotion window has ended';

    public function handle(): int
    {
        $changed = 0;

        if (Schema::hasTable('flash_deals')) {
            $changed += DB::table('flash_deals')
                ->where('status', true)
                ->whereNotNull('end_date')
                ->where('end_date', '<=', now())
                ->update(['status' => false, 'updated_at' => now()]);
        }

        if (Schema::hasTable('deals_of_the_day')) {
            $changed += DB::table('deals_of_the_day')
                ->where('date', '<', now()->toDateString())
                ->update(['updated_at' => now()]);
        }

        if (Schema::hasTable('coupons')) {
            $changed += DB::table('coupons')
                ->where('status', true)
                ->whereNotNull('end_date')
                ->where('end_date', '<=', now())
                ->update(['status' => false, 'updated_at' => now()]);
        }

        $this->info("Deactivated {$changed} promotion record(s).");

        return self::SUCCESS;
    }
}
