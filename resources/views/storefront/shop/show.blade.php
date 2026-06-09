@extends('layouts.storefront')
@section('title', $shop->name)
@section('content')
<div class="container">
    <div class="card border-0 shadow-sm rounded-4 mb-4"><div class="card-body p-4 text-center">
        <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width:80px;height:80px;"><i class="fas fa-store fa-2x text-primary"></i></div>
        <h3 class="fw-bold">{{ $shop->name }}</h3>
        @if($shop->description)<p class="text-muted">{{ $shop->description }}</p>@endif
        <div class="d-flex justify-content-center gap-4 small text-muted"><span><i class="fas fa-map-marker-alt me-1"></i>{{ $shop->address ?? '-' }}</span><span><i class="fas fa-phone me-1"></i>{{ $shop->phone ?? '-' }}</span></div>
    </div></div>
    <h5 class="fw-bold mb-3">Produk dari {{ $shop->name }}</h5>
    <div class="row g-3">@foreach($shop->products as $p)
    <div class="col-6 col-md-3"><a href="{{ route('products.show', $p->slug) }}" class="text-decoration-none"><div class="card product-card h-100"><div class="card-img-top d-flex align-items-center justify-content-center bg-light" style="height:180px;">@if($p->thumbnail)<img src="{{ asset('storage/'.$p->thumbnail) }}" class="w-100 h-100" style="object-fit:contain;">@else<i class="fas fa-box fa-3x text-muted opacity-25"></i>@endif</div><div class="card-body p-3"><h6 class="fw-semibold small line-clamp-2 text-dark">{{ $p->name }}</h6><span class="fw-bold text-primary">Rp {{ number_format($p->getEffectivePrice(),0,',','.') }}</span></div></div></a></div>@endforeach</div>
</div>
@endsection
