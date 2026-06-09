@extends('layouts.storefront')
@section('title', 'Rekomendasi untuk Anda')
@section('content')
<div class="container"><h4 class="fw-bold mb-4"><i class="fas fa-lightbulb me-2 text-warning"></i> Rekomendasi Produk</h4>
@if($boughtTogether->count()>0)<h6 class="mb-3">Customer yang lihat {{ $product->name }} juga beli:</h6><div class="row g-3">@foreach($boughtTogether as $rp)<div class="col-6 col-md-3">@include('storefront.products._card',['product'=>$rp])</div>@endforeach</div>@else<div class="empty-state"><i class="fas fa-lightbulb"></i><h5>Belum ada rekomendasi</h5><a href="{{ route('products.index') }}" class="btn btn-primary">Lihat Produk</a></div>@endif</div>
@endsection
