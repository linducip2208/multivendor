@extends('layouts.storefront')
@section('title', 'Produk')

@section('content')
<div class="container">
    <div class="row g-4">
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 mb-3">
                <h6 class="fw-bold mb-3"><i class="fas fa-filter me-2"></i>Kategori</h6>
                <div class="list-group list-group-flush">
                    <a href="{{ route('products.index') }}" class="list-group-item list-group-item-action border-0 {{ !request('category') ? 'active' : '' }}">Semua</a>
                    @foreach($categories as $cat)
                    <a href="?category={{ $cat->slug }}" class="list-group-item list-group-item-action border-0 {{ request('category') === $cat->slug ? 'active' : '' }}">
                        {{ $cat->name }}
                    </a>
                    @endforeach
                </div>
            </div>
            <div class="card border-0 shadow-sm rounded-4 p-3">
                <h6 class="fw-bold mb-3"><i class="fas fa-tag me-2"></i>Harga</h6>
                <form method="GET">
                    @if(request('category'))<input type="hidden" name="category" value="{{ request('category') }}">@endif
                    <div class="mb-2"><input type="number" name="min_price" class="form-control form-control-sm" placeholder="Min Rp" value="{{ request('min_price') }}"></div>
                    <div class="mb-2"><input type="number" name="max_price" class="form-control form-control-sm" placeholder="Max Rp" value="{{ request('max_price') }}"></div>
                    <button class="btn btn-primary btn-sm w-100">Terapkan</button>
                </form>
            </div>
        </div>

        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold mb-0">{{ request('category') ? ucfirst(request('category')) : 'Semua Produk' }}</h5>
                    <small class="text-muted">{{ $products->total() }} produk ditemukan</small>
                </div>
                <div>
                    <form method="GET" class="d-flex gap-2">
                        @foreach(request()->except('search', 'sort') as $k => $v)
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endforeach
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari produk..." value="{{ request('search') }}" style="width:200px;">
                        <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="latest" {{ request('sort') === 'latest' ? 'selected' : '' }}>Terbaru</option>
                            <option value="price_low" {{ request('sort') === 'price_low' ? 'selected' : '' }}>Harga Rendah</option>
                            <option value="price_high" {{ request('sort') === 'price_high' ? 'selected' : '' }}>Harga Tinggi</option>
                        </select>
                    </form>
                </div>
            </div>

            <div class="row g-3">
                @forelse($products as $product)
                <div class="col-6 col-md-4 col-lg-3">@include('storefront.products._card', ['product' => $product])</div>
                @empty
                <div class="col-12 text-center py-5">
                    <i class="fas fa-box-open fa-4x text-muted mb-3 opacity-25"></i>
                    <h5>Belum ada produk</h5>
                    <p class="text-muted">Produk akan muncul setelah vendor menambahkan dan admin menyetujui.</p>
                </div>
                @endforelse
            </div>

            @if($products->hasPages())
            <div class="mt-4">{{ $products->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
