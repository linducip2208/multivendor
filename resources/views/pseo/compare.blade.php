@extends('pseo._layout')
@section('meta')<link rel="canonical" href="{{ $canonical }}"/><meta name="description" content="{{ $desc }}"><meta property="og:title" content="{{ $title }}"><meta property="og:description" content="{{ $desc }}"><meta property="og:type" content="website"><meta property="og:url" content="{{ $canonical }}">@endsection
@section('jsonld'){!! $jsonld !!}@endsection
@section('title'){{ $title }}@endsection
@section('content')
<nav class="breadcrumb"><a href="/">Home</a> &raquo; <strong>Perbandingan</strong></nav>
<div class="content-section">
    <h1>{{ $title }}</h1>
    <p class="lead">{{ $desc }}</p>

    <div class="table-responsive"><table class="table table-bordered table-comparison">
        <thead><tr><th>Fitur</th><th>{{ $pa->name }}</th><th>{{ $pb->name }}</th></tr></thead>
        <tbody>
            <tr><td><strong>Harga</strong></td><td class="fw-bold text-primary">Rp {{ number_format($pa->getEffectivePrice(),0,',','.') }}</td><td class="fw-bold text-primary">Rp {{ number_format($pb->getEffectivePrice(),0,',','.') }}</td></tr>
            <tr><td><strong>Toko</strong></td><td>{{ $pa->shop->name ?? '-' }}</td><td>{{ $pb->shop->name ?? '-' }}</td></tr>
            <tr><td><strong>Kategori</strong></td><td>{{ $pa->category->name ?? '-' }}</td><td>{{ $pb->category->name ?? '-' }}</td></tr>
            <tr><td><strong>Stok</strong></td><td>{{ $pa->current_stock }}</td><td>{{ $pb->current_stock }}</td></tr>
            <tr><td><strong>Brand</strong></td><td>{{ $pa->brand->name ?? '-' }}</td><td>{{ $pb->brand->name ?? '-' }}</td></tr>
        </tbody>
    </table></div>

    @if($pa->description)<h2>Tentang {{ $pa->name }}</h2><p>{!! Str::limit(strip_tags($pa->description), 500) !!}</p>@endif
    @if($pb->description)<h2>Tentang {{ $pb->name }}</h2><p>{!! Str::limit(strip_tags($pb->description), 500) !!}</p>@endif

    <h2>Mana yang Lebih Bagus?</h2>
    <p>Pilihan tergantung budget dan kebutuhan Anda. {{ $pa->name }} cocok untuk yang mencari kualitas premium, sementara {{ $pb->name }} lebih terjangkau. Gunakan payment gateway kami untuk pembayaran aman dan ongkos kirim murah ke seluruh Indonesia.</p>
</div>
@endsection
