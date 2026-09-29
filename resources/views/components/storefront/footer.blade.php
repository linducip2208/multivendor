@php
    use App\Models\SystemSetting;

    $appName = $whitelabel['appName'] ?? config('app.name');
    $supportPhone = SystemSetting::get('contact_phone');
    $supportEmail = SystemSetting::get('contact_email');
    $whatsapp = SystemSetting::get('whatsapp_number');
    $whatsappDigits = preg_replace('/\D+/', '', (string) $whatsapp) ?: '';
    $whatsappLink = $whatsappDigits === '' ? null : str_replace(
        '{phone}',
        $whatsappDigits,
        (string) (SystemSetting::get('whatsapp_link_template') ?: 'https://api.whatsapp.com/send?phone={phone}')
    );
    $socials = array_filter([
        'Facebook' => SystemSetting::get('facebook_url'),
        'Instagram' => SystemSetting::get('instagram_url'),
        'TikTok' => SystemSetting::get('tiktok_url'),
        'YouTube' => SystemSetting::get('youtube_url'),
    ]);
    $paymentLabels = \App\Models\Provider::ofType('payment')->active()->orderBy('is_default', 'desc')->pluck('name')->take(5);
@endphp

<footer class="sf-footer">
    <div class="sf-container">
        {{-- Trust row --}}
        <div class="sf-footer__trust">
            <div class="sf-trust">
                <span class="sf-trust__icon"><x-storefront.icon name="shield-check" :size="20" /></span>
                <span>
                    <span class="sf-trust__title">Pembayaran Aman</span>
                    <span class="sf-trust__text">Transaksi terenkripsi dan diproses lewat payment gateway resmi.</span>
                </span>
            </div>
            <div class="sf-trust">
                <span class="sf-trust__icon"><x-storefront.icon name="refresh" :size="20" /></span>
                <span>
                    <span class="sf-trust__title">Retur 7 Hari</span>
                    <span class="sf-trust__text">Ajukan retur langsung dari halaman pesanan sesuai kebijakan toko.</span>
                </span>
            </div>
            <div class="sf-trust">
                <span class="sf-trust__icon"><x-storefront.icon name="truck" :size="20" /></span>
                <span>
                    <span class="sf-trust__title">Pengiriman Door-to-Door</span>
                    <span class="sf-trust__text">Cek ongkos kirim dan estimasi tiba sebelum membayar.</span>
                </span>
            </div>
            <div class="sf-trust">
                <span class="sf-trust__icon"><x-storefront.icon name="headset" :size="20" /></span>
                <span>
                    <span class="sf-trust__title">Bantuan</span>
                    <span class="sf-trust__text">
                        @if ($supportPhone) Tiket & chat 24/7 — {{ $supportPhone }} @else Buka tiket dukungan dari akun Anda. @endif
                    </span>
                </span>
            </div>
        </div>

        {{-- Link columns --}}
        <div class="sf-footer__main">
            <div>
                <a href="{{ route('home') }}" class="sf-logo" style="margin-bottom:12px">
                    @if ($whitelabel['logo'] ?? null)
                        <img src="{{ $whitelabel['logo'] }}" alt="{{ $appName }}" width="140" height="36">
                    @else
                        <span class="sf-logo__mark"><x-storefront.icon name="store" :size="18" /></span>
                        <span>{{ $appName }}</span>
                    @endif
                </a>
                <p class="sf-small sf-muted" style="max-width:34ch">
                    {{ SystemSetting::get('storefront_tagline', 'Marketplace multi-vendor untuk menemukan produk dari banyak toko tepercaya.') }}
                </p>

                @if ($whatsappLink)
                    <a href="{{ $whatsappLink }}" class="sf-btn sf-btn--outline sf-btn--sm" target="_blank" rel="noopener noreferrer">
                        <x-storefront.icon name="phone" :size="15" /> Chat WhatsApp
                    </a>
                @endif

                @if ($socials)
                    <div class="sf-row sf-mt-md" style="gap:8px">
                        @foreach ($socials as $label => $url)
                            <a href="{{ $url }}" class="sf-iconbtn" target="_blank" rel="noopener noreferrer" aria-label="{{ $label }}"><x-storefront.icon name="external" :size="16" /></a>
                        @endforeach
                    </div>
                @endif
            </div>

            <div>
                <h4 class="sf-footer__title">Belanja</h4>
                <ul class="sf-footer__list">
                    <li><a href="{{ route('products.index') }}">Semua Produk</a></li>
                    <li><a href="{{ route('stores.index') }}">Semua Toko</a></li>
                    <li><a href="{{ route('deals') }}">Promo & Deals</a></li>
                    <li><a href="{{ route('flash-sale') }}">Flash Sale</a></li>
                    <li><a href="{{ route('best-sellers') }}">Produk Terlaris</a></li>
                    <li><a href="{{ route('new-arrivals') }}">Produk Terbaru</a></li>
                </ul>
            </div>

            <div>
                <h4 class="sf-footer__title">Bantuan</h4>
                <ul class="sf-footer__list">
                    <li><a href="{{ route('track-order') }}">Lacak Pesanan</a></li>
                    <li><a href="{{ route('tickets.index') }}">Pusat Bantuan</a></li>
                    <li><a href="{{ route('page.faq') }}">FAQ</a></li>
                    <li><a href="{{ route('blog.index') }}">Blog & Ulasan</a></li>
                    <li><a href="{{ route('docs') }}">Panduan Belanja</a></li>
                </ul>
            </div>

            <div>
                <h4 class="sf-footer__title">Tentang</h4>
                <ul class="sf-footer__list">
                    <li><a href="{{ route('page.about') }}">Tentang Kami</a></li>
                    <li><a href="{{ route('page.terms') }}">Syarat & Ketentuan</a></li>
                    <li><a href="{{ route('page.privacy') }}">Kebijakan Privasi</a></li>
                    <li><a href="{{ route('page.return') }}">Kebijakan Retur</a></li>
                </ul>
            </div>

            <div>
                <h4 class="sf-footer__title">Jual di sini</h4>
                <ul class="sf-footer__list">
                    <li><a href="{{ route('vendor.login') }}">Masuk Penjual</a></li>
                    <li><a href="{{ route('vendor.register') }}">Daftar Jadi Penjual</a></li>
                    <li><a href="{{ route('page.seller') }}">Cara Bergabung</a></li>
                </ul>
                @if ($supportEmail)
                    <a href="mailto:{{ $supportEmail }}" class="sf-small">{{ $supportEmail }}</a>
                @endif
            </div>
        </div>

        <div class="sf-footer__bottom">
            <span>&copy; {{ date('Y') }} {{ $appName }}. Hak cipta dilindungi.</span>
            <div class="sf-footer__payments">
                <span class="sf-tiny sf-muted" style="align-self:center;margin-right:4px">Pembayaran:</span>
                @forelse ($paymentLabels as $label)
                    <span class="sf-footer__pay">{{ $label }}</span>
                @empty
                    <span class="sf-tiny sf-subtle">Belum ada payment gateway aktif</span>
                @endforelse
            </div>
        </div>
    </div>
</footer>
