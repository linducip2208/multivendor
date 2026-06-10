<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <meta name="keywords" content="{{ $keyword }}, source code marketplace, multivendor, toko online, ecommerce indonesia">
    <link rel="canonical" href="{{ $canonical }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --brand: #4F46E5; --brand-dark: #3730A3; }
        body { font-family: 'Inter', system-ui, sans-serif; background: #f8f9fc; color: #1e293b; }
        .hero { background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%); color: white; padding: 60px 0 40px; }
        .hero h1 { font-weight: 800; font-size: 2.25rem; line-height: 1.2; }
        .hero p { opacity: .85; font-size: 1.1rem; max-width: 640px; }
        .cta-card { border-radius: 16px; background: white; box-shadow: 0 4px 24px rgba(0,0,0,.08); padding: 32px; margin-top: -40px; position: relative; z-index: 2; }
        .feature-card { border: none; border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,.04); transition: transform .2s; }
        .feature-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.08); }
        .feature-card i { font-size: 1.5rem; color: var(--brand); }
        .btn-brand { background: linear-gradient(135deg, #4F46E5, #6366F1); border: none; border-radius: 12px; padding: 12px 28px; font-weight: 600; color: white; }
        .btn-brand:hover { background: linear-gradient(135deg, #4338CA, #4F46E5); color: white; }
        .btn-wa { background: linear-gradient(135deg, #059669, #10B981); border: none; border-radius: 12px; padding: 12px 28px; font-weight: 600; color: white; }
        .btn-wa:hover { background: linear-gradient(135deg, #047857, #059669); color: white; }
        .platform-badge { display: inline-block; background: #EEF2FF; color: #4338CA; padding: 6px 14px; border-radius: 20px; font-size: .85rem; font-weight: 500; margin: 4px; }
        .stats-bar { background: white; border-radius: 14px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,.04); }
        .stats-bar .stat-value { font-size: 1.5rem; font-weight: 700; color: var(--brand); }
        .faq-item { border: none; border-radius: 12px; margin-bottom: 8px; overflow: hidden; }
        .faq-item .accordion-button { font-weight: 600; border-radius: 12px!important; }
        .faq-item .accordion-button:not(.collapsed) { background: #EEF2FF; color: var(--brand); }
        footer { background: #1e1b4b; color: #cbd5e1; padding: 40px 0 20px; }
        @media (max-width: 768px) {
            .hero h1 { font-size: 1.6rem; }
            .hero { padding: 40px 0 30px; }
        }
    </style>
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "WebApplication",
        "name": "{{ $title }}",
        "description": "{{ $description }}",
        "url": "{{ $canonical }}",
        "applicationCategory": "E-Commerce",
        "operatingSystem": "Web, Android, iOS",
        "offers": {
            "@@type": "Offer",
            "category": "Source Code",
            "price": "0",
            "priceCurrency": "IDR"
        }
    }
    </script>
</head>
<body>

{{-- Hero --}}
<section class="hero">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h1>{{ $title }}</h1>
                <p class="mt-3">{{ $description }}</p>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <a href="https://wa.me/{{ $wa }}" target="_blank" class="btn btn-wa">
                        <i class="fab fa-whatsapp me-2"></i>Hubungi via WhatsApp
                    </a>
                    <a href="{{ url('/') }}" class="btn btn-brand">
                        <i class="fas fa-play me-2"></i>Lihat Demo
                    </a>
                </div>
            </div>
            <div class="col-lg-4 d-none d-lg-block text-center">
                <i class="fas fa-store" style="font-size:8rem;opacity:.15;"></i>
            </div>
        </div>
    </div>
</section>

{{-- CTA Card --}}
<div class="container">
    <div class="cta-card">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h3 class="fw-bold mb-2">Jual Source Code Marketplace Multi Vendor</h3>
                <p class="text-muted mb-0">Platform toko online multi vendor lengkap: admin panel, vendor panel, customer app. Source code siap pakai — Laravel + MySQL + Flutter. Free install, 1 bulan support gratis.</p>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <a href="https://wa.me/{{ $wa }}" target="_blank" class="btn btn-wa btn-lg">
                    <i class="fab fa-whatsapp me-2"></i>Beli Sekarang
                </a>
            </div>
        </div>
    </div>
</div>

{{-- Features --}}
<section class="container my-5">
    <div class="text-center mb-4">
        <h2 class="fw-bold">Fitur Lengkap Marketplace</h2>
        <p class="text-muted">Semua yang Anda butuhkan untuk menjalankan marketplace multi vendor</p>
    </div>
    <div class="row g-3">
        @php
        $features = [
            ['icon'=>'fa-store','title'=>'Multi Vendor','desc'=>'Vendor daftar, buka toko, kelola produk. Admin kontrol & komisi otomatis.'],
            ['icon'=>'fa-box','title'=>'Produk','desc'=>'5-tab form, WYSIWYG editor, foto, video, varian, tags, SEO meta.'],
            ['icon'=>'fa-cash-register','title'=>'POS','desc'=>'Point of Sale transaksi offline. Scan barcode, quick add to cart.'],
            ['icon'=>'fa-credit-card','title'=>'Payment Gateway','desc'=>'10 gateway BYOK: Midtrans, Xendit, Tripay. User input API key sendiri.'],
            ['icon'=>'fa-truck-fast','title'=>'Shipping','desc'=>'16 kurir BYOK: JNE, J&T, SiCepat. Ongkir real-time via RajaOngkir.'],
            ['icon'=>'fa-brain','title'=>'AI Analytics','desc'=>'10 AI provider termasuk Ollama (self-hosted gratis). Insight otomatis.'],
            ['icon'=>'fa-wallet','title'=>'Wallet & Komisi','desc'=>'Dompet digital, komisi otomatis, pencairan dana (withdraw).'],
            ['icon'=>'fa-tags','title'=>'Promo Engine','desc'=>'Kupon, Flash Deal, Deal of Day, Featured, Clearance Sale.'],
            ['icon'=>'fa-chart-line','title'=>'Laporan','desc'=>'Revenue, top produk, AI insight, export CSV. 3 tipe laporan.'],
            ['icon'=>'fa-blog','title'=>'Blog + SEO','desc'=>'CMS blog, sitemap otomatis, IndexNow, robots.txt, RSS feed.'],
            ['icon'=>'fa-heart','title'=>'Customer','desc'=>'Wishlist, compare, loyalty points, referral, ticket support, feed.'],
            ['icon'=>'fa-code','title'=>'REST API','desc'=>'API v1/v2/v3 siap untuk Flutter, Android, iOS, integrasi pihak ketiga.'],
        ];
        @endphp
        @foreach($features as $f)
        <div class="col-md-4 col-sm-6">
            <div class="feature-card card p-4 h-100">
                <i class="fas {{ $f['icon'] }} mb-3"></i>
                <h5 class="fw-bold">{{ $f['title'] }}</h5>
                <p class="text-muted small mb-0">{{ $f['desc'] }}</p>
            </div>
        </div>
        @endforeach
    </div>
</section>

{{-- Platform Alternatives --}}
<section class="container my-5">
    <div class="text-center mb-4">
        <h2 class="fw-bold">Alternatif Platform Marketplace</h2>
        <p class="text-muted">Platform marketplace pengganti yang bisa Anda miliki source code-nya</p>
    </div>
    <div class="text-center">
        @php
        $platforms = ['Shopee','Tokopedia','Lazada','Bukalapak','Blibli','Alibaba','AliExpress','Etsy','eBay','Amazon',
            'ThemeForest','CodeCanyon','AppSumo','Gumroad','Sellfy','Creative Market','Envato Market','SourceForge'];
        @endphp
        @foreach($platforms as $pl)
            <a href="{{ url('pengganti-'.strtolower(str_replace(' ','-',$pl))) }}" class="platform-badge text-decoration-none">{{ $pl }}</a>
        @endforeach
    </div>
</section>

{{-- FAQ --}}
<section class="container my-5">
    <h2 class="fw-bold text-center mb-4">Pertanyaan Umum</h2>
    <div class="accordion" id="faq">
        @php
        $faqs = [
            ['q'=>'Apa itu Multi Vendor Marketplace?','a'=>'Platform e-commerce di mana banyak penjual (vendor) bisa membuka toko dan menjual produk dalam satu website. Admin mengelola komisi, kontrol kualitas, dan keseluruhan sistem.'],
            ['q'=>'Payment gateway apa yang didukung?','a'=>'10+ gateway: Midtrans (Snap & Core API), Xendit, Tripay, Duitku, OY! Indonesia, iPaymu, Faspay, DOKU, ESIA Pay — semua BYOK (bawa API key sendiri).'],
            ['q'=>'Kurir apa saja yang tersedia?','a'=>'16 kurir via RajaOngkir: JNE (REG, OKE, YES), J&T (EZ, ECO), POS, SiCepat (BEST, REG, GOKIL), TIKI, Ninja, AnterAja, Wahana, Lion Parcel, Indah Cargo, dan lainnya.'],
            ['q'=>'Apakah ada aplikasi mobile?','a'=>'Ya. REST API v1/v2/v3 siap untuk Flutter, Android, iOS. Bisa langsung integrasi dengan aplikasi mobile kustom Anda.'],
            ['q'=>'Apakah bisa self-host AI?','a'=>'Ya. Dukung Ollama untuk self-hosted LLM gratis (Llama 3, Mistral, DeepSeek). Plus OpenAI, DeepSeek, Groq, OpenRouter — semua user input API key sendiri.'],
            ['q'=>'Apakah source code full?','a'=>'Full source code. Tidak ada enkripsi IonCube. Free install + setup. 1 bulan support gratis. Bisa dikustomisasi sesuai kebutuhan.'],
        ];
        @endphp
        @foreach($faqs as $i => $f)
        <div class="faq-item accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button {{ $i > 0 ? 'collapsed' : '' }}" data-bs-toggle="collapse" data-bs-target="#faq{{ $i }}">
                    {{ $f['q'] }}
                </button>
            </h2>
            <div id="faq{{ $i }}" class="accordion-collapse collapse {{ $i === 0 ? 'show' : '' }}" data-bs-parent="#faq">
                <div class="accordion-body text-muted">{{ $f['a'] }}</div>
            </div>
        </div>
        @endforeach
    </div>
</section>

{{-- CTA Bottom --}}
<section class="container my-5">
    <div class="text-center p-5 rounded-4 text-white" style="background: linear-gradient(135deg, #1e1b4b, #4338ca);">
        <h2 class="fw-bold mb-3">Siap Miliki Marketplace Sendiri?</h2>
        <p class="mb-4 opacity-75" style="max-width:540px;margin:0 auto 24px;">Source code marketplace multi vendor siap pakai. Mulai bisnis marketplace Anda hari ini.</p>
        <a href="https://wa.me/{{ $wa }}" target="_blank" class="btn btn-wa btn-lg">
            <i class="fab fa-whatsapp me-2"></i>Hubungi {{ $wa }}
        </a>
    </div>
</section>

<footer>
    <div class="container text-center">
        <p class="small mb-0">&copy; {{ date('Y') }} {{ $appName }}. Source Code Marketplace Multi Vendor Indonesia.</p>
    </div>
</footer>

<a href="https://wa.me/{{ $wa }}" target="_blank" style="position:fixed;bottom:24px;right:24px;z-index:999;width:60px;height:60px;background:#25D366;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(37,211,102,.4);">
    <i class="fab fa-whatsapp text-white" style="font-size:28px;"></i>
</a>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
