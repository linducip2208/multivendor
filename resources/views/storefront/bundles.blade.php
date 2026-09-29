@extends('layouts.storefront')

@section('content')
    <div class="sf-container">
        <nav aria-label="Breadcrumb" class="sf-breadcrumb">
            <a href="{{ route('home') }}">Beranda</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <span aria-current="page">Bundling</span>
        </nav>
    </div>

    <section class="sf-section" aria-labelledby="sf-bundles-title">
        <div class="sf-container">
            <div class="sf-section-head">
                <div>
                    <h1 class="sf-section-head__title" id="sf-bundles-title">Paket Hemat</h1>
                    <p class="sf-muted sf-small sf-mt-0" style="max-width:60ch">
                        Gabungkan beberapa produk dalam satu paket dan hemat dibanding membeli satuan.
                    </p>
                </div>
                <a href="{{ route('products.index') }}" class="sf-section-head__link">
                    Semua produk <x-storefront.icon name="arrow-right" :size="16" />
                </a>
            </div>

            @if ($bundles->isNotEmpty())
                <div class="sf-stack" style="gap:20px">
                    @foreach ($bundles as $bundle)
                        <section class="sf-card" aria-labelledby="sf-bundle-{{ $bundle->id }}">
                            <div class="sf-card__body">
                                <div class="sf-row sf-row--between sf-row--wrap" style="gap:10px;margin-bottom:14px">
                                    <h2 class="sf-mb-0" id="sf-bundle-{{ $bundle->id }}" style="font-size:1.1rem">
                                        {{ $bundle->title }}
                                    </h2>
                                    @if ((float) $bundle->discount_percentage > 0)
                                        <span class="sf-badge sf-badge--solid-danger">
                                            Hemat {{ \App\Support\Currency::number($bundle->discount_percentage) }}%
                                        </span>
                                    @endif
                                </div>

                                <div class="sf-products" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr))">
                                    @foreach ($bundle->products as $product)
                                        <x-storefront.product-card :product="$product" />
                                    @endforeach
                                </div>
                            </div>
                        </section>
                    @endforeach
                </div>
            @else
                <x-storefront.empty
                    title="Belum ada paket hemat"
                    text="Paket hemat akan tampil setelah administrator membuatnya."
                    :href="route('products.index')"
                    label="Jelajahi katalog"
                    icon="layers"
                />
            @endif
        </div>
    </section>
@endsection
