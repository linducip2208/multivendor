<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ExpireStalePayments;
use Illuminate\Console\Command;

class ExpirePayments extends Command
{
    protected $signature = 'payments:expire {--limit=100 : Maximum payment groups to expire per run}';

    protected $description = 'Expire stale payment groups and release the stock they were holding';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        ExpireStalePayments::dispatch($limit);

        $this->info(sprintf('Queued payment expiry sweep for up to %d group(s).', $limit));

        return self::SUCCESS;
    }
}
