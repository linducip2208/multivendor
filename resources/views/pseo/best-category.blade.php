@extends('pseo._layout')
@section('meta')
<link rel="canonical" href="{{ $canonical }}"/>
<meta name="description" content="{{ $desc }}">
<meta property="og:title" content="{{ $title }}"><meta property="og:description" content="{{ $desc }}"><meta property="og:type" content="website"><meta property="og:url" content="{{ $canonical }}">
@endsection
@section('jsonld'){!! $jsonld !!}@endsection
@section('title'){{ $title }}@endsection
@section('content')
<nav class="breadcrumb mb-3"><a href="/">Home</a> &raquo; <a href="{{ route('products.index') }}">Produk</a> &raquo; <a href="{{ route('products.index', ['category' => $category->slug]) }}">{{ $category->name }}</a> &raquo; <strong>Best Seller</strong></nav>
<div class="content-section">
    <h1>{{ $title }}</h1>
    <p class="lead">{{ $desc }}</p>
    <p>Berikut adalah daftar 10 produk <strong>{{ $category->name }}</strong> paling laris di platform multivendor kami. Data berdasarkan transaksi real dari ratusan toko online di {{ config('app.name') }}.</p>

    <div class="table-responsive"><table class="table table-hover table-comparison">
        <thead class="table-light"><tr><th>#</th><th>Produk</th><th>Toko</th><th>Harga</th><th>Terjual</th><th>Beli</th></tr></thead>
        <tbody>@foreach($products as $i => $p)
        <tr><td class="fw-bold">{{ $i + 1 }}</td><td><strong>{{ $p->name }}</strong></td><td>{{ $p->shop->name ?? '' }}</td><td>Rp {{ number_format($p->getEffectivePrice(),0,',','.') }}</td><td>{{ $p->sold ?? 0 }}</td><td><a href="{{ route('products.show', $p->slug) }}" class="btn btn-sm btn-primary">Lihat</a></td></tr>
        @endforeach</tbody>
    </table></div>

    <h2>Kenapa Belanja {{ $category->name }} di {{ config('app.name') }}?</h2>
    <ul>
        <li>✅ Ratusan toko online terpercaya</li>
        <li>✅ Pembayaran aman dengan Midtrans, Xendit, dan 8 payment gateway Indonesia lainnya</li>
        <li>✅ Ongkos kirim murah dari JNE, J&T, SiCepat, TIKI, POS, dan kurir lainnya</li>
        <li>✅ Garansi uang kembali jika produk tidak sesuai</li>
        <li>✅ Customer support 24/7 via WhatsApp dan tiket support</li>
    </ul>

    <h2>FAQ — {{ $category->name }} Terlaris</h2>
    <p><strong>Q: Bagaimana cara memilih {{ $category->name }} yang bagus?</strong><br>A: Perhatikan rating penjual, jumlah terjual, dan review dari pembeli sebelumnya. Pilih toko dengan rating tinggi dan produk dengan review positif.</p>
    <p><strong>Q: Apakah ongkos kirim {{ $category->name }} mahal?</strong><br>A: Tergantung lokasi dan kurir. Gunakan fitur cek ongkir kami untuk membandingkan harga dari berbagai kurir sebelum checkout.</p>
    <p><strong>Q: Payment gateway apa yang tersedia?</strong><br>A: Kami support Midtrans, Xendit, Tripay, Duitku, OY! Indonesia, iPaymu, Faspay, DOKU, dan ESIA Pay. Semua metode pembayaran Indonesia lengkap.</p>
</div>
@endsection
