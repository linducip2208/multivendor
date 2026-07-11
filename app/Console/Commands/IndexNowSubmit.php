<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Models\Product;
use App\Services\Seo\IndexNowService;
use Illuminate\Console\Command;

class IndexNowSubmit extends Command
{
    protected $signature = 'seo:indexnow {--type=all : all|products|blog|sitemap} {--limit=50 : Max URLs per run}';
    protected $description = 'Submit URLs to IndexNow (Bing, Yandex, Seznam, Naver)';

    public function handle(IndexNowService $service): int
    {
        $type = $this->option('type');
        $limit = (int) $this->option('limit');
        $urls = [];

        if ($type === 'all' || $type === 'products') {
            $products = Product::where('status', 'approved')
                ->latest()
                ->take($limit)
                ->get();

            foreach ($products as $product) {
                $urls[] = route('products.show', $product->slug);
                $urls[] = route('pseo.alternatif', ['slug' => $product->slug]);
            }

            $this->info("Collected " . count($products) . " product URLs");
        }

        if ($type === 'all' || $type === 'blog') {
            $posts = BlogPost::where('is_published', true)
                ->where('published_at', '<=', now())
                ->latest()
                ->take($limit)
                ->get();

            foreach ($posts as $post) {
                $urls[] = route('blog.show', $post->slug);
            }

            $this->info("Collected " . count($posts) . " blog URLs");
        }

        if ($type === 'all' || $type === 'sitemap') {
            $urls[] = url('/');
            $urls[] = url('/docs');
            $urls[] = url('/blog');
            $urls[] = url('/products');
        }

        if (empty($urls)) {
            $this->warn('No URLs to submit.');
            return self::SUCCESS;
        }

        $this->info('Submitting ' . count($urls) . ' URLs to IndexNow...');

        $submitted = $service->submitBatch(array_slice($urls, 0, 10000));

        $this->info("Successfully submitted {$submitted} URLs.");

        return self::SUCCESS;
    }
}
