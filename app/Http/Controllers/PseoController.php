<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Http\Request;

class PseoController extends Controller
{
    protected string $wa = '6281234567890';
    protected string $appName = 'MultiVendor';

    protected function view(string $view, array $data = [])
    {
        $data['wa'] = $this->wa;
        $data['appName'] = $this->appName;
        return view('pseo.' . $view, $data);
    }

    protected function jsonLd(array $data): string
    {
        return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    }

    /*
    |--------------------------------------------------------------------------
    | Pattern 1: /best-{category} — Top 10 produk per kategori
    |--------------------------------------------------------------------------
    */
    public function bestCategory($slug)
    {
        $category = Category::where('slug', $slug)->where('status', true)->firstOrFail();
        $products = Product::where('category_id', $category->id)->where('status', 'approved')
            ->withCount(['orderItems as sold' => fn($q) => $q->whereHas('order', fn($o) => $o->where('order_status', '!=', 'canceled'))])
            ->orderByDesc('sold')->take(10)->with('shop')->get();

        $title = "10 Produk {$category->name} Terlaris — Rekomendasi Terbaik " . date('Y');
        $desc = "Daftar 10 produk {$category->name} paling laris di {$this->appName}. Review, harga, dan rekomendasi {$category->name} terbaik tahun " . date('Y') . ". Belanja aman dengan payment gateway Indonesia.";
        $canonical = route('pseo.best-category', $slug);

        $jsonld = $this->jsonLd([
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $title,
            'description' => $desc,
            'itemListElement' => $products->map(fn($p, $i) => [
                '@type' => 'ListItem', 'position' => $i + 1,
                'item' => ['@type' => 'Product', 'name' => $p->name, 'url' => route('products.show', $p->slug)]
            ])->toArray()
        ]);

        return $this->view('best-category', compact('category', 'products', 'title', 'desc', 'canonical', 'jsonld'));
    }

    /*
    |--------------------------------------------------------------------------
    | Pattern 2: /best-{category}-{year}
    |--------------------------------------------------------------------------
    */
    public function bestCategoryYear($slug, $year)
    {
        $category = Category::where('slug', $slug)->where('status', true)->firstOrFail();
        $products = Product::where('category_id', $category->id)->where('status', 'approved')
            ->whereHas('orderItems.order', fn($q) => $q->whereYear('created_at', $year))
            ->withCount(['orderItems as sold' => fn($q) => $q->whereHas('order', fn($o) => $o->whereYear('created_at', $year))])
            ->orderByDesc('sold')->take(10)->with('shop')->get();

        $title = "10 Produk {$category->name} Terlaris {$year} — Best Seller {$category->name}";
        $desc = "Rekomendasi {$category->name} terbaik tahun {$year}. Produk paling laris di kategori {$category->name} berdasarkan data penjualan real. Belanja online aman.";
        $canonical = route('pseo.best-category-year', [$slug, $year]);

        return $this->view('best-category', compact('category', 'products', 'title', 'desc', 'canonical', 'year'))->with('jsonld', $this->jsonLd([
            '@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => $title,
            'itemListElement' => $products->map(fn($p,$i)=>['@type'=>'ListItem','position'=>$i+1,'item'=>['@type'=>'Product','name'=>$p->name]])->toArray()
        ]));
    }

    /*
    |--------------------------------------------------------------------------
    | Pattern 3: /alternatives-to-{product-slug}
    |--------------------------------------------------------------------------
    */
    public function alternatives($slug)
    {
        $product = Product::where('slug', $slug)->where('status', 'approved')->with('shop', 'category')->firstOrFail();
        $alternatives = Product::where('category_id', $product->category_id)->where('id', '!=', $product->id)
            ->where('status', 'approved')->with('shop')->take(12)->get();

        $title = "Alternatif {$product->name} — Produk Serupa dengan Harga Lebih Murah";
        $desc = "Cari alternatif {$product->name}? Lihat " . $alternatives->count() . " produk serupa di kategori {$product->category->name}. Bandingkan harga, fitur, dan review. Hemat belanja online.";
        $canonical = route('pseo.alternatives', $slug);

        return $this->view('alternatives', compact('product', 'alternatives', 'title', 'desc', 'canonical'))->with('jsonld', $this->jsonLd([
            '@context'=>'https://schema.org','@type'=>'Product','name'=>$product->name,'description'=>strip_tags($product->short_description??''),
            'offers'=>['@type'=>'Offer','price'=>$product->getEffectivePrice(),'priceCurrency'=>'IDR']
        ]));
    }

    /*
    |--------------------------------------------------------------------------
    | Pattern 4: /compare/{a}-vs-{b}
    |--------------------------------------------------------------------------
    */
    public function compare($a, $b)
    {
        $pa = Product::where('slug', $a)->where('status', 'approved')->with('shop')->first();
        $pb = Product::where('slug', $b)->where('status', 'approved')->with('shop')->first();
        if (!$pa || !$pb) abort(404);

        $title = "{$pa->name} vs {$pb->name} — Perbandingan Lengkap 2025";
        $desc = "Bandingkan {$pa->name} vs {$pb->name}. Lihat perbedaan harga Rp " . number_format($pa->getEffectivePrice(),0,',','.') . " vs Rp " . number_format($pb->getEffectivePrice(),0,',','.') . ", fitur, review. Mana yang lebih bagus?";
        $canonical = route('pseo.compare', [$a, $b]);

        return $this->view('compare', compact('pa', 'pb', 'title', 'desc', 'canonical'))->with('jsonld', $this->jsonLd([
            '@context'=>'https://schema.org','@type'=>'WebPage','name'=>$title,'description'=>$desc,
            'mainEntity'=>['@type'=>'Product','name'=>$pa->name],'about'=>['@type'=>'Product','name'=>$pb->name]
        ]));
    }

    /*
    |--------------------------------------------------------------------------
    | Pattern 5: /beli-{product-slug} — Source code sales + product purchase
    |--------------------------------------------------------------------------
    */
    public function beli($slug)
    {
        $product = Product::where('slug', $slug)->where('status', 'approved')->with('shop', 'category')->firstOrFail();
        $title = "Beli {$product->name} — Harga Terbaik " . date('Y') . " | {$this->appName}";
        $desc = "Beli {$product->name} harga murah di {$this->appName}. " . ($product->shop->name ?? '') . " — payment gateway Indonesia lengkap, ongkos kirim murah. Belanja online aman dan cepat.";
        $canonical = route('pseo.beli', $slug);

        return $this->view('beli', compact('product', 'title', 'desc', 'canonical'))->with('jsonld', $this->jsonLd([
            '@context'=>'https://schema.org','@type'=>'Product','name'=>$product->name,'description'=>strip_tags($product->short_description??''),
            'offers'=>['@type'=>'Offer','price'=>$product->getEffectivePrice(),'priceCurrency'=>'IDR','availability'=>'https://schema.org/InStock']
        ]));
    }

    /*
    |--------------------------------------------------------------------------
    | Pattern 6: Source Code Sales — /beli-aplikasi-multivendor dll
    |--------------------------------------------------------------------------
    */
    public function sourceCodePage($keyword = null)
    {
        $keywords = [
            'multivendor' => 'Aplikasi Multivendor',
            'toko-online' => 'Aplikasi Toko Online',
            'marketplace' => 'Aplikasi Marketplace',
            'ecommerce' => 'Aplikasi E-Commerce',
        ];
        $label = $keywords[$keyword] ?? 'Aplikasi Multivendor E-Commerce';
        $title = "Beli {$label} — Source Code Siap Pakai | {$this->appName}";
        $desc = "Jual source code {$label} full-stack Laravel + MySQL + Flutter. Payment gateway Indonesia lengkap (Midtrans, Xendit, Tripay, dll), ongkos kirim RajaOngkir, AI analytics. Siap pakai, tinggal deploy.";
        $canonical = route('pseo.source-code', $keyword);

        return $this->view('source-code', compact('label', 'title', 'desc', 'canonical', 'keyword'));
    }

    public function sourceCodeCity($city = null)
    {
        $cityName = $city ? ucwords(str_replace('-', ' ', $city)) : 'Indonesia';
        $title = "Jual Source Code Multivendor di {$cityName} — Aplikasi E-Commerce Siap Pakai";
        $desc = "Butuh source code multivendor di {$cityName}? Kami jual aplikasi e-commerce full-stack Laravel + Flutter. Payment gateway Indonesia, ongkos kirim, AI analytics. Free install + setup.";
        $canonical = route('pseo.source-code-city', $city);

        return $this->view('source-code-city', compact('cityName', 'title', 'desc', 'canonical', 'city'));
    }

    /*
    |--------------------------------------------------------------------------
    | Pattern 7: /ongkos-kirim-{city}
    |--------------------------------------------------------------------------
    */
    public function shippingCity($city = null)
    {
        $cityName = $city ? ucwords(str_replace('-', ' ', $city)) : 'Indonesia';
        $title = "Cek Ongkos Kirim ke {$cityName} — Estimasi Biaya Pengiriman Terbaru";
        $desc = "Cek ongkos kirim ke {$cityName} dari JNE, J&T, SiCepat, TIKI, POS, AnterAja, Lion Parcel. Estimasi biaya pengiriman murah ke {$cityName}. Bandingkan harga kurir terbaik.";
        $canonical = route('pseo.shipping-city', $city);

        $couriers = ['JNE REG', 'JNE YES', 'J&T EZ', 'SiCepat REG', 'SiCepat BEST', 'TIKI REG', 'POS Kilat Khusus', 'AnterAja Next Day', 'Lion Parcel'];
        return $this->view('shipping-city', compact('cityName', 'title', 'desc', 'canonical', 'city', 'couriers'));
    }

    /*
    |--------------------------------------------------------------------------
    | Pattern 8: /payment-gateway-{method}
    |--------------------------------------------------------------------------
    */
    public function paymentGateway($method = null)
    {
        $gateways = ['midtrans'=>'Midtrans','xendit'=>'Xendit','tripay'=>'Tripay','duitku'=>'Duitku','oyindonesia'=>'OY! Indonesia','ipaymu'=>'iPaymu','faspay'=>'Faspay','doku'=>'DOKU','esiapay'=>'ESIA Pay'];
        $gwName = $gateways[$method] ?? 'Payment Gateway Indonesia';
        $title = "Integrasi {$gwName} — Payment Gateway Terbaik untuk Multivendor";
        $desc = "Cara integrasi {$gwName} ke aplikasi multivendor e-commerce. Panduan lengkap setup {$gwName} payment gateway. Support VA, QRIS, e-wallet, transfer bank. Cocok untuk marketplace Indonesia.";
        $canonical = route('pseo.payment-gateway', $method);

        return $this->view('payment-gateway', compact('gwName', 'title', 'desc', 'canonical', 'method'));
    }
}
