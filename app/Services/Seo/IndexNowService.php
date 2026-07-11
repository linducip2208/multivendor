<?php

namespace App\Services\Seo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IndexNowService
{
    protected array $endpoints = [
        'https://www.bing.com/indexnow',
        'https://yandex.com/indexnow',
        'https://search.seznam.cz/indexnow',
        'https://searchadvisor.naver.com/indexnow',
    ];

    protected string $cacheKey = 'indexnow:submitted_urls';

    public function submit(string $url): bool
    {
        if ($this->alreadySubmitted($url)) {
            return true;
        }

        $key = $this->getKey();
        if (!$key) {
            return false;
        }

        $payload = [
            'host' => parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost',
            'key' => $key,
            'keyLocation' => url('/indexnow-key.txt'),
            'urlList' => [$url],
        ];

        $success = false;

        foreach ($this->endpoints as $endpoint) {
            try {
                $response = Http::timeout(10)
                    ->acceptJson()
                    ->asJson()
                    ->post($endpoint, $payload);

                if ($response->successful()) {
                    $success = true;
                }
            } catch (\Throwable $e) {
                Log::warning("IndexNow submit to {$endpoint} failed: " . $e->getMessage());
            }
        }

        if ($success) {
            $this->markSubmitted($url);
        }

        return $success;
    }

    public function submitBatch(array $urls): int
    {
        $key = $this->getKey();
        if (!$key) {
            return 0;
        }

        $newUrls = array_filter($urls, fn ($url) => !$this->alreadySubmitted($url));
        if (empty($newUrls)) {
            return 0;
        }

        $payload = [
            'host' => parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost',
            'key' => $key,
            'keyLocation' => url('/indexnow-key.txt'),
            'urlList' => array_values($newUrls),
        ];

        $submitted = 0;

        foreach ($this->endpoints as $endpoint) {
            try {
                $response = Http::timeout(15)
                    ->acceptJson()
                    ->asJson()
                    ->post($endpoint, $payload);

                if ($response->successful()) {
                    $submitted = count($newUrls);
                }
            } catch (\Throwable $e) {
                Log::warning("IndexNow batch to {$endpoint} failed: " . $e->getMessage());
            }
        }

        if ($submitted > 0) {
            foreach ($newUrls as $url) {
                $this->markSubmitted($url);
            }
        }

        return $submitted;
    }

    public function getKey(): ?string
    {
        $path = public_path('indexnow-key.txt');

        if (!file_exists($path)) {
            $key = bin2hex(random_bytes(16));
            file_put_contents($path, $key);
        }

        return trim(file_get_contents($path));
    }

    public function keyExists(): bool
    {
        return file_exists(public_path('indexnow-key.txt'));
    }

    public function generateKey(): string
    {
        $key = bin2hex(random_bytes(16));
        file_put_contents(public_path('indexnow-key.txt'), $key);
        return $key;
    }

    protected function alreadySubmitted(string $url): bool
    {
        $normalized = rtrim($url, '/');
        $submitted = Cache::get($this->cacheKey, []);
        return in_array($normalized, $submitted);
    }

    protected function markSubmitted(string $url): void
    {
        $normalized = rtrim($url, '/');
        $submitted = Cache::get($this->cacheKey, []);
        $submitted[] = $normalized;
        $submitted = array_slice(array_unique($submitted), -10000);
        Cache::put($this->cacheKey, $submitted, now()->addDays(30));
    }
}
