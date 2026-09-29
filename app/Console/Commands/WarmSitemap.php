<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class WarmSitemap extends Command
{
    protected $signature = 'seo:sitemap-warm {--path=sitemap.xml} {--audit-alts : Audit alt image produk tanpa menulis sitemap}';

    protected $description = 'Warm the generated sitemap so crawlers always receive a fast, cached response';

    public function handle(): int
    {
        if ($this->option('audit-alts')) {
            $issues = app(\App\Services\Seo\PseoService::class)->auditImageAlts(50);
            $this->info('Produk tanpa alt deskriptif: '.count($issues));
            foreach (array_slice($issues, 0, 20) as $issue) {
                $this->line('#'.$issue['id'].' '.$issue['name'].' — '.$issue['saran']);
            }

            return self::SUCCESS;
        }
        $key = 'seo:sitemap:'.sha1((string) config('app.url'));
        $ttl = max(60, (int) config('seo.sitemap_cache_ttl', 3600));
        $url = rtrim((string) config('app.url'), '/').'/'.ltrim((string) $this->option('path'), '/');

        $cached = Cache::get($key);

        if (is_string($cached) && $cached !== '') {
            $this->info('Sitemap is already warm.');

            return self::SUCCESS;
        }

        try {
            $response = Http::accept('application/xml', 'text/xml', '*/*')
                ->timeout(15)
                ->connectTimeout(5)
                ->get($url);

            $body = $response->successful() ? (string) $response->body() : '';
        } catch (Throwable $e) {
            $body = '';
        }

        if ($body === '' || ! Str::contains($body, '<urlset')) {
            $this->warn('Sitemap could not be generated; nothing cached.');

            return self::FAILURE;
        }

        Cache::put($key, $body, $ttl);
        $this->info('Sitemap warmed for '.$ttl.' seconds.');

        return self::SUCCESS;
    }
}
