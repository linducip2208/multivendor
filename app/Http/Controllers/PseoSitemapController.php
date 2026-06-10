<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class PseoSitemapController extends Controller
{
    const CHUNK_SIZE = 10000; // ~1.3MB XML per chunk

    protected array $platforms = [
        'shopee','tokopedia','lazada','bukalapak','blibli','alibaba','aliexpress','etsy','ebay','amazon',
        'themeforest','codecanyon','appsumo','gumroad','sellfy','creativemarket','envatomarket','sourceforge',
        'codecanyon-clone','multi-vendor-marketplace','digital-product-marketplace','b2b-marketplace',
        'saas-marketplace','white-label-marketplace','marketplace-source-code','marketplace-script',
        'e-commerce-platform','online-store-builder','digital-store-platform','multi-seller-marketplace',
        'vendor-management-system','standalone-marketplace-solution','cms-marketplace','erp-marketplace',
    ];

    protected array $features = [
        'multivendor','toko-online','marketplace','ecommerce','payment-gateway','ongkos-kirim',
        'ai-analytics','pos-system','source-code','aplikasi','platform','sistem','script','cms','erp',
        'white-label','b2b','saas','digital-product','multi-seller','vendor-management',
        'reseller','dropship','gratis-ongkir','cod','terbaik','murah','lengkap','terpercaya',
        'profesional','laravel','flutter','fullstack','android','ios',
    ];

    protected array $prefixes = ['pengganti','alternatif','source-code','toko-online','jual-source-code','beli-aplikasi'];

    protected array $cities = [
        'jakarta','bandung','surabaya','medan','makassar','semarang','yogyakarta','palembang','denpasar',
        'balikpapan','pekanbaru','malang','solo','bogor','batam','padang','pontianak','banjarmasin',
        'manado','samarinda','depok','tangerang','bekasi','cilegon','serang','cirebon','tasikmalaya',
        'sukabumi','garut','purwakarta','karawang','cikarang','cikampek','subang','indramayu',
        'majalengka','kuningan','ciamis','banjar','pangandaran','cilacap','purwokerto','tegal',
        'pekalongan','magelang','klaten','boyolali','sragen','wonogiri','karanganyar','sukoharjo',
        'kudus','pati','rembang','blora','grobogan','demak','kendal','batang','pemalang','brebes',
        'banyuwangi','jember','probolinggo','pasuruan','mojokerto','jombang','nganjuk','madiun',
        'magetan','ponorogo','pacitan','trenggalek','tulungagung','blitar','kediri','lamongan',
        'gresik','sidoarjo','bangkalan','sampang','pamekasan','sumenep','buleleng','gianyar',
        'tabanan','badung','bima','dompu','sumbawa','mataram','ende','maumere','kupang','atambua',
        'ambon','ternate','jayapura','sorong','manokwari','merauke','timika','nabire','biak',
        'singaraja','negara','bangli','ampana','kolaka','bau-bau','kendari','palu','poso',
        'gorontalo','maros','watampone','palopo','parepare','pinrang','mamuju','majene',
        'tarakan','nunukan','berau','tenggarong','bontang','sangatta','tanjungselor',
        'mentawai','solok','bukittinggi','payakumbuh','pariaman','sawahlunto','dumai','bengkalis',
        'jambi','muaro-jambi','bungho','tebo','sarolangun','lahat','pagaralam','lubuklinggau',
        'baturaja','martapura','tanjung','barabai','amlapura','rantau','kandangan','barito',
    ];

    protected function combos(): array
    {
        $p = count($this->platforms);
        $pr = count($this->prefixes);
        $c = count($this->cities);
        $f = count($this->features);

        return [
            ['dims' => [$pr, $p, $c, $f],        'fn' => 4, 'priority' => '0.5', 'changefreq' => 'monthly'],
            ['dims' => [$pr, $p, $c],            'fn' => 1, 'priority' => '0.6', 'changefreq' => 'weekly'],
            ['dims' => [$f, $p, $c],             'fn' => 2, 'priority' => '0.6', 'changefreq' => 'weekly'],
            ['dims' => [$pr, $p],                'fn' => 3, 'priority' => '0.7', 'changefreq' => 'weekly'],
            ['dims' => [$p, $c],                 'fn' => 5, 'priority' => '0.6', 'changefreq' => 'weekly'],
            ['dims' => [$f, $c],                 'fn' => 6, 'priority' => '0.5', 'changefreq' => 'monthly'],
            ['dims' => [$pr, $c],                'fn' => 7, 'priority' => '0.5', 'changefreq' => 'monthly'],
            ['dims' => [$f, $p],                 'fn' => 11,'priority' => '0.6', 'changefreq' => 'monthly'],
            ['dims' => [$p],                     'fn' => 8, 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['dims' => [$p],                     'fn' => 9, 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['dims' => [$p],                     'fn' => 10,'priority' => '0.7', 'changefreq' => 'monthly'],
            ['dims' => [$f],                     'fn' => 12,'priority' => '0.7', 'changefreq' => 'monthly'],
            ['dims' => [$f],                     'fn' => 13,'priority' => '0.7', 'changefreq' => 'monthly'],
            ['dims' => [$pr],                    'fn' => 14,'priority' => '0.7', 'changefreq' => 'monthly'],
            ['dims' => [$f, $pr],                'fn' => 15,'priority' => '0.5', 'changefreq' => 'monthly'],
        ];
    }

    protected function totalPatterns(): int
    {
        $total = 0;
        foreach ($this->combos() as $combo) {
            $total += array_product($combo['dims']);
        }
        return $total;
    }

    public function getChunkCount(): int
    {
        return (int) ceil($this->totalPatterns() / self::CHUNK_SIZE);
    }

    public function index(): Response
    {
        $chunks = $this->getChunkCount();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        $xml .= '<sitemap><loc>' . url('sitemap-main.xml') . '</loc></sitemap>';
        $xml .= '<sitemap><loc>' . url('sitemap-products.xml') . '</loc></sitemap>';
        $xml .= '<sitemap><loc>' . url('sitemap-categories.xml') . '</loc></sitemap>';
        $xml .= '<sitemap><loc>' . url('sitemap-blog.xml') . '</loc></sitemap>';
        for ($i = 1; $i <= $chunks; $i++) {
            $xml .= '<sitemap><loc>' . url("sitemap-pseo-{$i}.xml") . '</loc></sitemap>';
        }
        $xml .= '</sitemapindex>';
        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function chunk(int $num): Response
    {
        $urls = $this->generateChunk($num);
        return $this->renderXml($urls);
    }

    protected function generateChunk(int $num): array
    {
        $offset = ($num - 1) * self::CHUNK_SIZE;
        $urls = [];

        for ($i = $offset; $i < $offset + self::CHUNK_SIZE; $i++) {
            $url = $this->getUrl($i);
            if ($url === null) break;
            $urls[] = $url;
        }

        return $urls;
    }

    protected function getUrl(int $index): ?array
    {
        $remaining = $index;
        foreach ($this->combos() as $combo) {
            $total = array_product($combo['dims']);
            if ($remaining < $total) {
                $indices = [];
                $r = $remaining;
                $dims = $combo['dims'];
                for ($d = count($dims) - 1; $d >= 0; $d--) {
                    $indices[$d] = $r % $dims[$d];
                    $r = intdiv($r, $dims[$d]);
                }
                $fn = $combo['fn'];
                $loc = $this->buildUrl($fn, $indices);
                return ['loc' => $loc, 'priority' => $combo['priority'], 'changefreq' => $combo['changefreq']];
            }
            $remaining -= $total;
        }
        return null;
    }

    protected function buildUrl(int $pattern, array $idx): string
    {
        $i = $idx[0] ?? 0;
        $j = $idx[1] ?? 0;
        $k = $idx[2] ?? 0;
        $l = $idx[3] ?? 0;

        return match ($pattern) {
            1  => url("{$this->prefixes[$i]}-{$this->platforms[$j]}-{$this->cities[$k]}"),
            2  => url("{$this->features[$i]}-{$this->platforms[$j]}-{$this->cities[$k]}"),
            3  => url("{$this->prefixes[$i]}-{$this->platforms[$j]}"),
            4  => url("{$this->prefixes[$i]}-{$this->platforms[$j]}-{$this->cities[$k]}-{$this->features[$l]}"),
            5  => url("toko-online-{$this->platforms[$i]}-{$this->cities[$j]}"),
            6  => url("{$this->features[$i]}-{$this->cities[$j]}"),
            7  => url("{$this->prefixes[$i]}-{$this->cities[$j]}"),
            8  => url("beli-{$this->platforms[$i]}"),
            9  => url("jual-{$this->platforms[$i]}"),
            10 => url("marketplace-{$this->platforms[$i]}"),
            11 => url("{$this->features[$i]}-{$this->platforms[$j]}"),
            12 => url("toko-online-{$this->features[$i]}"),
            13 => url("beli-{$this->features[$i]}"),
            14 => url("toko-online-{$this->prefixes[$i]}"),
            15 => url("{$this->features[$i]}-{$this->prefixes[$j]}"),
            default => url('/'),
        };
    }

    protected function renderXml(array $urls): Response
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
}
