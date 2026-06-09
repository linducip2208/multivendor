<?php

namespace App\Services\Seo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IndexNowService
{
    protected array $searchEngines = [
        'bing' => 'https://www.bing.com/indexnow',
        'yandex' => 'https://yandex.com/indexnow',
        'seznam' => 'https://search.seznam.cz/indexnow',
        'naver' => 'https://searchadvisor.naver.com/indexnow',
    ];

    protected string $keyFile;

    public function __construct()
    {
        $this->keyFile = public_path('indexnow-key.txt');
    }

    public function getKey(): string
    {
        if (!file_exists($this->keyFile)) {
            $key = bin2hex(random_bytes(32));
            file_put_contents($this->keyFile, $key);
        }
        return trim(file_get_contents($this->keyFile));
    }

    public function submit(array $urls): array
    {
        $key = $this->getKey();
        $keyLocation = url('indexnow-key.txt');
        $submitted = Cache::get('indexnow_submitted', []);

        $newUrls = array_filter($urls, fn ($url) => !in_array($url, $submitted));

        if (empty($newUrls)) return ['success' => true, 'skipped' => true];

        $results = [];
        foreach ($this->searchEngines as $engine => $endpoint) {
            try {
                $response = Http::timeout(10)->post($endpoint, [
                    'host' => parse_url(config('app.url'), PHP_URL_HOST),
                    'key' => $key,
                    'keyLocation' => $keyLocation,
                    'urlList' => array_values($newUrls),
                ]);

                $results[$engine] = $response->successful();
            } catch (\Exception $e) {
                Log::warning("IndexNow {$engine} failed: " . $e->getMessage());
                $results[$engine] = false;
            }
        }

        $submitted = array_merge($submitted, $newUrls);
        $submitted = array_slice($submitted, -10000);
        Cache::put('indexnow_submitted', $submitted, now()->addDays(30));

        return ['success' => true, 'results' => $results, 'count' => count($newUrls)];
    }

    public function submitUrl(string $url): array
    {
        return $this->submit([$url]);
    }
}
