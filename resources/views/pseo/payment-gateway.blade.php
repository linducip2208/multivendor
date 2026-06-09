@extends('pseo._layout')
@section('meta')<link rel="canonical" href="{{ $canonical }}"/><meta name="description" content="{{ $desc }}"><meta property="og:title" content="{{ $title }}"><meta property="og:description" content="{{ $desc }}"><meta property="og:type" content="website"><meta property="og:url" content="{{ $canonical }}">@endsection
@section('title'){{ $title }}@endsection
@section('content')
<nav class="breadcrumb"><a href="/">Home</a> &raquo; <strong>{{ $gwName }}</strong></nav>
<div class="content-section">
    <h1>{{ $title }}</h1>
    <p class="lead">{{ $desc }}</p>
    <h2>Kenapa {{ $gwName }}?</h2>
    <ul><li>🔒 Transaksi aman dengan enkripsi</li><li>💳 Support berbagai channel: transfer bank, VA, QRIS, e-wallet, gerai retail</li><li>📊 Dashboard real-time untuk pantau transaksi</li><li>🔄 Auto-callback untuk update status pembayaran otomatis</li></ul>
    <h2>Cara Setup {{ $gwName }}</h2>
    <ol><li>Daftar akun di website resmi {{ $gwName }}</li><li>Dapatkan API Key / Server Key dari dashboard</li><li>Masuk ke Admin Panel → Integrasi → Tambah Provider</li><li>Pilih preset {{ $gwName }}, isi API Key, simpan</li><li>{{ $gwName }} siap digunakan di checkout!</li></ol>
    <p class="mt-3">Platform kami mendukung <strong>10 payment gateway Indonesia</strong>: Midtrans, Xendit, Tripay, Duitku, OY! Indonesia, iPaymu, Faspay, DOKU, ESIA Pay. Semua bisa diaktifkan dengan API key Anda sendiri.</p>
</div>
@endsection
