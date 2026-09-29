@extends('layouts.storefront')

@section('content')
    @php
        $wallet = $wallet ?? auth()->user()->wallet;
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
            <a href="{{ route('account.dashboard') }}">Dashboard</a>
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
                                Saldo tertahan {{ \App\Support\Currency::format($wallet->pending_balance) }}
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
                                Poin loyalitas dapat ditukar menjadi saldo dompet digital.
                            </p>
                            <a href="{{ route('loyalty.index') }}" class="sf-btn sf-btn--outline sf-btn--sm">
                                <x-storefront.icon name="coins" :size="15" /> Lihat poin saya
                            </a>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </section>
@endsection
