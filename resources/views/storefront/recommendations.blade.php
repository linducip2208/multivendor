@extends('layouts.storefront')

@section('content')
    <div class="sf-container">
        <nav aria-label="Breadcrumb" class="sf-breadcrumb">
            <a href="{{ route('home') }}">Beranda</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <a href="{{ route('products.show', $product->slug) }}">{{ $product->name }}</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <span aria-current="page">Rekomendasi</span>
        </nav>
    </div>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-recommendations-title">
        <div class="sf-container">
            <div class="sf-section-head">
                <div>
                    <h1 class="sf-section-head__title" id="sf-recommendations-title">Rekomendasi untuk Anda</h1>
                    <p class="sf-muted sf-small sf-mt-0" style="max-width:60ch">
                        Produk sejenis dari kategori yang sama dengan {{ $product->name }}.
                    </p>
                </div>
                <a href="{{ route('products.index') }}" class="sf-section-head__link">
                    Semua produk <x-storefront.icon name="arrow-right" :size="16" />
                </a>
            </div>

            @if ($boughtTogether->isNotEmpty())
                <div class="sf-products">
                    @foreach ($boughtTogether as $item)
                        <x-storefront.product-card :product="$item" />
                    @endforeach
                </div>
            @else
                <x-storefront.empty
                    title="Belum ada rekomendasi"
                    text="Rekomendasi muncul setelah tersedia produk lain pada kategori yang sama."
                    :href="route('products.index')"
                    label="Jelajahi katalog"
                    icon="sparkles"
                />
            @endif
        </div>
    </section>
@endsection
