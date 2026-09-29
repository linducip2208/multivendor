@extends('layouts.storefront')

@section('content')
    @php
        $user = auth()->user();
        $stats = $stats ?? [];
        $recentOrders = collect($recentOrders ?? []);
        $recommended = collect($recommended ?? []);
        $notifications = collect($notifications ?? []);
        $wallet = $wallet ?? null;
        $loyaltyPoints = (int) ($loyaltyPoints ?? 0);
        $wishlistCount = (int) ($wishlistCount ?? 0);

        $statCards = collect($stats)->map(fn ($stat) => [
            'label' => (string) ($stat['label'] ?? ''),
            'value' => (string) ($stat['value'] ?? '0'),
            'icon' => (string) ($stat['icon'] ?? 'box'),
            'href' => $stat['href'] ?? null,
        ])->filter(fn ($stat) => $stat['label'] !== '')->values();

        if ($statCards->isEmpty()) {
            $statCards = collect([
                ['label' => 'Pesanan', 'value' => (string) $recentOrders->count(), 'icon' => 'package', 'href' => route('orders.index')],
                ['label' => 'Favorit', 'value' => (string) $wishlistCount, 'icon' => 'heart', 'href' => route('wishlist.index')],
                ['label' => 'Poin loyalitas', 'value' => \App\Support\Currency::number($loyaltyPoints), 'icon' => 'coins', 'href' => route('loyalty.index')],
            ]);
        }
    @endphp

    <div class="sf-container">
        <nav aria-label="Breadcrumb" class="sf-breadcrumb">
            <a href="{{ route('home') }}">Beranda</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <span aria-current="page">Dasbor</span>
        </nav>
    </div>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-dashboard-title">
        <div class="sf-container">
            <div class="sf-row sf-row--between sf-row--wrap" style="gap:12px;margin-bottom:22px">
                <div style="min-width:0">
                    <h1 class="sf-section-head__title" id="sf-dashboard-title">
                        Halo, {{ \Illuminate\Support\Str::before($user->name ?? 'Pelanggan', ' ') }}
                    </h1>
                    <p class="sf-muted sf-small sf-mb-0">Ringkasan aktivitas belanja Anda di satu tempat.</p>
                </div>
                <a href="{{ route('products.index') }}" class="sf-btn sf-btn--primary">
                    <x-storefront.icon name="cart" :size="16" /> Mulai belanja
                </a>
            </div>

            <div class="sf-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
                @foreach ($statCards as $stat)
                    <div class="sf-panel sf-stat">
                        @if ($stat['href'])
                            <a href="{{ $stat['href'] }}" class="sf-row" style="gap:12px" aria-label="{{ $stat['label'] }}">
                        @else
                            <span class="sf-row" style="gap:12px">
                        @endif
                            <span class="sf-trust__icon" aria-hidden="true">
                                <x-storefront.icon :name="$stat['icon']" :size="19" />
                            </span>
                            <span style="min-width:0">
                                <span class="sf-stat__value" style="display:block">{{ $stat['value'] }}</span>
                                <span class="sf-stat__label">{{ $stat['label'] }}</span>
                            </span>
                        @if ($stat['href'])
                            </a>
                        @else
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="sf-account">
                <aside>
                    <x-storefront.account-nav current="account.dashboard" />
                </aside>

                <div class="sf-stack" style="gap:20px">
                    <section class="sf-card" aria-labelledby="sf-dashboard-orders">
                        <div class="sf-card__body">
                            <div class="sf-row sf-row--between sf-row--wrap" style="gap:10px">
                                <h2 class="sf-footer__title" id="sf-dashboard-orders">Pesanan terbaru</h2>
                                <a href="{{ route('orders.index') }}" class="sf-small">Lihat semua</a>
                            </div>

                            @if ($recentOrders->isNotEmpty())
                                <div class="sf-tablewrap" style="margin-top:12px">
                                    <table class="sf-table">
                                        <caption class="sf-sr-only">Pesanan terbaru</caption>
                                        <thead>
                                            <tr>
                                                <th scope="col">Nomor</th>
                                                <th scope="col">Tanggal</th>
                                                <th scope="col">Status</th>
                                                <th scope="col" class="sf-table__num">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($recentOrders as $order)
                                                <tr>
                                                    <th scope="row" style="font-weight:500">
                                                        <a href="{{ route('orders.show', $order) }}">{{ $order->order_number }}</a>
                                                    </th>
                                                    <td>
                                                        <time datetime="{{ $order->created_at?->toAtomString() }}">
                                                            {{ $order->created_at?->translatedFormat('d M Y') }}
                                                        </time>
                                                    </td>
                                                    <td>
                                                        <span class="sf-badge sf-badge--brand">
                                                            {{ \App\Enums\OrderStatus::fromStored($order->order_status)->label() }}
                                                        </span>
                                                    </td>
                                                    <td class="sf-table__num">{{ \App\Support\Currency::format($order->total) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <x-storefront.empty
                                    title="Belum ada pesanan"
                                    text="Pesanan Anda akan tampil di sini setelah checkout pertama."
                                    :href="route('products.index')"
                                    label="Mulai belanja"
                                    icon="package"
                                />
                            @endif
                        </div>
                    </section>

                    <section class="sf-card" aria-labelledby="sf-dashboard-wallet">
                        <div class="sf-card__body">
                            <div class="sf-row sf-row--between sf-row--wrap" style="gap:10px">
                                <h2 class="sf-footer__title" id="sf-dashboard-wallet">Dompet digital</h2>
                                <a href="{{ route('account.wallet') }}" class="sf-small">Detail dompet</a>
                            </div>
                            <p class="sf-mb-0" style="font-size:1.6rem;font-weight:800">
                                {{ \App\Support\Currency::format($wallet?->balance ?? 0) }}
                            </p>
                        </div>
                    </section>

                    <section class="sf-card" aria-labelledby="sf-dashboard-notifications">
                        <div class="sf-card__body">
                            <div class="sf-row sf-row--between sf-row--wrap" style="gap:10px">
                                <h2 class="sf-footer__title" id="sf-dashboard-notifications">Notifikasi terbaru</h2>
                                <a href="{{ route('account.notifications') }}" class="sf-small">Lihat semua</a>
                            </div>

                            @if ($notifications->isNotEmpty())
                                <div class="sf-stack" style="gap:10px;margin-top:12px">
                                    @foreach ($notifications->take(4) as $notification)
                                        <div class="sf-row" style="gap:10px;align-items:flex-start">
                                            <span style="width:8px;height:8px;border-radius:50%;background:var(--sf-brand);flex-shrink:0;margin-top:7px" aria-hidden="true"></span>
                                            <span style="min-width:0">
                                                <span class="sf-bold" style="display:block">{{ $notification->title ?? '' }}</span>
                                                <span class="sf-small sf-muted">{{ $notification->body ?? '' }}</span>
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="sf-small sf-muted sf-mb-0">Belum ada notifikasi.</p>
                            @endif
                        </div>
                    </section>

                    @if ($recommended->isNotEmpty())
                        <section aria-labelledby="sf-dashboard-recommended">
                            <div class="sf-row sf-row--between sf-row--wrap" style="gap:10px;margin-bottom:14px">
                                <h2 class="sf-mb-0" id="sf-dashboard-recommended">Direkomendasikan untuk Anda</h2>
                                <a href="{{ route('products.index') }}" class="sf-small">Semua produk</a>
                            </div>
                            <div class="sf-products">
                                @foreach ($recommended as $product)
                                    <x-storefront.product-card :product="$product" />
                                @endforeach
                            </div>
                        </section>
                    @endif

                    @if (! empty($analytics))
                        <section class="sf-card" aria-labelledby="sf-dashboard-insight">
                            <div class="sf-card__body">
                                <h2 class="sf-footer__title" id="sf-dashboard-insight">Ringkasan belanja saya</h2>
                                <p class="sf-small sf-muted sf-mb-0">
                                    Tier loyalitas: <strong>{{ $analytics['tier']['label'] ?? '-' }}</strong>
                                    @if (! empty($analytics['rfm']))
                                        &middot; Segmen: <strong>{{ $analytics['rfm']['segment'] ?? '-' }}</strong>
                                        &middot; Skor RFM {{ $analytics['rfm']['score'] ?? '-' }}
                                    @endif
                                </p>
                                @if (! empty($analytics['funnel']))
                                    <p class="sf-small sf-muted sf-mb-0" style="margin-top:6px">
                                        Funnel: {{ collect($analytics['funnel'])->map(fn ($s) => $s['stage'].' '.$s['count'])->implode(' → ') }}
                                    </p>
                                @endif
                            </div>
                        </section>
                    @endif

                    @if (! empty($wishlistCollections))
                        <section class="sf-card" aria-labelledby="sf-dashboard-collections">
                            <div class="sf-card__body">
                                <h2 class="sf-footer__title" id="sf-dashboard-collections">Koleksi wishlist saya</h2>
                                <ul class="sf-stack sf-small" style="gap:6px;margin-top:10px;list-style:none;padding:0">
                                    @foreach (collect($wishlistCollections)->take(5) as $folder)
                                        <li><strong>{{ $folder['label'] }}</strong> &middot; {{ \App\Support\Currency::number($folder['count']) }} produk</li>
                                    @endforeach
                                </ul>
                            </div>
                        </section>
                    @endif

                    @php
                        try { $misiPanel = app(\App\Http\Controllers\Storefront\AccountController::class)->misiHarianData(); }
                        catch (\Throwable $e) { $misiPanel = ['missions' => [], 'streak' => 0, 'points' => 0]; }
                        try { $koleksiPanel = app(\App\Http\Controllers\Storefront\AccountController::class)->koleksiBerbagiData(); }
                        catch (\Throwable $e) { $koleksiPanel = []; }
                        try { $afiliasiPanel = app(\App\Http\Controllers\Storefront\AccountController::class)->dasborAfiliasiData(); }
                        catch (\Throwable $e) { $afiliasiPanel = ['affiliate' => null, 'leaderboard' => []]; }
                    @endphp

                    @if (! empty($misiPanel['missions']))
                        <section class="sf-card" aria-labelledby="sf-dashboard-misi">
                            <div class="sf-card__body">
                                <div class="sf-row sf-row--between sf-row--wrap" style="gap:10px">
                                    <h2 class="sf-footer__title" id="sf-dashboard-misi">Misi harian</h2>
                                    <span class="sf-badge sf-badge--warning">Streak {{ (int) ($misiPanel['streak'] ?? 0) }} hari</span>
                                </div>
                                <ul class="sf-stack sf-small" style="gap:8px;margin-top:12px;list-style:none;padding:0">
                                    @foreach ($misiPanel['missions'] as $misi)
                                        <li class="sf-row sf-row--between" style="gap:10px">
                                            <span style="min-width:0">
                                                <strong>{{ $misi['label'] }}</strong>
                                                <span class="sf-muted" style="display:block">{{ $misi['deskripsi'] }}</span>
                                            </span>
                                            <span class="sf-nowrap">
                                                @if ($misi['diklaim'])
                                                    <span class="sf-badge sf-badge--success">Diklaim</span>
                                                @elseif ($misi['selesai'])
                                                    <span class="sf-badge sf-badge--brand">+{{ $misi['poin'] }} poin</span>
                                                @else
                                                    <span class="sf-badge">{{ $misi['progress'] }}/{{ $misi['target'] }}</span>
                                                @endif
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                                <p class="sf-small sf-muted sf-mb-0" style="margin-top:8px">
                                    Klaim hadiah misi lewat halaman loyalitas atau API. Check-in setiap hari menaikkan bonus streak.
                                </p>
                                <a href="{{ route('loyalty.index') }}" class="sf-btn sf-btn--outline sf-btn--sm" style="margin-top:8px">
                                    <x-storefront.icon name="coins" :size="15" /> Klaim di halaman loyalitas
                                </a>
                            </div>
                        </section>
                    @endif

                    @if (! empty($koleksiPanel))
                        <section class="sf-card" aria-labelledby="sf-dashboard-share">
                            <div class="sf-card__body">
                                <h2 class="sf-footer__title" id="sf-dashboard-share">Bagikan koleksi wishlist</h2>
                                <ul class="sf-stack sf-small" style="gap:8px;margin-top:10px;list-style:none;padding:0">
                                    @foreach (collect($koleksiPanel)->take(5) as $folder)
                                        <li class="sf-row sf-row--between" style="gap:10px">
                                            <span style="min-width:0">
                                                <strong>{{ $folder['label'] }}</strong>
                                                &middot; {{ \App\Support\Currency::number($folder['count']) }} produk
                                            </span>
                                            <button type="button" class="sf-btn sf-btn--outline sf-btn--sm"
                                                onclick="navigator.clipboard && navigator.clipboard.writeText('{{ $folder['share_url'] }}');this.textContent='Tautan disalin!'">
                                                Salin tautan
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </section>
                    @endif

                    @if (! empty($afiliasiPanel['affiliate']))
                        <section class="sf-card" aria-labelledby="sf-dashboard-afiliasi">
                            <div class="sf-card__body">
                                <h2 class="sf-footer__title" id="sf-dashboard-afiliasi">Afiliasi saya</h2>
                                <p class="sf-small sf-muted">
                                    Kode <strong>{{ $afiliasiPanel['affiliate']['code'] }}</strong> &middot;
                                    {{ \App\Support\Currency::number($afiliasiPanel['affiliate']['clicks']) }} klik &middot;
                                    {{ \App\Support\Currency::number($afiliasiPanel['affiliate']['total_orders']) }} pesanan &middot;
                                    Komisi {{ \App\Support\Currency::format($afiliasiPanel['affiliate']['total_commission']) }}
                                </p>
                                <button type="button" class="sf-btn sf-btn--outline sf-btn--sm"
                                    onclick="navigator.clipboard && navigator.clipboard.writeText('{{ $afiliasiPanel['affiliate']['link'] }}');this.textContent='Tautan disalin!'">
                                    Salin tautan afiliasi
                                </button>
                            </div>
                        </section>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
