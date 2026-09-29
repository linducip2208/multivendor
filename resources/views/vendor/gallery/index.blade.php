@extends('layouts.vendor')
@section('title','Galeri Produk')
@section('content')
<h4 class="fw-bold mb-3"><x-admin.icon name="photo" :size="20" class="me-2 text-success" /> Galeri Produk</h4>
<div class="row g-3">@forelse($products as $p)<div class="col-6 col-md-3"><div class="card overflow-hidden"><x-ui.thumbnail :src="url('img/'.$p->thumbnail)" :alt="$p->name" /><div class="card-body p-2"><h6 class="small fw-semibold line-clamp-2">{{ $p->name }}</h6><small class="text-muted">Rp {{ number_format($p->price,0,',','.') }}</small></div></div></div>@empty<div class="col-12 text-center py-5 text-muted"><x-admin.icon name="photo" :size="48" class="mb-2 opacity-25" /><p>Unggah foto produk dulu</p></div>@endforelse</div><x-admin.pagination>{{ $products->links() }}</x-admin.pagination>
@endsection
