@extends('layouts.storefront')

@push('head')
    <style>
        .sf-landing { overflow: hidden; }
        .sf-landing__nav { border-bottom: 1px solid var(--sf-border); background: var(--sf-bg); }
        .sf-landing__hero {
            background: linear-gradient(140deg, var(--sf-brand) 0%, var(--sf-brand-700) 62%, var(--sf-brand-600) 100%);
            color: var(--sf-brand-contrast);
        }
        .sf-landing__hero h1 { color: inherit; }
        .sf-landing__hero p { color: inherit; opacity: .92; }
        .sf-landing__panel {
            border-radius: var(--sf-radius-lg);
            background: rgba(255, 255, 255, .1);
            border: 1px solid rgba(255, 255, 255, .18);
        }
        .sf-landing__tick { width: 18px; height: 18px; border-radius: 50%; background: rgba(255, 255, 255, .18); display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .sf-landing__price { display: grid; gap: 2px; }
    </style>
@endpush

@section('content')
    @php
        $brandName = $whitelabel['appName'] ?? config('app.name');
        $seo = is_array($seo ?? null) ? $seo : [];
        $metaTitle = $seo['title'] ?? $metaTitle ?? null;
        $metaDescription = $seo['description'] ?? $metaDescription ?? null;
        $canonicalUrl = $seo['canonical'] ?? $canonicalUrl ?? null;
        $productName = $seo['label'] ?? \App\Models\SystemSetting::get('pseo_product_name', $brandName);
        $headline = $seo['title'] ?? \App\Models\SystemSetting::get('pseo_headline', 'Source code marketplace multi-vendor siap dipakai');
        $tagline = $seo['description'] ?? \App\Models\SystemSetting::get(
            'pseo_tagline',
            'Kode sumber lengkap dengan multi-vendor, payment gateway, ongkos kirim, POS, loyalty, dan modul pSEO dalam satu paket instalasi.'
        );

        $whatsapp = trim((string) \App\Models\SystemSetting::get('whatsapp_number', ''));
        $linkTemplate = (string) \App\Models\SystemSetting::get('whatsapp_link_template', 'https://api.whatsapp.com/send?phone={phone}');
        $whatsappDigits = preg_replace('/\D+/', '', $whatsapp) ?: '';
        $whatsappLink = $whatsappDigits !== ''
            ? str_replace('{phone}', $whatsappDigits, $linkTemplate)
            : '';
        $contactEmail = trim((string) \App\Models\SystemSetting::get('contact_email', ''));

        $features = [
            ['icon' => 'store', 'title' => 'Multi-vendor end-to-end', 'text' => 'Pendaftaran vendor, halaman toko, katalog, komisi otomatis, dan pencairan saldo vendor'],
            ['icon' => 'wallet', 'title' => 'Payment gateway BYOK', 'text' => 'Dukungan banyak gateway dengan API key sendiri, tanpa biaya platform tambahan.'],
            ['icon' => 'truck', 'title' => 'Ongkos kirim real-time', 'text' => 'Kurir dan ongkos kirim langsung dihitung pada halaman checkout untuk setiap penjual.'],
            ['icon' => 'coins', 'title' => 'Loyalty & dompet digital', 'text' => 'Poin dari belanja dan referral, penukaran ke dompet, serta mutasi yang tercatat rapi.'],
            ['icon' => 'globe', 'title' => 'Programmatic SEO', 'text' => 'Pembuat halaman dan template pSEO berskala besar, lengkap dengan sitemap dan IndexNow.'],
            ['icon' => 'settings', 'title' => 'Panel white-label', 'text' => 'Ganti nama, warna, dan logo tanpa menyentuh kode. Satu lisensi untuk banyak instalasi.'],
        ];

        $modules = [
            ['icon' => 'box', 'label' => 'Katalog & varian', 'text' => 'Atribut, varian, stok multi-gudang, dan riwayat pergerakan stok.'],
            ['icon' => 'ticket', 'label' => 'Promo & kupon', 'text' => 'Kupon, flash sale, deal of the day, dan harga khusus per pelanggan.'],
            ['icon' => 'package', 'label' => 'POS retail', 'text' => 'Kasir untuk transaksi offline dengan sinkronisasi ke inventori.'],
            ['icon' => 'headset', 'label' => 'Bantuan & tiket', 'text' => 'Tiket dukungan, live chat, dan penilaian pengalaman pengiriman.'],
            ['icon' => 'layers', 'label' => 'Bundel & group buy', 'text' => 'Paket hemat dan pembelian bersama dengan progres peserta.'],
            ['icon' => 'external', 'label' => 'REST API', 'text' => 'API publik untuk aplikasi mobile, sinkronisasi stok, dan integrasi kustom.'],
        ];

        $plans = json_decode((string) \App\Models\SystemSetting::get('landing_plans', '[]'), true);
        $plans = collect(is_array($plans) ? $plans : [])
            ->filter(fn ($plan) => is_array($plan) && ($plan['name'] ?? '') !== '')
            ->map(fn (array $plan) => [
                'name' => (string) ($plan['name'] ?? ''),
                'price' => (string) ($plan['price'] ?? ''),
                'period' => (string) ($plan['period'] ?? ''),
                'description' => (string) ($plan['description'] ?? ''),
                'features' => collect($plan['features'] ?? [])->filter()->map(fn ($item) => (string) $item)->values()->all(),
                'featured' => (bool) ($plan['featured'] ?? false),
            ])
            ->values();

        $faqs = [
            ['q' => 'Apakah lisensinya berlaku untuk satu toko?', 'a' => 'Lisensi mengikat satu instalasi. Anda dapat memakai nama dan merek sendiri, dan menambah domain atau toko baru melalui panel white-label pada paket yang sama.'],
            ['q' => 'Apakah kode sumber dikirim utuh?', 'a' => 'Ya. Anda menerima repositori kode sumber lengkap beserta basis data awal, dokumentasi, dan panduan instalasi.'],
            ['q' => 'Apakah dapat dipasang di hosting sendiri?', 'a' => 'Aplikasi berjalan pada hosting milik Anda sendiri. Kebutuhan server dan ekstensi PHP dijelaskan pada dokumentasi instalasi.'],
            ['q' => 'Bagaimana pembaruan dilakukan?', 'a' => 'Pembaruan dikirim sesuai skema paket yang dipilih. Dokumentasi selalu menyertakan catatan perubahan agar migrasi tidak mengganggu data yang sudah ada.'],
            ['q' => 'Apakah bisa terhubung ke payment gateway sendiri?', 'a' => 'Bisa. Setiap payment gateway dan kurir dikonfigurasi melalui menu Integrasi, lengkap dengan preset dan API key milik Anda sendiri.'],
            ['q' => 'Apa saja dukungan teknis yang diberikan?', 'a' => 'Dukungan teknis diberikan sesuai paket yang dipilih, mencakup bantuan instalasi, kesalahan konfigurasi, dan panduan fitur.'],
        ];
    @endphp

    <div class="sf-landing">
        <nav class="sf-landing__nav" aria-label="Navigasi halaman produk">
            <div class="sf-container">
                <div class="sf-row sf-row--between" style="gap:16px;padding-block:14px">
                    <a href="{{ route('home') }}" class="sf-row" style="gap:9px;min-width:0">
                        @if (! empty($whitelabel['logo']))
                            <img src="{{ $whitelabel['logo'] }}" alt="{{ $brandName }}" width="34" height="34" style="width:34px;height:34px;object-fit:contain">
                        @else
                            <x-storefront.icon name="layers" :size="22" style="color:var(--sf-brand)" />
                        @endif
                        <span class="sf-bold sf-truncate" style="color:var(--sf-text)">{{ $productName }}</span>
                    </a>

                    <div class="sf-row sf-row--wrap sf-hide-mobile" style="gap:18px">
                        <a href="#fitur" class="sf-small">Fitur</a>
                        <a href="#harga" class="sf-small">Harga</a>
                        <a href="#faq" class="sf-small">FAQ</a>
                        <a href="#kontak" class="sf-small">Kontak</a>
                    </div>

                    <a href="#kontak" class="sf-btn sf-btn--primary sf-btn--sm">
                        <x-storefront.icon name="mail" :size="15" /> Tanya penjelasan
                    </a>
                </div>
            </div>
        </nav>

        <section class="sf-landing__hero" aria-labelledby="sf-landing-title">
            <div class="sf-container">
                <div class="sf-row sf-row--wrap" style="gap:32px;align-items:center;padding-block:clamp(40px,7vw,80px)">
                    <div style="flex:1 1 340px;min-width:0">
                        <p class="sf-hero__eyebrow" style="color:inherit;opacity:.85">Kode sumber &amp; lisensi</p>
                        <h1 class="sf-mb-0" id="sf-landing-title" style="font-size:clamp(2rem,1.4rem+3vw,3.4rem);line-height:1.05;letter-spacing:-.02em;max-width:18ch">
                            {{ $headline }}
                        </h1>
                        <p class="sf-mb-0" style="max-width:56ch;font-size:1.05rem;margin-top:16px">{{ $tagline }}</p>

                        <div class="sf-row sf-row--wrap" style="gap:10px;margin-top:26px">
                            <a href="#kontak" class="sf-btn sf-btn--lg" style="background:#fff;color:var(--sf-brand)">
                                <x-storefront.icon name="mail" :size="17" /> Minta Penawaran
                            </a>
                            <a href="{{ route('products.index') }}" class="sf-btn sf-btn--outline sf-btn--lg" style="color:#fff;border-color:rgba(255,255,255,.45)">
                                <x-storefront.icon name="eye" :size="17" /> Lihat Demo
                            </a>
                        </div>

                        <div class="sf-row sf-row--wrap" style="gap:18px;margin-top:24px">
                            @foreach (['Instalasi mandiri', 'Kode sumber penuh', 'Dukungan teknis'] as $promise)
                                <span class="sf-row" style="gap:8px;font-size:.9rem;opacity:.95">
                                    <span class="sf-landing__tick" aria-hidden="true">
                                        <x-storefront.icon name="check" :size="11" :stroke="2.5" />
                                    </span>
                                    {{ $promise }}
                                </span>
                            @endforeach
                        </div>
                    </div>

                    <div style="flex:1 1 320px;min-width:0">
                        <div class="sf-landing__panel sf-stack" style="gap:16px;padding:24px">
                            <p class="sf-mb-0" style="font-size:.95rem;opacity:.9">Cakupan modul dalam satu paket</p>
                            <div class="sf-grid" style="grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px">
                                @foreach ($modules as $module)
                                    <div class="sf-row" style="gap:9px;align-items:flex-start">
                                        <span class="sf-landing__tick" aria-hidden="true">
                                            <x-storefront.icon :name="$module['icon']" :size="12" :stroke="2" />
                                        </span>
                                        <span style="min-width:0">
                                            <span style="display:block;font-size:.88rem;font-weight:600">{{ $module['label'] }}</span>
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                            <p class="sf-tiny sf-mb-0" style="opacity:.78;overflow-wrap:anywhere">
                                Modul dijalankan pada server Anda sendiri, tidak ada biaya platform per transaksi.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="sf-container" style="margin-top:-18px;position:relative">
            <x-storefront.trust-row />
        </div>

        <section class="sf-section" id="fitur" aria-labelledby="sf-landing-features">
            <div class="sf-container">
                <div class="sf-section-head" style="text-align:center;justify-content:center">
                    <div>
                        <p class="sf-section-head__eyebrow">Fitur</p>
                        <h2 class="sf-section-head__title" id="sf-landing-features" style="font-size:clamp(1.5rem,1.2rem+1.4vw,2.2rem)">
                            Semua yang dibutuhkan untuk menjalankan marketplace
                        </h2>
                        <p class="sf-muted sf-small" style="max-width:64ch;margin-inline:auto">
                            Tidak perlu merangkai banyak paket. Kode sumber yang Anda terima sudah saling terhubung
                            dari katalog sampai laporan penjualan.
                        </p>
                    </div>
                </div>

                <div class="sf-grid" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr));margin-top:28px">
                    @foreach ($features as $feature)
                        <article class="sf-panel sf-stack" style="gap:12px">
                            <span class="sf-trust__icon" aria-hidden="true">
                                <x-storefront.icon :name="$feature['icon']" :size="20" />
                            </span>
                            <h3 class="sf-mb-0" style="font-size:1.02rem">{{ $feature['title'] }}</h3>
                            <p class="sf-small sf-muted sf-mb-0">{{ $feature['text'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="sf-section sf-section--subtle" aria-labelledby="sf-landing-modules">
            <div class="sf-container">
                <h2 class="sf-section-head__title" id="sf-landing-modules" style="font-size:clamp(1.3rem,1.1rem+1vw,1.8rem)">
                    Modul yang langsung aktif setelah instalasi
                </h2>
                <div class="sf-grid" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr));margin-top:20px">
                    @foreach ($modules as $module)
                        <article class="sf-panel">
                            <p class="sf-row" style="gap:10px;margin-bottom:6px">
                                <x-storefront.icon :name="$module['icon']" :size="18" style="color:var(--sf-brand);flex-shrink:0" />
                                <span class="sf-bold" style="color:var(--sf-text)">{{ $module['label'] }}</span>
                            </p>
                            <p class="sf-small sf-muted sf-mb-0">{{ $module['text'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="sf-section" id="harga" aria-labelledby="sf-landing-pricing">
            <div class="sf-container">
                <div class="sf-section-head" style="text-align:center;justify-content:center">
                    <div>
                        <p class="sf-section-head__eyebrow">Harga</p>
                        <h2 class="sf-section-head__title" id="sf-landing-pricing" style="font-size:clamp(1.5rem,1.2rem+1.4vw,2.2rem)">
                            Pilih paket sesuai kebutuhan
                        </h2>
                    </div>
                </div>

                @if ($plans->isNotEmpty())
                    <div class="sf-grid" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr));margin-top:28px">
                        @foreach ($plans as $plan)
                            <article @class(['sf-panel', 'sf-stack' => true, 'is-featured' => $plan['featured']]) style="gap:14px;position:relative">
                                @if ($plan['featured'])
                                    <span class="sf-badge sf-badge--brand" style="position:absolute;top:14px;right:14px">Rekomendasi</span>
                                @endif
                                <h3 class="sf-mb-0" style="font-size:1.05rem">{{ $plan['name'] }}</h3>
                                @if ($plan['description'])
                                    <p class="sf-small sf-muted sf-mb-0">{{ $plan['description'] }}</p>
                                @endif
                                <p class="sf-landing__price sf-mb-0">
                                    <span style="font-size:1.7rem;font-weight:800">{{ $plan['price'] }}</span>
                                    @if ($plan['period'])
                                        <span class="sf-small sf-muted">{{ $plan['period'] }}</span>
                                    @endif
                                </p>
                                @if ($plan['features'] !== [])
                                    <ul class="sf-prose sf-small sf-muted" style="margin:0;padding-inline-start:18px">
                                        @foreach ($plan['features'] as $line)
                                            <li>{{ $line }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                <a href="#kontak" class="sf-btn {{ $plan['featured'] ? 'sf-btn--primary' : 'sf-btn--outline' }} sf-btn--block">
                                    Pilih paket ini
                                </a>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="sf-panel" style="margin-top:28px">
                        <h3 class="sf-mb-0" style="font-size:1.05rem">Harga disesuaikan dengan cakupan</h3>
                        <p class="sf-small sf-muted">
                            Struktur harga mengikuti cakupan modul, jumlah domain, dan dukungan yang Anda butuhkan.
                            Sampaikan kebutuhan Anda melalui formulir di bawah ini untuk penawaran yang terperinci.
                        </p>
                        <a href="#kontak" class="sf-btn sf-btn--primary" style="margin-top:14px">
                            <x-storefront.icon name="mail" :size="16" /> Minta penawaran
                        </a>
                    </div>
                @endif
            </div>
        </section>

        <section class="sf-section sf-section--subtle" id="faq" aria-labelledby="sf-landing-faq">
            <div class="sf-container" style="max-width:840px">
                <p class="sf-section-head__eyebrow">FAQ</p>
                <h2 class="sf-section-head__title" id="sf-landing-faq" style="font-size:clamp(1.4rem,1.2rem+1vw,2rem)">
                    Pertanyaan yang sering diajukan
                </h2>

                <div class="sf-stack" style="gap:10px;margin-top:20px">
                    @foreach ($faqs as $index => $faq)
                        <details class="sf-panel" @if ($index === 0) open @endif>
                            <summary class="sf-bold" style="cursor:pointer;color:var(--sf-text)">{{ $faq['q'] }}</summary>
                            <p class="sf-small sf-muted sf-mb-0" style="margin-top:10px">{{ $faq['a'] }}</p>
                        </details>                    @endforeach
                </div>
            </div>
        </section>

        <section class="sf-section" id="kontak" aria-labelledby="sf-landing-contact">
            <div class="sf-container">
                <div class="sf-cartlayout">
                    <div>
                        <p class="sf-section-head__eyebrow">Kontak</p>
                        <h2 class="sf-section-head__title" id="sf-landing-contact" style="font-size:clamp(1.4rem,1.2rem+1vw,2rem)">
                            Ceritakan kebutuhan Anda
                        </h2>
                        <p class="sf-muted sf-small" style="max-width:52ch">
                            Isi formulir berikut dan tim kami akan menghubungi Anda dengan ringkasan paket serta
                            estimasi waktu implementasi.
                        </p>

                        @if ($whatsappLink !== '')
                            <a href="{{ $whatsappLink }}" class="sf-btn sf-btn--primary" style="margin-top:18px" rel="noopener">
                                <x-storefront.icon name="mail" :size="17" /> Hubungi via WhatsApp
                            </a>
                            <p class="sf-tiny sf-muted sf-mb-0" style="margin-top:8px">
                                Nomor tersimpan pada pengaturan sistem, bukan di dalam halaman.
                            </p>
                        @endif
                    </div>

                    <div class="sf-panel">
                        <form method="POST" action="{{ route('pseo.contact') }}" class="sf-stack" style="gap:16px" novalidate>
                            @csrf
                            <input type="hidden" name="source" value="{{ \Illuminate\Support\Str::slug($seo['keyword'] ?? $productName) }}">

                            <div class="sf-field">
                                <label class="sf-label" for="sf-contact-name">Nama <span class="sf-required">*</span></label>
                                <input class="sf-input" id="sf-contact-name" type="text" name="name" required
                                       autocomplete="name" value="{{ old('name') }}"
                                       @error('name') aria-invalid="true" aria-describedby="sf-contact-name-error" @enderror>
                                @error('name')
                                    <span class="sf-error" id="sf-contact-name-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="sf-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
                                <div class="sf-field">
                                    <label class="sf-label" for="sf-contact-email">Email <span class="sf-required">*</span></label>
                                    <input class="sf-input" id="sf-contact-email" type="email" name="email" required
                                           autocomplete="email" value="{{ old('email') }}"
                                           @error('email') aria-invalid="true" aria-describedby="sf-contact-email-error" @enderror>
                                    @error('email')
                                        <span class="sf-error" id="sf-contact-email-error">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="sf-field">
                                    <label class="sf-label" for="sf-contact-phone">Nomor telepon</label>
                                    <input class="sf-input" id="sf-contact-phone" type="tel" name="phone"
                                           autocomplete="tel" value="{{ old('phone') }}"
                                           @error('phone') aria-invalid="true" aria-describedby="sf-contact-phone-error" @enderror>
                                    @error('phone')
                                        <span class="sf-error" id="sf-contact-phone-error">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="sf-field">
                                <label class="sf-label" for="sf-contact-company">Nama perusahaan</label>
                                <input class="sf-input" id="sf-contact-company" type="text" name="company"
                                       autocomplete="organization" value="{{ old('company') }}">
                            </div>

                            <div class="sf-field">
                                <label class="sf-label" for="sf-contact-message">Kebutuhan Anda <span class="sf-required">*</span></label>
                                <textarea class="sf-textarea" id="sf-contact-message" name="message" rows="5" required
                                          maxlength="2000" placeholder="Contoh: butuh 3 domain, integrasi kurir, dan POS."
                                          @error('message') aria-invalid="true" aria-describedby="sf-contact-message-error" @enderror>{{ old('message') }}</textarea>
                                @error('message')
                                    <span class="sf-error" id="sf-contact-message-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <label class="sf-checkbox" for="sf-contact-consent">
                                <input type="checkbox" id="sf-contact-consent" name="consent" value="1" required
                                       @checked(old('consent')) @error('consent') aria-invalid="true" aria-describedby="sf-contact-consent-error" @enderror>
                                <span>Saya setuju dihubungi terkait permintaan ini.</span>
                            </label>
                            @error('consent')
                                <span class="sf-error" id="sf-contact-consent-error">{{ $message }}</span>
                            @enderror

                            <div class="sf-row sf-row--wrap" style="gap:8px">
                                <button type="submit" class="sf-btn sf-btn--primary sf-btn--lg">
                                    <x-storefront.icon name="mail" :size="17" /> Kirim permintaan
                                </button>
                                @if ($contactEmail !== '')
                                    <button type="button" class="sf-btn sf-btn--ghost" data-sf-copy="{{ $contactEmail }}"
                                            aria-label="Salin alamat email">
                                        <x-storefront.icon name="copy" :size="16" /> Salin email
                                    </button>
                                    <span class="sf-tiny sf-muted">{{ $contactEmail }}</span>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => $productName,
        'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Linux, Windows',
        'description' => \Illuminate\Support\Str::limit(strip_tags($tagline), 300),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn (array $faq) => [
            '@type' => 'Question',
            'name' => $faq['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['a']],
        ], $faqs),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endpush
