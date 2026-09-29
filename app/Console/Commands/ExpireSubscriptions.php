<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Expire vendor and SaaS subscriptions whose billing period has ended';

    public function handle(): int
    {
        $expired = 0;

        if (Schema::hasTable('vendor_subscriptions')) {
            $expired += DB::table('vendor_subscriptions')
                ->where('status', 'active')
                ->whereNotNull('ends_at')
                ->where('ends_at', '<=', now())
                ->update(['status' => 'expired', 'updated_at' => now()]);
        }

        if (Schema::hasTable('saas_subscriptions')) {
            $expired += DB::table('saas_subscriptions')
                ->whereIn('status', ['active', 'trialing', 'pending'])
                ->whereNotNull('ends_at')
                ->where('ends_at', '<=', now())
                ->update(['status' => 'expired', 'updated_at' => now()]);
        }

        $this->info("Expired {$expired} subscription(s).");

        return self::SUCCESS;
    }
}
