<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function index()
    {
        return Cache::remember('sitemap_index', 3600, function () {
            $files = ['sitemap-main.xml', 'sitemap-products.xml', 'sitemap-categories.xml', 'sitemap-blog.xml'];

            $xml = '<?xml version="1.0" encoding="UTF-8"?>';
            $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
            foreach ($files as $file) {
                $xml .= '<sitemap><loc>' . url($file) . '</loc></sitemap>';
            }
            $xml .= '</sitemapindex>';

            return response($xml, 200, ['Content-Type' => 'application/xml']);
        });
    }

    public function main()
    {
        return Cache::remember('sitemap_main', 3600, function () {
            $urls = [
                ['loc' => url('/'), 'priority' => '1.0', 'changefreq' => 'daily'],
                ['loc' => url('/products'), 'priority' => '0.9', 'changefreq' => 'daily'],
                ['loc' => url('/blog'), 'priority' => '0.8', 'changefreq' => 'weekly'],
                ['loc' => url('/docs'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ];

            return $this->renderUrlsXml($urls);
        });
    }

    public function products()
    {
        return Cache::remember('sitemap_products', 3600, function () {
            $urls = Product::where('status', 'approved')->where('published', true)
                ->get()
                ->map(fn ($p) => [
                    'loc' => route('products.show', $p->slug),
                    'priority' => '0.8',
                    'lastmod' => $p->updated_at->toAtomString(),
                ])
                ->all();

            return $this->renderUrlsXml($urls);
        });
    }

    public function categories()
    {
        return Cache::remember('sitemap_categories', 3600, function () {
            $urls = Category::where('status', true)->get()
                ->map(fn ($c) => [
                    'loc' => route('products.index', ['category' => $c->slug]),
                    'priority' => '0.7',
                ])->all();

            return $this->renderUrlsXml($urls);
        });
    }

    public function blog()
    {
        return Cache::remember('sitemap_blog', 3600, function () {
            $urls = BlogPost::where('is_published', true)
                ->where('published_at', '<=', now())
                ->get()
                ->map(fn ($p) => [
                    'loc' => route('blog.show', $p->slug),
                    'priority' => '0.7',
                    'lastmod' => $p->updated_at->toAtomString(),
                ])->all();

            return $this->renderUrlsXml($urls);
        });
    }

    protected function renderUrlsXml(array $urls): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $url) {
            $xml .= '<url>';
            $xml .= '<loc>' . e($url['loc']) . '</loc>';
            if (isset($url['lastmod'])) $xml .= '<lastmod>' . $url['lastmod'] . '</lastmod>';
            if (isset($url['changefreq'])) $xml .= '<changefreq>' . $url['changefreq'] . '</changefreq>';
            if (isset($url['priority'])) $xml .= '<priority>' . $url['priority'] . '</priority>';
            $xml .= '</url>';
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
