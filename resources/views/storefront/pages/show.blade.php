@extends('layouts.storefront')

@section('content')
    <div class="sf-container">
        <x-storefront.breadcrumb :items="$breadcrumbItems" />
    </div>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-page-title">
        <div class="sf-container" style="max-width:820px">
            <p class="sf-section-head__eyebrow" style="margin-bottom:6px">
                <x-storefront.icon :name="in_array($icon, ['shield-check', 'store', 'refresh', 'info', 'book', 'headset'], true) ? $icon : 'info'" :size="16" />
                Informasi
            </p>
            <h1 class="sf-section-head__title" id="sf-page-title">{{ $title }}</h1>

            @if (trim((string) $content) !== '')
                <div class="sf-prose" style="margin-top:20px">{!! $content !!}</div>
            @else
                <x-storefront.empty
                    :title="$title.' belum tersedia'"
                    text="Konten halaman ini belum diisi oleh administrator. Silakan kembali lagi nanti."
                    :href="route('home')"
                    label="Kembali ke beranda"
                    icon="info"
                />
            @endif

            <hr class="sf-divider" style="margin-block:32px">

            <div class="sf-row sf-row--wrap" style="gap:10px">
                <a href="{{ route('home') }}" class="sf-btn sf-btn--outline sf-btn--sm">
                    <x-storefront.icon name="chevron-left" :size="15" /> Beranda
                </a>
                <a href="{{ route('docs') }}" class="sf-btn sf-btn--ghost sf-btn--sm">Panduan belanja</a>
                <a href="{{ route('blog.index') }}" class="sf-btn sf-btn--ghost sf-btn--sm">Blog</a>
                <a href="{{ route('tickets.create') }}" class="sf-btn sf-btn--ghost sf-btn--sm">Hubungi kami</a>
            </div>
        </div>
    </section>
@endsection
