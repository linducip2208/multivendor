@extends('layouts.storefront')

@section('content')
    @php
        $points = (int) ($lp->points ?? 0);
        $minimumRedeem = 100;
        $canRedeem = $points >= $minimumRedeem;
    @endphp

    <div class="sf-container">
        <nav aria-label="Breadcrumb" class="sf-breadcrumb">
            <a href="{{ route('home') }}">Beranda</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <span aria-current="page">Poin Loyalitas</span>
        </nav>
    </div>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-loyalty-title">
        <div class="sf-container" style="max-width:820px">
            <h1 class="sf-section-head__title" id="sf-loyalty-title">Poin Loyalitas</h1>

            <div class="sf-cartlayout">
                <div class="sf-stack" style="gap:20px">
                    <section class="sf-card" aria-labelledby="sf-loyalty-balance">
                        <div class="sf-card__body">
                            <h2 class="sf-footer__title" id="sf-loyalty-balance">Saldo poin</h2>
                            <p class="sf-mb-0" style="font-size:2.6rem;font-weight:800;line-height:1;color:var(--sf-brand)">
                                {{ \App\Support\Currency::number($points) }}
                            </p>
                            <p class="sf-small sf-muted sf-mb-0">poin tersedia</p>

                            <hr class="sf-divider">

                            @if ($canRedeem)
                                <form method="POST" action="{{ route('loyalty.redeem') }}" class="sf-row sf-row--wrap" style="gap:10px" novalidate>
                                    @csrf
                                    <div class="sf-field" style="flex:1 1 150px;min-width:0">
                                        <label class="sf-label" for="sf-redeem-points">Jumlah poin</label>
                                        <input class="sf-input" id="sf-redeem-points" type="number" name="points" required
                                               min="{{ $minimumRedeem }}" max="{{ $points }}" step="1" inputmode="numeric"
                                               value="{{ $minimumRedeem }}"
                                               @error('points') aria-invalid="true" aria-describedby="sf-redeem-points-error" @enderror>
                                        @error('points')
                                            <span class="sf-error" id="sf-redeem-points-error">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div style="align-self:flex-end">
                                        <button type="submit" class="sf-btn sf-btn--primary">
                                            <x-storefront.icon name="coins" :size="16" /> Tukar ke dompet
                                        </button>
                                    </div>
                                </form>
                                <p class="sf-hint sf-mb-0">Poin dapat ditukar ke dompet digital dengan rasio 100 poin = {{ \App\Support\Currency::format(100) }}.</p>
                            @else
                                <x-storefront.alert type="info" title="Belum cukup poin">
                                    Minimal {{ \App\Support\Currency::number($minimumRedeem) }} poin diperlukan untuk ditukar ke dompet digital.
                                    Kumpulkan {{ \App\Support\Currency::number(max(0, $minimumRedeem - $points)) }} poin lagi.
                                </x-storefront.alert>
                            @endif
                        </div>
                    </section>

                    <section class="sf-card" aria-labelledby="sf-loyalty-history">
                        <div class="sf-card__body">
                            <h2 class="sf-footer__title" id="sf-loyalty-history">Riwayat poin</h2>

                            @if ($transactions->total() > 0)
                                <div class="sf-tablewrap" style="margin-top:12px">
                                    <table class="sf-table">
                                        <caption class="sf-sr-only">Riwayat transaksi poin loyalitas</caption>
                                        <thead>
                                            <tr>
                                                <th scope="col">Tanggal</th>
                                                <th scope="col">Keterangan</th>
                                                <th scope="col" class="sf-table__num">Poin</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($transactions as $transaction)
                                                <tr>
                                                    <th scope="row" style="font-weight:500">
                                                        <time datetime="{{ $transaction->created_at?->toAtomString() }}">
                                                            {{ $transaction->created_at?->translatedFormat('d M Y H:i') }}
                                                        </time>
                                                    </th>
                                                    <td>
                                                        {{ $transaction->description ?: \Illuminate\Support\Str::headline((string) $transaction->type) }}
                                                    </td>
                                                    <td class="sf-table__num">
                                                        <span class="sf-badge {{ $transaction->type === 'earn' ? 'sf-badge--success' : 'sf-badge--neutral' }}">
                                                            {{ $transaction->type === 'earn' ? '+' : '-' }}{{ \App\Support\Currency::number($transaction->points) }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <x-storefront.pagination :paginator="$transactions" />
                            @else
                                <x-storefront.empty
                                    title="Belum ada aktivitas poin"
                                    text="Poin bertambah dari pembelian yang selesai, referral, dan aktivitas lain yang memenuhi syarat."
                                    :href="route('products.index')"
                                    label="Mulai belanja"
                                    icon="coins"
                                />
                            @endif
                        </div>
                    </section>
                </div>

                <aside class="sf-stack" style="gap:16px">
                    <section class="sf-panel" aria-labelledby="sf-loyalty-referral">
                        <h2 class="sf-footer__title" id="sf-loyalty-referral">Kode referral</h2>
                        @if (auth()->user()->referral_code)
                            <p class="sf-mb-0">
                                <code style="font-size:1.15rem;font-weight:700;letter-spacing:.06em">{{ auth()->user()->referral_code }}</code>
                            </p>
                            <button type="button" class="sf-btn sf-btn--ghost sf-btn--sm" style="margin-top:12px"
                                    data-sf-copy="{{ auth()->user()->referral_code }}" aria-label="Salin kode referral">
                                <x-storefront.icon name="copy" :size="15" /> Salin kode
                            </button>
                        @else
                            <p class="sf-small sf-muted sf-mb-0">Kode referral belum tersedia untuk akun Anda.</p>
                        @endif
                    </section>

                    <section class="sf-panel" aria-labelledby="sf-loyalty-tiers">
                        <h2 class="sf-footer__title" id="sf-loyalty-tiers">Cara mendapatkan poin</h2>
                        <ul class="sf-prose sf-small sf-muted" style="padding-inline-start:18px">
                            <li>Poin bertambah setiap pesanan selesai dan dibayar.</li>
                            <li>Teman yang mendaftar memakai kode referral Anda ikut memberi poin.</li>
                            <li>Poin bertambah dari menulis ulasan produk yang telah diterima.</li>
                            <li>Poin tidak berlaku lintas akun dan tidak dapat dipindahtangankan.</li>
                        </ul>
                    </section>
                </aside>
            </div>
        </div>
    </section>
@endsection
