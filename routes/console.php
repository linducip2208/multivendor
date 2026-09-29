<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('seo:indexnow --type=all --limit=50')->dailyAt('02:45');
Schedule::command('seo:indexnow --type=products --limit=100')->everySixHours();
Schedule::command('db:backup')->dailyAt('03:00');

Schedule::command('payments:reconcile --limit=100')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('payments:expire --limit=100')->everyTenMinutes()->withoutOverlapping();
Schedule::command('subscriptions:expire')->dailyAt('00:10')->withoutOverlapping();
Schedule::command('promotions:expire')->dailyAt('00:20')->withoutOverlapping();
Schedule::command('notifications:digest')->dailyAt('07:00')->withoutOverlapping();
Schedule::command('seo:sitemap-warm')->dailyAt('04:00');

Artisan::command('webhooks:replay {delivery : Delivery ID or event id} {--endpoint= : Restrict to a webhook endpoint id} {--reset : Clear the attempt counter first}', function () {
    /** @var \App\Models\WebhookDelivery $delivery */
    $delivery = \App\Models\WebhookDelivery::query()
        ->where('id', (int) $this->argument('delivery'))
        ->orWhere('event_id', (string) $this->argument('delivery'))
        ->when($this->option('endpoint'), fn ($query) => $query->where('webhook_endpoint_id', (int) $this->option('endpoint')))
        ->latest('id')
        ->first();

    if (! $delivery) {
        $this->error('Delivery tidak ditemukan.');

        return self::FAILURE;
    }

    if (! $delivery->endpoint || ! $delivery->endpoint->is_active) {
        $this->error('Endpoint delivery ini tidak ada atau sedang nonaktif.');

        return self::FAILURE;
    }

    if ($this->option('reset')) {
        $delivery->forceFill(['attempt' => 1])->save();
    }

    $delivery->forceFill(['status' => 'pending', 'next_retry_at' => now()])->save();

    \App\Jobs\DispatchWebhookDelivery::dispatch((int) $delivery->getKey())
        ->onQueue(\App\Services\Webhooks\WebhookService::QUEUE)
        ->afterCommit();

    $this->info(sprintf(
        'Delivery #%d (%s) dijadwalkan ulang ke endpoint %s.',
        $delivery->getKey(),
        $delivery->event,
        $delivery->webhook_endpoint_id,
    ));

    return self::SUCCESS;
})->purpose('Kirim ulang satu webhook delivery');

Artisan::command('webhooks:retry-failed {--limit=200 : Maximum deliveries to requeue} {--endpoint= : Restrict to a webhook endpoint id} {--hours=168 : Only consider deliveries from the last N hours}', function () {
    $limit = max(1, (int) $this->option('limit'));
    $since = now()->subHours(max(1, (int) $this->option('hours')));

    $deliveries = \App\Models\WebhookDelivery::query()
        ->whereIn('status', ['failed', 'exhausted'])
        ->where('created_at', '>=', $since)
        ->when($this->option('endpoint'), fn ($query) => $query->where('webhook_endpoint_id', (int) $this->option('endpoint')))
        ->orderBy('id')
        ->limit($limit)
        ->get();

    $requeued = 0;
    $skipped = 0;

    foreach ($deliveries as $delivery) {
        if (! $delivery->endpoint || ! $delivery->endpoint->is_active) {
            $skipped++;

            continue;
        }

        $delivery->forceFill([
            'attempt' => 1,
            'status' => 'pending',
            'next_retry_at' => now(),
            'delivered_at' => null,
        ])->save();

        \App\Jobs\DispatchWebhookDelivery::dispatch((int) $delivery->getKey())
            ->onQueue(\App\Services\Webhooks\WebhookService::QUEUE)
            ->afterCommit();

        $requeued++;
    }

    $this->info(sprintf('Delivery dijadwalkan ulang: %d, dilewati karena endpoint nonaktif: %d.', $requeued, $skipped));

    return self::SUCCESS;
})->purpose('Jadwalkan ulang seluruh webhook delivery yang gagal');

Artisan::command('webhooks:prune {--days=30 : Keep deliveries newer than this many days}', function () {
    $days = max(1, (int) $this->option('days'));
    $cutoff = now()->subDays($days);

    $delivered = \App\Models\WebhookDelivery::query()
        ->where('status', 'delivered')
        ->where('delivered_at', '<', $cutoff)
        ->delete();

    $this->info(sprintf('Delivery sukses yang dihapus: %d (lebih lama dari %d hari).', $delivered, $days));

    return self::SUCCESS;
})->purpose('Hapus riwayat webhook delivery sukses yang lama');

Artisan::command('webhooks:stats {--hours=24 : Reporting window} {--endpoint= : Restrict to a webhook endpoint id}', function () {
    $stats = app(\App\Services\Webhooks\WebhookStats::class);
    $hours = max(1, (int) $this->option('hours'));
    $endpointId = $this->option('endpoint') !== null ? (int) $this->option('endpoint') : null;

    $this->line(json_encode($stats->summary($hours), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $this->newLine();
    $this->table(
        ['endpoint', 'active', 'delivered', 'failed', 'failure rate', 'p95 ms'],
        $endpointId !== null
            ? array_values(array_filter($stats->perEndpoint($hours), fn (array $row): bool => $row['endpoint_id'] === $endpointId))
            : $stats->perEndpoint($hours)
    );

    return self::SUCCESS;
})->purpose('Ringkasan pengiriman webhook untuk dashboard admin');
