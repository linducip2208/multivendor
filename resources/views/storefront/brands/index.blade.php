@extends('layouts.storefront')

@section('content')
    <div class="sf-container">
        <x-storefront.breadcrumb :items="$breadcrumbItems" />
    </div>

    <section class="sf-section" aria-labelledby="sf-brands-title">
        <div class="sf-container">
            <div class="sf-section-head">
                <div>
                    <h1 class="sf-section-head__title" id="sf-brands-title">Merek / Brand</h1>
                    <p class="sf-muted sf-small sf-mt-0" style="max-width:60ch">
                        Pilih merek untuk melihat seluruh produk yang tersedia dari merek tersebut.
                    </p>
                </div>
                <a href="{{ route('products.index') }}" class="sf-section-head__link">
                    Semua produk <x-storefront.icon name="arrow-right" :size="16" />
                </a>
            </div>

            @if ($brands->total() > 0)
                <div class="sf-brands">
                    @foreach ($brands as $brand)
                        <a href="{{ route('brands.show', $brand->slug) }}" class="sf-brand">
                            @if ($brand->logo_url)
                                <img src="{{ $brand->logo_url }}" alt="{{ $brand->name }}" loading="lazy" width="120" height="40" decoding="async">
                            @else
                                <span class="sf-truncate">{{ $brand->name }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>

                <x-storefront.pagination :paginator="$brands" />
            @else
                <x-storefront.empty
                    title="Belum ada merek"
                    text="Merek akan tampil setelah tersedia produk yang memakai merek tersebut."
                    :href="route('products.index')"
                    label="Lihat produk"
                    icon="award"
                />
            @endif
        </div>
    </section>
@endsection
