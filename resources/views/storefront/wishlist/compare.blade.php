@extends('layouts.storefront')

@section('content')
    @php
        $entries = collect($items)->filter(fn ($entry) => $entry->product)->values();
        $cheapest = $entries->sortBy(fn ($entry) => (float) $entry->product->getEffectivePrice())->first();
    @endphp

    <div class="sf-container">
        <nav aria-label="Breadcrumb" class="sf-breadcrumb">
            <a href="{{ route('home') }}">Beranda</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <span aria-current="page">Bandingkan Produk</span>
        </nav>
    </div>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-compare-title">
        <div class="sf-container">
            <div class="sf-section-head">
                <div>
                    <h1 class="sf-section-head__title" id="sf-compare-title">Bandingkan Produk</h1>
                    <p class="sf-muted sf-small sf-mt-0" style="max-width:60ch">
                        Bandingkan maksimal {{ $entries->count() }} dari 4 produk secara berdampingan.
                    </p>
                </div>
                <a href="{{ route('products.index') }}" class="sf-section-head__link">
                    <x-storefront.icon name="plus" :size="16" /> Tambah produk
                </a>
            </div>

            @if ($entries->isEmpty())
                <x-storefront.empty
                    title="Belum ada produk untuk dibandingkan"
                    text="Pilih hingga empat produk, lalu bandingkan harga, toko, dan spesifikasinya."
                    :href="route('products.index')"
                    label="Pilih produk"
                    icon="scale"
                />
            @else
                <div class="sf-tablewrap">
                    <table class="sf-table">
                        <caption class="sf-sr-only">Perbandingan {{ $entries->count() }} produk</caption>
                        <thead>
                            <tr>
                                <th scope="col" style="min-width:150px">Produk</th>
                                @foreach ($entries as $entry)
                                    <th scope="col" style="min-width:200px;vertical-align:top">
                                        <a href="{{ $entry->product->storefront_url }}" class="sf-clamp-2"
                                           style="display:-webkit-box;color:var(--sf-text)">{{ $entry->product->name }}</a>
                                        <form method="POST" action="{{ route('compare.remove', ['item' => $entry->id]) }}" style="margin-top:8px">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="sf-btn sf-btn--ghost sf-btn--sm"
                                                    aria-label="Hapus {{ $entry->product->name }} dari perbandingan">
                                                <x-storefront.icon name="trash" :size="14" /> Hapus
                                            </button>
                                        </form>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th scope="row">Gambar</th>
                                @foreach ($entries as $entry)
                                    <td>
                                        <a href="{{ $entry->product->storefront_url }}" tabindex="-1" aria-hidden="true">
                                            @if ($entry->product->thumbnail_url)
                                                <img src="{{ $entry->product->thumbnail_url }}" alt=""
                                                     loading="lazy" width="160" height="160" decoding="async"
                                                     style="width:100%;max-width:160px;aspect-ratio:1;object-fit:cover;border-radius:var(--sf-radius-sm);background:var(--sf-bg-muted)">
                                            @else
                                                <span class="sf-row" style="aspect-ratio:1;max-width:160px;border-radius:var(--sf-radius-sm);background:var(--sf-bg-muted);justify-content:center;color:var(--sf-text-subtle)">
                                                    <x-storefront.icon name="image" :size="26" />
                                                </span>
                                            @endif
                                        </a>
                                    </td>
                                @endforeach
                            </tr>
                            <tr>
                                <th scope="row">Harga</th>
                                @foreach ($entries as $entry)
                                    <td>
                                        <x-storefront.price
                                            :amount="$entry->product->getEffectivePrice()"
                                            :compare-at="$entry->product->price"
                                        />
                                        @if ($cheapest && $cheapest->id === $entry->id && $entries->count() > 1)
                                            <span class="sf-badge sf-badge--success" style="margin-top:6px">Harga termurah</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                            <tr>
                                <th scope="row">Toko</th>
                                @foreach ($entries as $entry)
                                    <td>
                                        @if ($entry->product->shop)
                                            <a href="{{ route('shop.show', $entry->product->shop->slug) }}">{{ $entry->product->shop->name }}</a>
                                        @else
                                            <span class="sf-muted">-</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                            <tr>
                                <th scope="row">Kategori</th>
                                @foreach ($entries as $entry)
                                    <td>
                                        @if ($entry->product->category)
                                            <a href="{{ route('categories.show', $entry->product->category->slug) }}">{{ $entry->product->category->name }}</a>
                                        @else
                                            <span class="sf-muted">-</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                            <tr>
                                <th scope="row">Merek</th>
                                @foreach ($entries as $entry)
                                    <td>
                                        @if ($entry->product->brand)
                                            <a href="{{ route('brands.show', $entry->product->brand->slug) }}">{{ $entry->product->brand->name }}</a>
                                        @else
                                            <span class="sf-muted">-</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                            <tr>
                                <th scope="row">Stok</th>
                                @foreach ($entries as $entry)
                                    <td>
                                        @if ($entry->product->is_out_of_stock)
                                            <span class="sf-stock sf-stock--out">Habis</span>
                                        @elseif ($entry->product->is_low_stock)
                                            <span class="sf-stock sf-stock--low">Sisa {{ \App\Support\Currency::number($entry->product->current_stock) }}</span>
                                        @else
                                            <span class="sf-stock sf-stock--in">{{ \App\Support\Currency::number($entry->product->current_stock) }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                            <tr>
                                <th scope="row">Terjual</th>
                                @foreach ($entries as $entry)
                                    <td>{{ \App\Support\Currency::number($entry->product->sold_count) }}</td>
                                @endforeach
                            </tr>
                            <tr>
                                <th scope="row">Rating</th>
                                @foreach ($entries as $entry)
                                    <td>
                                        @if ((int) $entry->product->rating_count > 0)
                                            <x-storefront.rating :rating="$entry->product->rating_average" :count="$entry->product->rating_count" />
                                        @else
                                            <span class="sf-muted">Belum ada ulasan</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                            <tr>
                                <th scope="row">Satuan</th>
                                @foreach ($entries as $entry)
                                    <td>{{ $entry->product->unit ?: 'pcs' }}</td>
                                @endforeach
                            </tr>
                            <tr>
                                <th scope="row">Tautan</th>
                                @foreach ($entries as $entry)
                                    <td>
                                        <a href="{{ $entry->product->storefront_url }}" class="sf-btn sf-btn--primary sf-btn--sm">
                                            Lihat produk
                                        </a>
                                    </td>
                                @endforeach
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
@endsection
