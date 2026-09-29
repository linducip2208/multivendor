@extends('layouts.storefront')

@section('content')
    @php
        $position = 0;
    @endphp

    <div class="sf-container">
        <nav aria-label="Breadcrumb" class="sf-breadcrumb">
            <a href="{{ route('home') }}">Beranda</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <span aria-current="page">Papan peringkat</span>
        </nav>
    </div>

    <section class="sf-section" aria-labelledby="sf-leaderboard-title">
        <div class="sf-container">
            <div class="sf-section-head">
                <div>
                    <h1 class="sf-section-head__title" id="sf-leaderboard-title">Papan Peringkat</h1>
                    <p class="sf-muted sf-small sf-mt-0" style="max-width:60ch">
                        Dua puluh pembeli dengan total belanja tertinggi.
                    </p>
                </div>
                <a href="{{ route('loyalty.index') }}" class="sf-section-head__link">
                    <x-storefront.icon name="coins" :size="16" /> Poin saya
                </a>
            </div>

            @if ($top->isNotEmpty())
                <div class="sf-tablewrap">
                    <table class="sf-table">
                        <caption class="sf-sr-only">Peringkat 20 pembeli dengan total belanja tertinggi</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="sf-table__num">#</th>
                                <th scope="col">Pembeli</th>
                                <th scope="col">Lencana</th>
                                <th scope="col" class="sf-table__num">Total belanja</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($top as $row)
                                @php $position++; @endphp
                                <tr>
                                    <th scope="row" class="sf-table__num sf-bold">
                                        {{ \App\Support\Currency::number($position) }}
                                    </th>
                                    <td>
                                        <span class="sf-row" style="gap:10px">
                                            <span class="sf-avatar" style="width:34px;height:34px;font-size:.8rem" aria-hidden="true">
                                                {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($row->name, 0, 1)) }}
                                            </span>
                                            <span class="sf-clamp-2">{{ $row->name }}</span>
                                        </span>
                                    </td>
                                    <td>
                                        @php
                                            $badge = $badges->firstWhere('customer_id', $row->id);
                                        @endphp
                                        @if ($badge)
                                            <span class="sf-badge sf-badge--brand">{{ \Illuminate\Support\Str::headline((string) $badge->badge) }}</span>
                                            @if ($badge->tier)
                                                <span class="sf-badge sf-badge--neutral">{{ \Illuminate\Support\Str::headline((string) $badge->tier) }}</span>
                                            @endif
                                        @else
                                            <span class="sf-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="sf-table__num sf-bold">
                                        {{ \App\Support\Currency::format($row->total_spent ?? 0) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-storefront.empty
                    title="Peringkat belum tersedia"
                    text="Papan peringkat muncul setelah ada pelanggan yang menyelesaikan pembelian."
                    :href="route('products.index')"
                    label="Mulai belanja"
                    icon="award"
                />
            @endif
        </div>
    </section>
@endsection
