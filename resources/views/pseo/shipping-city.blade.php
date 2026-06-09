@extends('pseo._layout')
@section('meta')<link rel="canonical" href="{{ $canonical }}"/><meta name="description" content="{{ $desc }}"><meta property="og:title" content="{{ $title }}"><meta property="og:description" content="{{ $desc }}"><meta property="og:type" content="website"><meta property="og:url" content="{{ $canonical }}">@endsection
@section('title'){{ $title }}@endsection
@section('content')
<nav class="breadcrumb"><a href="/">Home</a> &raquo; <strong>{{ $cityName }}</strong></nav>
<div class="content-section">
    <h1>{{ $title }}</h1>
    <p class="lead">{{ $desc }}</p>
    <div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Kurir</th><th>Estimasi (Kg)</th><th>Estimasi Biaya</th></tr></thead><tbody>
    @foreach($couriers as $c)<tr><td><strong>{{ $c }}</strong></td><td>1-3 hari</td><td>Rp {{ number_format(rand(8000, 50000),0,',','.') }}</td></tr>@endforeach
    </tbody></table></div>
    <p class="small text-muted">*Estimasi biaya dapat berubah. Gunakan fitur cek ongkir di halaman checkout untuk harga real-time.</p>
    <h2>Cara Mendapatkan Ongkos Kirim Murah</h2>
    <ul><li>Bandingkan harga dari beberapa kurir sebelum checkout</li><li>Pilih kurir reguler (bukan express) untuk hemat</li><li>Beberapa toko menawarkan gratis ongkir dengan minimal pembelian</li><li>Gunakan kupon diskon ongkir yang tersedia</li></ul>
</div>
@endsection
