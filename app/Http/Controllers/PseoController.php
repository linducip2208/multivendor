<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PseoController extends Controller
{
    protected function renderPseo(Request $request, string $label, string $canonical, string $keyword = null, string $title = null, string $desc = null)
    {
        $kw = $keyword ?? $label;
        $title = $title ?? "{$label} — Source Code Marketplace Multi Vendor Indonesia";
        $desc = $desc ?? "Butuh {$kw}? MultiVendor platform marketplace lengkap: multi vendor, 10 payment gateway BYOK (Midtrans, Xendit, Tripay), 16 kurir (JNE, J&T, SiCepat), AI analytics (10 provider), POS, wallet, promo engine, API REST. Source code siap pakai Laravel + Flutter.";

        $seo = [
            'title' => $title,
            'description' => $desc,
            'canonical' => $canonical,
            'label' => $label,
            'keyword' => $kw,
        ];

        return view('pseo.source-code', array_merge($seo, [
            'wa' => '6281296052010',
            'appName' => config('app.name'),
        ]));
    }

    public function alternatif(Request $request, $slug)
    {
        $label = 'Alternatif ' . ucwords(str_replace('-', ' ', $slug));
        return $this->renderPseo($request, $label, url("alternatif-{$slug}"), $slug);
    }

    public function sourceCode(Request $request, $slug = null)
    {
        $label = $slug ? 'Source Code ' . ucwords(str_replace('-', ' ', $slug)) : 'Source Code Marketplace Indonesia';
        return $this->renderPseo($request, $label, url('source-code' . ($slug ? "-{$slug}" : '')), $slug ?? 'marketplace');
    }

    public function pengganti(Request $request, $slug)
    {
        $label = 'Pengganti ' . ucwords(str_replace('-', ' ', $slug));
        return $this->renderPseo($request, $label, url("pengganti-{$slug}"), $slug);
    }

    public function beli(Request $request, $slug)
    {
        $label = 'Beli ' . ucwords(str_replace('-', ' ', $slug));
        return $this->renderPseo($request, $label, url("beli-{$slug}"), $slug);
    }

    public function jual(Request $request, $slug)
    {
        $label = 'Jual ' . ucwords(str_replace('-', ' ', $slug));
        return $this->renderPseo($request, $label, url("jual-{$slug}"), $slug);
    }

    public function marketplace(Request $request, $slug = null)
    {
        $label = $slug ? 'Marketplace ' . ucwords(str_replace('-', ' ', $slug)) : 'Marketplace Source Code Indonesia';
        return $this->renderPseo($request, $label, url('marketplace' . ($slug ? "-{$slug}" : '')), $slug ?? 'marketplace');
    }

    public function compare(Request $request, $a, $b)
    {
        $label = ucwords(str_replace('-', ' ', $a)) . ' vs ' . ucwords(str_replace('-', ' ', $b));
        $title = "{$label} — Perbandingan Source Code Marketplace";
        $desc = "Bandingkan {$a} vs {$b}? MultiVendor solusi marketplace multi vendor Indonesia. Source code lengkap: 10 payment gateway, 16 kurir, AI analytics, POS, Flutter app. Alternatif terbaik untuk {$a} dan {$b}.";
        return $this->renderPseo($request, $label, url("compare/{$a}-vs-{$b}"), "{$a} vs {$b}", $title, $desc);
    }

    public function best(Request $request, $slug)
    {
        $label = 'Best ' . ucwords(str_replace('-', ' ', $slug));
        return $this->renderPseo($request, $label, url("best/{$slug}"), $slug);
    }

    public function catchAll(Request $request, $slug)
    {
        $parts = explode('-', $slug);
        $label = ucwords(str_replace('-', ' ', $slug));

        $platforms = ['shopee','tokopedia','lazada','bukalapak','blibli','alibaba','aliexpress','etsy','ebay','amazon',
            'themeforest','codecanyon','appsumo','gumroad','sellfy','creativemarket','envatomarket','sourceforge'];
        $prefixes = ['pengganti','alternatif','aplikasi-seperti','saingan','mirip','source-code','beli-aplikasi','jual-source-code'];

        $kw = implode(', ', array_slice($parts, 0, 5));
        $title = "{$label} — Source Code Marketplace Multi Vendor Indonesia";
        $desc = "Butuh {$kw}? MultiVendor platform marketplace multi vendor Indonesia. 10 payment gateway BYOK, 16 kurir otomatis, AI analytics, POS, wallet, promo engine. Source code Laravel + Flutter siap pakai. Free install + 1 bulan support.";

        return $this->renderPseo($request, $label, url($slug), $kw, $title, $desc);
    }
}
