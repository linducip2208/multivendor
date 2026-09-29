@extends('layouts.storefront')

@section('content')
    @php
        $wallet = $wallet ?? auth()->user()?->wallet;
        $transactions = $transactions ?? null;
        $paginator = method_exists($transactions ?? null, 'links') || $transactions instanceof \Illuminate\Contracts\Pagination\Paginator
            ? $transactions
            : collect($transactions ?? []);
        $transactionRows = $paginator instanceof \Illuminate\Contracts\Pagination\Paginator
            ? collect($paginator->items())
            : collect($paginator);
    @endphp

    <div class="sf-container">
        <nav aria-label="Breadcrumb" class="sf-breadcrumb">
            <a href="{{ route('home') }}">Beranda</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <a href="{{ route('account.dashboard') }}">Dasbor</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <span aria-current="page">Dompet</span>
        </nav>
    </div>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-wallet-title">
        <div class="sf-container">
            <h1 class="sf-section-head__title" id="sf-wallet-title">Dompet Digital</h1>

            <div class="sf-account">
                <aside>
                    <x-storefront.account-nav current="account.wallet" />
                </aside>

                <div class="sf-stack" style="gap:20px">
                    <section class="sf-panel" aria-labelledby="sf-wallet-balance">
                        <h2 class="sf-footer__title" id="sf-wallet-balance">Saldo tersedia</h2>
                        <p class="sf-mb-0" style="font-size:2.2rem;font-weight:800;line-height:1;color:var(--sf-brand)">
                            {{ \App\Support\Currency::format($wallet?->balance ?? 0) }}
                        </p>
                        @if ((float) ($wallet?->pending_balance ?? 0) > 0)
                            <p class="sf-small sf-muted sf-mb-0" style="margin-top:6px">
                                Saldo tertahan {{ \App\Support\Currency::format($wallet?->pending_balance ?? 0) }}
                            </p>
                        @endif
                    </section>

                    <section class="sf-card" aria-labelledby="sf-wallet-history">
                        <div class="sf-card__body">
                            <h2 class="sf-footer__title" id="sf-wallet-history">Mutasi dompet</h2>

                            @if ($transactionRows->isNotEmpty())
                                <div class="sf-tablewrap" style="margin-top:12px">
                                    <table class="sf-table">
                                        <caption class="sf-sr-only">Mutasi dompet digital</caption>
                                        <thead>
                                            <tr>
                                                <th scope="col">Tanggal</th>
                                                <th scope="col">Keterangan</th>
                                                <th scope="col">Status</th>
                                                <th scope="col" class="sf-table__num">Nominal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($transactionRows as $transaction)
                                                @php $isCredit = in_array((string) ($transaction->type ?? ''), ['credit', 'topup', 'in'], true); @endphp
                                                <tr>
                                                    <th scope="row" style="font-weight:500">
                                                        <time datetime="{{ $transaction->created_at?->toAtomString() }}">
                                                            {{ $transaction->created_at?->translatedFormat('d M Y H:i') }}
                                                        </time>
                                                    </th>
                                                    <td>{{ $transaction->description ?? \Illuminate\Support\Str::headline((string) ($transaction->type ?? '')) }}</td>
                                                    <td>
                                                        <span class="sf-badge {{ ($transaction->status ?? 'completed') === 'completed' ? 'sf-badge--success' : 'sf-badge--warning' }}">
                                                            {{ \Illuminate\Support\Str::headline((string) ($transaction->status ?? 'completed')) }}
                                                        </span>
                                                    </td>
                                                    <td class="sf-table__num sf-bold" style="{{ $isCredit ? 'color:var(--sf-success)' : '' }}">
                                                        {{ $isCredit ? '+' : '-' }}{{ \App\Support\Currency::format(abs((float) $transaction->amount)) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                @if ($paginator instanceof \Illuminate\Contracts\Pagination\Paginator)
                                    <x-storefront.pagination :paginator="$paginator" />
                                @endif
                            @else
                                <x-storefront.empty
                                    title="Belum ada mutasi"
                                    text="Perubahan saldo dompet akan tercatat di sini setelah transaksi pertama."
                                    :href="route('products.index')"
                                    label="Mulai belanja"
                                    icon="wallet"
                                />
                            @endif
                        </div>
                    </section>

                    <section class="sf-card" aria-labelledby="sf-wallet-loyalty">
                        <div class="sf-card__body">
                            <h2 class="sf-footer__title" id="sf-wallet-loyalty">Poin loyalitas</h2>
                            <p class="sf-small sf-muted">
                                Tier {{ $tier['label'] ?? 'Perunggu' }} &middot; {{ \App\Support\Currency::number($loyalty?->points ?? 0) }} poin
                                @if (! empty($tier['points_to_next']))
                                    &middot; {{ \App\Support\Currency::number($tier['points_to_next']) }} poin lagi ke {{ $tier['next_label'] }}
                                @endif
                            </p>
                            <p class="sf-small sf-muted sf-mb-0">
                                Poin kedaluwarsa {{ $expiring['expiry_months'] ?? 12 }} bulan setelah diperoleh.
                            </p>
                            <a href="{{ route('loyalty.index') }}" class="sf-btn sf-btn--outline sf-btn--sm">
                                <x-storefront.icon name="coins" :size="15" /> Lihat poin saya
                            </a>
                        </div>
                    </section>

                    @if (! empty($unified) && collect($unified)->isNotEmpty())
                        <section class="sf-card" aria-labelledby="sf-wallet-unified">
                            <div class="sf-card__body">
                                <h2 class="sf-footer__title" id="sf-wallet-unified">Riwayat terpadu dompet + poin</h2>
                                <ul class="sf-stack" style="gap:8px;margin-top:12px;list-style:none;padding:0">
                                    @foreach (collect($unified)->take(10) as $row)
                                        <li class="sf-row sf-row--between sf-small" style="gap:10px">
                                            <span style="min-width:0">
                                                <span class="sf-badge {{ ($row['kind'] ?? '') === 'loyalty' ? 'sf-badge--warning' : 'sf-badge--brand' }}">{{ $row['kind'] }}</span>
                                                {{ $row['label'] }}
                                            </span>
                                            <span class="sf-bold">{{ $row['dir'] }}{{ is_numeric($row['amount'] ?? 0) ? \App\Support\Currency::number($row['amount']) : '' }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </section>
                    @endif

                    @if (! empty($referral))
                        <section class="sf-card" aria-labelledby="sf-wallet-referral">
                            <div class="sf-card__body">
                                <h2 class="sf-footer__title" id="sf-wallet-referral">Referral saya</h2>
                                <p class="sf-small sf-muted">
                                    Kode <strong>{{ $referral['code'] ?? '-' }}</strong> &middot;
                                    {{ \App\Support\Currency::number($referral['count'] ?? 0) }} teman bergabung &middot;
                                    {{ \App\Support\Currency::number($referral['points_earned'] ?? 0) }} poin dari referral.
                                </p>
                                @if (! empty($leaderboard))
                                    <p class="sf-small sf-muted sf-mb-0">Papan peringkat: {{ collect($leaderboard)->take(3)->map(fn ($r) => $r['name'].' ('.$r['referrals'].')')->implode(', ') }}</p>
                                @endif
                            </div>
                        </section>
                    @endif

                    @php
                        try { $misiWallet = app(\App\Http\Controllers\Storefront\AccountController::class)->misiHarianData(); }
                        catch (\Throwable $e) { $misiWallet = ['missions' => [], 'streak' => 0, 'points' => 0]; }
                        try { $afiliasiWallet = app(\App\Http\Controllers\Storefront\AccountController::class)->dasborAfiliasiData(); }
                        catch (\Throwable $e) { $afiliasiWallet = ['affiliate' => null, 'leaderboard' => []]; }
                    @endphp

                    @if (! empty($misiWallet['missions']))
                        <section class="sf-card" aria-labelledby="sf-wallet-misi">
                            <div class="sf-card__body">
                                <div class="sf-row sf-row--between sf-row--wrap" style="gap:10px">
                                    <h2 class="sf-footer__title" id="sf-wallet-misi">Misi harian + check-in</h2>
                                    <span class="sf-badge sf-badge--warning">Streak {{ (int) ($misiWallet['streak'] ?? 0) }} hari</span>
                                </div>
                                <ul class="sf-stack sf-small" style="gap:8px;margin-top:12px;list-style:none;padding:0">
                                    @foreach ($misiWallet['missions'] as $misi)
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
                                <a href="{{ route('loyalty.index') }}" class="sf-btn sf-btn--outline sf-btn--sm" style="margin-top:8px">
                                    <x-storefront.icon name="coins" :size="15" /> Klaim hadiah misi
                                </a>
                            </div>
                        </section>
                    @endif

                    @if (! empty($afiliasiWallet['affiliate']))
                        <section class="sf-card" aria-labelledby="sf-wallet-afiliasi">
                            <div class="sf-card__body">
                                <h2 class="sf-footer__title" id="sf-wallet-afiliasi">Komisi afiliasi → dompet</h2>
                                <p class="sf-small sf-muted">
                                    Tautan <strong>{{ $afiliasiWallet['affiliate']['link'] }}</strong><br>
                                    {{ \App\Support\Currency::number($afiliasiWallet['affiliate']['clicks_30d']) }} klik 30 hari &middot;
                                    {{ \App\Support\Currency::number($afiliasiWallet['affiliate']['conversions']) }} konversi &middot;
                                    Komisi berjalan {{ \App\Support\Currency::format($afiliasiWallet['affiliate']['total_commission']) }}.
                                    Komisi otomatis masuk dompet saat pesanan memakai kode referral Anda.
                                </p>
                                @if (! empty($afiliasiWallet['leaderboard']))
                                    <p class="sf-small sf-muted sf-mb-0">
                                        Papan peringkat: {{ collect($afiliasiWallet['leaderboard'])->take(3)->map(fn ($r) => $r['name'].' ('.\App\Support\Currency::format($r['total_revenue']).')')->implode(', ') }}
                                    </p>
                                @endif
                            </div>
                        </section>
                    @endif

                    @php
                        try { $bagikan = app(\App\Http\Controllers\Storefront\AccountController::class)->dataBagikanReferral(); }
                        catch (\Throwable $e) { $bagikan = ['tautan' => null, 'kode' => null, 'pesan' => null, 'poster_svg' => null, 'qr_tersedia' => false]; }
                        try { $cashbackSaya = app(\App\Http\Controllers\Storefront\AccountController::class)->dataCashbackSaya(); }
                        catch (\Throwable $e) { $cashbackSaya = ['riwayat' => [], 'total_dompet' => 0.0, 'total_poin' => 0]; }
                    @endphp

                    @if (! empty($bagikan['tautan']))
                        <section class="sf-card" aria-labelledby="sf-wallet-bagikan">
                            <div class="sf-card__body">
                                <h2 class="sf-footer__title" id="sf-wallet-bagikan">Bagikan referral</h2>
                                <p class="sf-small sf-muted">
                                    Kode <strong>{{ $bagikan['kode'] }}</strong> &middot; tanpa QR (tautan + tombol salin + poster unduhan).
                                </p>
                                <div class="sf-row" style="gap:8px;flex-wrap:wrap">
                                    <input id="sf-referral-link" class="sf-input" type="text" readonly value="{{ $bagikan['tautan'] }}" aria-label="Tautan referral" style="flex:1;min-width:220px">
                                    <button type="button" class="sf-btn sf-btn--outline sf-btn--sm" data-copy-target="sf-referral-link">Salin tautan</button>
                                    <a class="sf-btn sf-btn--outline sf-btn--sm" target="_blank" rel="noopener" href="https://wa.me/?text={{ urlencode($bagikan['pesan'] ?? $bagikan['tautan']) }}">Bagikan WA</a>
                                    @if (! empty($bagikan['poster_svg']))
                                        <button type="button" class="sf-btn sf-btn--outline sf-btn--sm" id="sf-poster-unduh">Unduh poster</button>
                                    @endif
                                </div>
                                @if (! empty($bagikan['poster_svg']))
                                    <details style="margin-top:10px">
                                        <summary class="sf-small">Pratinjau poster</summary>
                                        <div id="sf-poster-svg" style="margin-top:8px;max-width:300px">{!! $bagikan['poster_svg'] !!}</div>
                                    </details>
                                    <script>
                                        (function () {
                                            var unduh = document.getElementById('sf-poster-unduh');
                                            if (unduh) unduh.addEventListener('click', function () {
                                                var svg = document.querySelector('#sf-poster-svg svg');
                                                if (! svg) return;
                                                var blob = new Blob([new XMLSerializer().serializeToString(svg)], { type: 'image/svg+xml' });
                                                var a = document.createElement('a');
                                                a.href = URL.createObjectURL(blob);
                                                a.download = 'poster-referral-{{ $bagikan['kode'] }}.svg';
                                                a.click();
                                                setTimeout(function () { URL.revokeObjectURL(a.href); }, 4000);
                                            });
                                            document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
                                                btn.addEventListener('click', function () {
                                                    var el = document.getElementById(btn.getAttribute('data-copy-target'));
                                                    if (! el) return;
                                                    (navigator.clipboard ? navigator.clipboard.writeText(el.value) : Promise.reject())
                                                        .then(function () { btn.textContent = 'Tersalin!'; })
                                                        .catch(function () { el.select(); document.execCommand('copy'); btn.textContent = 'Tersalin!'; });
                                                });
                                            });
                                        })();
                                    </script>
                                @endif
                            </div>
                        </section>
                    @endif

                    @if (! empty($cashbackSaya['riwayat']))
                        <section class="sf-card" aria-labelledby="sf-wallet-cashback">
                            <div class="sf-card__body">
                                <h2 class="sf-footer__title" id="sf-wallet-cashback">Cashback saya</h2>
                                <p class="sf-small sf-muted">
                                    Total {{ \App\Support\Currency::format($cashbackSaya['total_dompet'] ?? 0) }} ke dompet &middot;
                                    {{ \App\Support\Currency::number($cashbackSaya['total_poin'] ?? 0) }} poin cashback.
                                </p>
                                <ul class="sf-stack sf-small" style="gap:8px;margin-top:12px;list-style:none;padding:0">
                                    @foreach (collect($cashbackSaya['riwayat'])->take(10) as $row)
                                        <li class="sf-row sf-row--between" style="gap:10px">
                                            <span style="min-width:0">
                                                <span class="sf-badge {{ ($row['jenis'] ?? '') === 'poin' ? 'sf-badge--warning' : 'sf-badge--success' }}">{{ $row['jenis'] }}</span>
                                                {{ $row['label'] }} &middot; {{ $row['at'] }}
                                            </span>
                                            <span class="sf-bold">+{{ ($row['jenis'] ?? '') === 'poin' ? \App\Support\Currency::number($row['nominal']) : \App\Support\Currency::format($row['nominal']) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </section>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
