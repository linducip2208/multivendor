@extends('layouts.storefront')
@section('title', 'Wishlist')
@section('content')
<div class="container">
<h4 class="fw-bold mb-4"><i class="fas fa-heart me-2 text-danger"></i> Wishlist</h4>
@if($items->count()>0)
<div class="row g-3">@foreach($items as $item)
<div class="col-6 col-md-3"><a href="{{ route('products.show', $item->product->slug) }}" class="text-decoration-none"><div class="card product-card h-100"><div class="card-img-top d-flex align-items-center justify-content-center" style="height:180px;">@if($item->product->thumbnail)<img src="{{ url('img/'.$item->product->thumbnail) }}" class="w-100 h-100" style="object-fit:contain;">@else<i class="fas fa-box fa-3x text-muted opacity-25"></i>@endif</div><div class="card-body p-3"><div class="small text-muted mb-1">{{ $item->product->shop->name ?? '' }}</div><h6 class="small fw-semibold text-dark mb-2" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">{{ $item->product->name }}</h6><span class="fw-bold text-primary">Rp {{ number_format($item->product->getEffectivePrice(),0,',','.') }}</span></div></div></a></div>
@endforeach</div>{{ $items->links() }}
@else<div class="text-center py-5"><i class="fas fa-heart fa-4x text-muted mb-3 opacity-25"></i><h5>Wishlist kosong</h5><a href="{{ route('products.index') }}" class="btn btn-primary mt-2">Lihat Produk</a></div>@endif
</div>
@endsection
