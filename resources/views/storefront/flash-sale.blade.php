@extends('layouts.storefront')

@section('content')
    <div class="sf-container">
        <x-storefront.breadcrumb :items="$breadcrumbItems" />
    </div>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-flash-sale-title">
        <div class="sf-container">
            <div class="sf-section-head">
                <div>
                    <h1 class="sf-section-head__title" id="sf-flash-sale-title" style="font-size:clamp(1.5rem,1.2rem+1.4vw,2.25rem)">
                        {{ $deal?->title ?: 'Flash Sale' }}
                    </h1>
                    <p class="sf-muted sf-small sf-mt-0" style="max-width:60ch">
                        Harga kilat dengan stok terbatas. Selesaikan pembelian sebelum waktu habis.
                    </p>
                </div>
                @if ($deal && $deal->end_date)
                    <x-storefront.countdown :ends-at="$deal->end_date" />
                @endif
            </div>

            @if ($deal && $deal->banner)
                <a href="{{ route('products.index') }}" class="sf-banner" style="margin-bottom:24px">
                    <img src="{{ url('img/'.ltrim((string) $deal->banner, '/')) }}" alt="{{ $deal->title ?: 'Flash sale' }}"
                         width="1200" height="675" loading="eager" fetchpriority="high" decoding="async">
                </a>
            @endif

            @if ($products->isNotEmpty())
                <div class="sf-products">
                    @foreach ($products as $product)
                        <x-storefront.product-card :product="$product" />
                    @endforeach
                </div>
            @else
                <x-storefront.empty
                    title="Belum ada flash sale aktif"
                    text="Jadwal flash sale berikutnya belum diumumkan. Simpan halaman ini atau cek katalog kami secara berkala."
                    :href="route('products.index')"
                    label="Lihat katalog"
                    icon="flame"
                />
            @endif
        </div>
    </section>

    @include('storefront._partials.flash-sale-stripe', ['deal' => $deal])
@endsection
