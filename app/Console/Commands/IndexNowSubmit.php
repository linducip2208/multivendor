<?php

namespace App\Console\Commands;

use App\Services\Seo\IndexNowService;
use Illuminate\Console\Command;

class IndexNowSubmit extends Command
{
    protected $signature = 'seo:indexnow {--url= : Single URL to submit}';
    protected $description = 'Submit URLs to IndexNow search engines (Bing, Yandex, Seznam, Naver)';

    public function handle(IndexNowService $service): int
    {
        if ($this->option('url')) {
            $result = $service->submitUrl($this->option('url'));
            $this->info('Submitted: ' . $this->option('url'));
            return self::SUCCESS;
        }

        $urls = [];

        $posts = \App\Models\BlogPost::where('is_published', true)
            ->where('published_at', '<=', now())
            ->where('updated_at', '>=', now()->subDay())
            ->get();

        foreach ($posts as $post) {
            $urls[] = route('blog.show', $post->slug);
        }

        $products = \App\Models\Product::where('status', 'approved')
            ->where('updated_at', '>=', now()->subDay())
            ->get();

        foreach ($products as $product) {
            $urls[] = route('products.show', $product->slug);
        }

        if (empty($urls)) {
            $this->info('No new URLs to submit.');
            return self::SUCCESS;
        }

        $result = $service->submit($urls);
        $this->info('Submitted ' . ($result['count'] ?? 0) . ' URLs to IndexNow.');

        return self::SUCCESS;
    }
}
