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
            $files = ['sitemap-main.xml', 'sitemap-products.xml', 'sitemap-categories.xml', 'sitemap-blog.xml', 'sitemap-pseo.xml'];

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

    public function pseo()
    {
        return Cache::remember('sitemap_pseo', 3600, function () {
            $platforms = ['shopee','tokopedia','bukalapak','lazada','blibli','zalora'];
            $prefixes = ['pengganti','alternatif','aplikasi-seperti','saingan'];
            $cities = ['jakarta','bandung','surabaya','medan','makassar','semarang','yogyakarta','palembang','denpasar','balikpapan','pekanbaru','malang','solo','bogor','batam','padang','pontianak','banjarmasin','manado','samarinda'];
            $keywords = ['multivendor','toko-online','marketplace','ecommerce'];
            $gateways = ['midtrans','xendit','tripay','duitku'];
            $kotaOngkir = ['jakarta','bandung','surabaya','medan','makassar','semarang','yogyakarta'];
            $tokoOnline = ['source-code','payment-gateway','ongkos-kirim','multivendor','marketplace','murah','terbaik','lengkap','terpercaya','profesional','laravel','flutter','fullstack'];
            $sourceCode = ['gratis','murah','premium','siap-pakai','laravel','flutter'];

            $urls = [];
            // PSEO patterns
            foreach ($prefixes as $pref) {
                foreach ($platforms as $plat) {
                    foreach ($cities as $city) {
                        $urls[] = ['loc' => url("{$pref}-{$plat}-{$city}"), 'priority' => '0.6', 'changefreq' => 'weekly'];
                    }
                }
            }
            foreach ($keywords as $kw) $urls[] = ['loc' => url("beli-aplikasi-{$kw}"), 'priority' => '0.8', 'changefreq' => 'monthly'];
            foreach ($platforms as $p) $urls[] = ['loc' => url("beli-aplikasi-{$p}"), 'priority' => '0.8', 'changefreq' => 'monthly'];
            foreach ($cities as $c) $urls[] = ['loc' => url("source-code-{$c}"), 'priority' => '0.7', 'changefreq' => 'monthly'];
            foreach ($kotaOngkir as $k) $urls[] = ['loc' => url("ongkos-kirim-{$k}"), 'priority' => '0.7', 'changefreq' => 'monthly'];
            foreach ($gateways as $gw) $urls[] = ['loc' => url("payment-gateway-{$gw}"), 'priority' => '0.7', 'changefreq' => 'monthly'];
            foreach ($tokoOnline as $t) $urls[] = ['loc' => url("toko-online-{$t}"), 'priority' => '0.7', 'changefreq' => 'monthly'];
            foreach ($sourceCode as $s) $urls[] = ['loc' => url("source-code-toko-online-{$s}"), 'priority' => '0.7', 'changefreq' => 'monthly'];

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
