@extends('layouts.vendor')
@section('title','Galeri Produk')
@section('content')
<h4 class="fw-bold mb-3"><i class="fas fa-images me-2 text-success"></i> Galeri Produk</h4>
<div class="row g-3">@forelse($products as $p)<div class="col-6 col-md-3"><div class="card border-0 shadow-sm rounded-4 overflow-hidden"><img src="{{ url('img/'.$p->thumbnail) }}" class="w-100" style="height:200px;object-fit:contain;"><div class="card-body p-2"><h6 class="small fw-semibold line-clamp-2">{{ $p->name }}</h6><small class="text-muted">Rp {{ number_format($p->price,0,',','.') }}</small></div></div></div>@empty<div class="col-12 text-center py-5 text-muted"><i class="fas fa-images fa-3x mb-2 opacity-25"></i><p>Upload foto produk dulu</p></div>@endforelse</div>{{ $products->links() }}
@endsection
