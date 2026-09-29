@extends('layouts.storefront')

@section('content')
    <div class="sf-container">
        <nav aria-label="Breadcrumb" class="sf-breadcrumb">
            <a href="{{ route('home') }}">Beranda</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <span aria-current="page">Favorit</span>
        </nav>
    </div>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-wishlist-title">
        <div class="sf-container">
            <div class="sf-section-head">
                <div>
                    <h1 class="sf-section-head__title" id="sf-wishlist-title">Favorit Saya</h1>
                    <p class="sf-muted sf-small sf-mt-0" style="max-width:60ch">
                        Produk yang Anda simpan tampil di sini. Tekan ikon hati untuk menghapus dari daftar.
                    </p>
                </div>
                @if ($items->total() > 0)
                    <a href="{{ route('products.index') }}" class="sf-section-head__link">
                        <x-storefront.icon name="plus" :size="16" /> Tambah produk
                    </a>
                @endif
            </div>

            @if ($items->total() > 0)
                <div class="sf-products">
                    @foreach ($items as $entry)
                        <x-storefront.product-card :product="$entry->product" :wishlisted="true" />
                    @endforeach
                </div>

                <x-storefront.pagination :paginator="$items" />
            @else
                <x-storefront.empty
                    title="Belum ada produk favorit"
                    text="Tekan ikon hati pada produk mana pun untuk menyimpannya di sini dan membandingkannya nanti."
                    :href="route('products.index')"
                    label="Jelajahi produk"
                    icon="heart"
                />
            @endif
        </div>
    </section>
@endsection
