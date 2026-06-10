<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class PseoSitemapController extends Controller
{
    protected function xml(array $urls): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $u) {
            $xml .= '<url><loc>' . e($u['loc']) . '</loc>';
            if (isset($u['changefreq'])) $xml .= '<changefreq>' . $u['changefreq'] . '</changefreq>';
            if (isset($u['priority'])) $xml .= '<priority>' . $u['priority'] . '</priority>';
            $xml .= '</url>';
        }
        $xml .= '</urlset>';
        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function index()
    {
        $chunks = $this->getChunkCount();
        $files = [];
        for ($i = 1; $i <= $chunks; $i++) {
            $files[] = "sitemap-pseo-{$i}.xml";
        }
        // Also include main sitemaps
        array_unshift($files, 'sitemap-main.xml', 'sitemap-products.xml', 'sitemap-categories.xml', 'sitemap-blog.xml');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($files as $f) {
            $xml .= '<sitemap><loc>' . url($f) . '</loc></sitemap>';
        }
        $xml .= '</sitemapindex>';
        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function chunk($num)
    {
        $chunkSize = 45000; // ~1.4MB XML
        $allUrls = $this->generateAllUrls();
        $offset = ((int)$num - 1) * $chunkSize;
        $slice = array_slice($allUrls, $offset, $chunkSize);
        return $this->xml($slice);
    }

    protected function getChunkCount(): int
    {
        return (int) ceil(count($this->generateAllUrls()) / 45000);
    }

    protected function generateAllUrls(): array
    {
        // Build from cache to avoid regenerating 1M URLs each request
        return \Illuminate\Support\Facades\Cache::remember('pseo_all_urls', 86400, function () {
            $urls = [];

            // Core platforms
            $platforms = ['shopee','tokopedia','lazada','bukalapak','blibli','alibaba','aliexpress','etsy','ebay','amazon','themeforest','codecanyon','appsumo','gumroad','sellfy','creativemarket','envatomarket','sourceforge'];
            
            // Prefixes
            $prefixes = ['pengganti','alternatif','aplikasi-seperti','saingan','mirip','source-code','beli-aplikasi','jual-source-code'];
            
            // Cities (100+ major Indonesian cities)
            $cities = ['jakarta','bandung','surabaya','medan','makassar','semarang','yogyakarta','palembang','denpasar','balikpapan','pekanbaru','malang','solo','bogor','batam','padang','pontianak','banjarmasin','manado','samarinda','depok','tangerang','bekasi','bogor','cilegon','serang','cirebon','tasikmalaya','sukabumi','garut','purwakarta','karawang','cikarang','cikampek','subang','indramayu','majalengka','kuningan','ciamis','banjar','pangandaran','cilacap','purwokerto','tegal','pekalongan','magelang','klaten','boyolali','sragen','wonogiri','karanganyar','sukoharjo','kudus','pati','rembang','blora','grobogan','demak','kendal','batang','pemalang','brebes','banyuwangi','jember','probolinggo','pasuruan','mojokerto','jombang','nganjuk','madiun','magetan','ponorogo','pacitan','trenggalek','tulungagung','blitar','kediri','lamongan','gresik','sidoarjo','bangkalan','sampang','pamekasan','sumenep','buleleng','gianyar','tabanan','badung','bima','dompu','sumbawa','mataram','ende','maumere','kupang','atambua','ambon','ternate','jayapura','sorong','manokwari','merauke','timika','nabire','biak'];

            // Features
            $features = ['multivendor','marketplace','toko-online','ecommerce','payment-gateway','ongkos-kirim','ai-analytics','pos-system','source-code','aplikasi','platform','sistem','script','cms','erp','white-label','b2b','saas','digital-product','multi-seller','vendor-management'];
            
            $sourceCodeTerms = ['source-code-toko-online','source-code-marketplace','jual-source-code','beli-source-code','aplikasi-toko-online','aplikasi-marketplace','aplikasi-multivendor','script-toko-online','script-marketplace','source-code-ecommerce','source-code-multivendor','white-label-marketplace','multi-vendor-script','marketplace-cms','toko-online-laravel','toko-online-flutter','toko-online-fullstack','toko-online-android','toko-online-ios'];

            // Pattern 1: {prefix}-{platform}-{city} 
            foreach ($prefixes as $pref) {
                foreach ($platforms as $plat) {
                    foreach (array_slice($cities, 0, 50) as $city) { // 50 cities to control volume
                        $urls[] = ['loc' => url("{$pref}-{$plat}-{$city}"), 'priority' => '0.6', 'changefreq' => 'weekly'];
                    }
                }
            }

            // Pattern 2: {feature}-{platform}-{city}
            $topFeatures = array_slice($features, 0, 7);
            foreach ($topFeatures as $feat) {
                foreach (array_slice($platforms, 0, 10) as $plat) {
                    foreach (array_slice($cities, 0, 20) as $city) {
                        $urls[] = ['loc' => url("{$feat}-{$plat}-{$city}"), 'priority' => '0.6', 'changefreq' => 'weekly'];
                    }
                }
            }

            // Pattern 3: Source code keywords
            foreach ($sourceCodeTerms as $term) {
                $urls[] = ['loc' => url($term), 'priority' => '0.8', 'changefreq' => 'weekly'];
                foreach (array_slice($cities, 0, 30) as $city) {
                    $urls[] = ['loc' => url("{$term}-{$city}"), 'priority' => '0.7', 'changefreq' => 'weekly'];
                }
            }

            // Pattern 4: beli-aplikasi-{platform} and variations
            foreach ($platforms as $p) {
                $urls[] = ['loc' => url("beli-aplikasi-{$p}"), 'priority' => '0.8', 'changefreq' => 'monthly'];
                $urls[] = ['loc' => url("pengganti-{$p}"), 'priority' => '0.8', 'changefreq' => 'monthly'];
                $urls[] = ['loc' => url("aplikasi-seperti-{$p}"), 'priority' => '0.8', 'changefreq' => 'monthly'];
                $urls[] = ['loc' => url("jual-source-code-{$p}"), 'priority' => '0.8', 'changefreq' => 'monthly'];
            }

            // Pattern 5: Feature pages
            foreach ($features as $f) {
                $urls[] = ['loc' => url("{$f}-indonesia"), 'priority' => '0.7', 'changefreq' => 'monthly'];
                $urls[] = ['loc' => url("{$f}-terbaik"), 'priority' => '0.7', 'changefreq' => 'monthly'];
            }

            // Pattern 6: City-based source code + ongkir
            foreach (array_slice($cities, 0, 80) as $city) {
                $urls[] = ['loc' => url("source-code-{$city}"), 'priority' => '0.7', 'changefreq' => 'monthly'];
                $urls[] = ['loc' => url("ongkos-kirim-{$city}"), 'priority' => '0.7', 'changefreq' => 'monthly'];
                $urls[] = ['loc' => url("jual-source-code-{$city}"), 'priority' => '0.7', 'changefreq' => 'monthly'];
                $urls[] = ['loc' => url("aplikasi-marketplace-{$city}"), 'priority' => '0.7', 'changefreq' => 'monthly'];
            }

            // Pattern 7: Payment gateway pages
            $gateways = ['midtrans','xendit','tripay','duitku','oyindonesia','ipaymu','faspay','doku','esiapay'];
            foreach ($gateways as $gw) {
                $urls[] = ['loc' => url("payment-gateway-{$gw}"), 'priority' => '0.7', 'changefreq' => 'monthly'];
            }

            // Pattern 8: toko-online-{keyword}
            $tokoKW = ['source-code','payment-gateway','ongkos-kirim','multivendor','marketplace','murah','terbaik','lengkap','terpercaya','profesional','laravel','flutter','fullstack','android','ios','gratis','premium','siap-pakai','reseller','dropship','grosir','cod','gratis-ongkir','terlaris','terbaru','2024','2025','2026'];
            foreach ($tokoKW as $kw) {
                $urls[] = ['loc' => url("toko-online-{$kw}"), 'priority' => '0.7', 'changefreq' => 'monthly'];
            }

            // Pattern 9: Product comparisons (sample pairs)
            $products = \App\Models\Product::where('status', 'approved')->inRandomOrder()->take(100)->pluck('slug')->toArray();
            for ($i = 0; $i < count($products) - 1; $i += 2) {
                if (isset($products[$i]) && isset($products[$i+1])) {
                    $urls[] = ['loc' => url("compare/{$products[$i]}-vs-{$products[$i+1]}"), 'priority' => '0.5', 'changefreq' => 'monthly'];
                }
            }

            return array_unique($urls, SORT_REGULAR);
        });
    }
}
