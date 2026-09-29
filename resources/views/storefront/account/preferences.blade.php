@extends('layouts.storefront')

@section('content')
    @php
        $preferences = collect($preferences ?? []);
        $categories = collect($categories ?? ['order', 'promo', 'stock', 'loyalty', 'system']);
        $channels = collect($channels ?? ['email', 'push', 'whatsapp']);

        $enabledMap = $preferences->mapWithKeys(fn ($preference) => [
            $preference->category.':'.$preference->channel => (bool) $preference->enabled,
        ])->all();
    @endphp

    <div class="sf-container">
        <nav aria-label="Breadcrumb" class="sf-breadcrumb">
            <a href="{{ route('home') }}">Beranda</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <a href="{{ route('account.dashboard') }}">Dashboard</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <span aria-current="page">Preferensi</span>
        </nav>
    </div>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-preferences-title">
        <div class="sf-container">
            <h1 class="sf-section-head__title" id="sf-preferences-title">Preferensi Notifikasi</h1>
            <p class="sf-muted sf-small" style="max-width:60ch">
                Pilih jenis informasi dan kanal yang ingin Anda terima.
            </p>

            <div class="sf-account">
                <aside>
                    <x-storefront.account-nav current="account.preferences" />
                </aside>

                <div class="sf-stack" style="gap:20px">
                    <section class="sf-card" aria-labelledby="sf-preferences-form">
                        <div class="sf-card__body">
                            <h2 class="sf-footer__title" id="sf-preferences-form">Kanal notifikasi</h2>

                            <form method="POST" action="{{ route('account.preferences.update') }}" novalidate>
                                @csrf
                                @method('PUT')

                                <div class="sf-tablewrap" style="margin-top:12px">
                                    <table class="sf-table">
                                        <caption class="sf-sr-only">Pilihan kanal notifikasi untuk setiap kategori</caption>
                                        <thead>
                                            <tr>
                                                <th scope="col">Kategori</th>
                                                @foreach ($channels as $channel)
                                                    <th scope="col" class="sf-table__num">{{ \Illuminate\Support\Str::headline((string) $channel) }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($categories as $category)
                                                <tr>
                                                    <th scope="row" style="font-weight:500">{{ \Illuminate\Support\Str::headline((string) $category) }}</th>
                                                    @foreach ($channels as $channel)
                                                        @php
                                                            $field = $category.':'.$channel;
                                                            $isOn = $enabledMap[$field] ?? ($channel === 'email');
                                                        @endphp
                                                        <td class="sf-table__num">
                                                            <label class="sf-checkbox" for="sf-pref-{{ str_replace(':', '-', $field) }}">
                                                                <input type="checkbox" id="sf-pref-{{ str_replace(':', '-', $field) }}"
                                                                       name="preferences[{{ $field }}]" value="1" @checked($isOn)>
                                                                <span class="sf-sr-only">
                                                                    {{ \Illuminate\Support\Str::headline((string) $category) }} via
                                                                    {{ \Illuminate\Support\Str::headline((string) $channel) }}
                                                                </span>
                                                            </label>
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div style="margin-top:18px">
                                    <button type="submit" class="sf-btn sf-btn--primary">
                                        <x-storefront.icon name="check" :size="16" /> Simpan preferensi
                                    </button>
                                </div>
                            </form>
                        </div>
                    </section>

                    <section class="sf-card" aria-labelledby="sf-preferences-privacy">
                        <div class="sf-card__body">
                            <h2 class="sf-footer__title" id="sf-preferences-privacy">Privasi</h2>
                            <p class="sf-small sf-muted">
                                Alamat email dan nomor telepon Anda hanya digunakan untuk transaksi, pengiriman,
                                dan notifikasi yang Anda setujui di halaman ini.
                            </p>
                            <a href="{{ route('page.privacy') }}" class="sf-btn sf-btn--ghost sf-btn--sm">
                                Baca kebijakan privasi
                            </a>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </section>
@endsection
