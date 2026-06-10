@extends('pseo._layout')
@section('meta')<link rel="canonical" href="{{ $canonical }}"/><meta name="description" content="{{ $desc }}"><meta property="og:title" content="{{ $title }}"><meta property="og:description" content="{{ $desc }}"><meta property="og:type" content="product"><meta property="og:url" content="{{ $canonical }}">@if($product->thumbnail)<meta property="og:image" content="{{ url('img/'.$product->thumbnail) }}">@endif
@endsection
@section('jsonld'){!! $jsonld !!}@endsection
@section('title'){{ $title }}@endsection
@section('content')
<nav class="breadcrumb"><a href="/">Home</a> &raquo; <a href="{{ route('products.show', $product->slug) }}">{{ $product->name }}</a> &raquo; <strong>Beli</strong></nav>
<div class="content-section">
    <h1>{{ $title }}</h1>
    <div class="row"><div class="col-md-4 text-center">@if($product->thumbnail)<img src="{{ url('img/'.$product->thumbnail) }}" class="img-fluid rounded-4 mb-3" style="max-height:300px">@endif</div>
    <div class="col-md-8">
        <p class="lead">{{ $desc }}</p>
        <h3 class="fw-bold text-primary">Rp {{ number_format($product->getEffectivePrice(),0,',','.') }}</h3>
        <p>Toko: <strong>{{ $product->shop->name ?? '' }}</strong> | Stok: {{ $product->current_stock }} | Kategori: {{ $product->category->name ?? '' }}</p>
        <a href="{{ route('products.show', $product->slug) }}" class="btn btn-primary btn-lg px-5"><i class="fas fa-shopping-cart me-2"></i>Beli Sekarang</a>
    </div></div>
    <h2 class="mt-4">Cara Belanja di {{ config('app.name') }}</h2>
    <ol><li>Pilih produk yang diinginkan</li><li>Tambah ke keranjang</li><li>Checkout dengan payment gateway pilihan Anda (Midtrans, Xendit, dll)</li><li>Pilih kurir pengiriman (JNE, J&T, SiCepat, dll)</li><li>Bayar dan tunggu pesanan sampai</li></ol>
    <p>Semua transaksi aman dengan enkripsi AES-256. Didukung 10 payment gateway Indonesia dan 16 kurir pengiriman.</p>
</div>
@endsection
