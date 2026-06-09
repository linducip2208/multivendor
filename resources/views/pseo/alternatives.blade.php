@extends('pseo._layout')
@section('meta')<link rel="canonical" href="{{ $canonical }}"/><meta name="description" content="{{ $desc }}"><meta property="og:title" content="{{ $title }}"><meta property="og:description" content="{{ $desc }}"><meta property="og:type" content="website"><meta property="og:url" content="{{ $canonical }}">@endsection
@section('jsonld'){!! $jsonld !!}@endsection
@section('title'){{ $title }}@endsection
@section('content')
<nav class="breadcrumb"><a href="/">Home</a> &raquo; <a href="{{ route('products.index') }}">Produk</a> &raquo; <a href="{{ route('products.show', $product->slug) }}">{{ $product->name }}</a> &raquo; <strong>Alternatif</strong></nav>
<div class="content-section">
    <h1>{{ $title }}</h1>
    <p class="lead">Sedang mencari alternatif <strong>{{ $product->name }}</strong>? Kami punya {{ $alternatives->count() }} rekomendasi produk serupa dari toko terpercaya di {{ config('app.name') }}.</p>
    <p>Harga {{ $product->name }} saat ini <strong>Rp {{ number_format($product->getEffectivePrice(),0,',','.') }}</strong> dari toko {{ $product->shop->name ?? '' }}. Tapi mungkin ada alternatif dengan harga lebih murah atau fitur lebih lengkap.</p>

    <h2>{{ $alternatives->count() }} Alternatif {{ $product->name }}</h2>
    <div class="row g-3">@foreach($alternatives as $alt)<div class="col-md-4"><div class="card border-0 shadow-sm rounded-4 h-100 p-3"><h6 class="fw-bold">{{ $alt->name }}</h6><small class="text-muted">{{ $alt->shop->name ?? '' }}</small><div class="fw-bold text-primary mt-2">Rp {{ number_format($alt->getEffectivePrice(),0,',','.') }}</div><a href="{{ route('products.show', $alt->slug) }}" class="btn btn-sm btn-outline-primary mt-2">Lihat Produk</a></div></div>@endforeach</div>

    <h2 class="mt-4">Kenapa Cari Alternatif?</h2>
    <ul><li>💰 Harga lebih murah dari toko lain</li><li>📦 Stok tersedia di banyak toko</li><li>⭐ Rating dan review lebih baik</li><li>🚚 Ongkos kirim lebih murah dari toko terdekat</li></ul>
</div>
@endsection
