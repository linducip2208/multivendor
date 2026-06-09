@extends('layouts.storefront')
@section('title', $product->meta_title ?: $product->name)

@push('head')
<meta property="og:title" content="{{ $product->meta_title ?: $product->name }}">
<meta property="og:description" content="{{ $product->meta_description ?: strip_tags($product->short_description ?? $product->description) }}">
@if($product->thumbnail)<meta property="og:image" content="{{ asset('storage/'.$product->thumbnail) }}">@endif
@if($product->meta_description)<meta name="description" content="{{ $product->meta_description }}">@endif
@endpush

@section('content')
<div class="container">
    <nav class="mb-3"><ol class="breadcrumb small"><li class="breadcrumb-item"><a href="/">Home</a></li><li class="breadcrumb-item"><a href="{{ route('products.index') }}">Produk</a></li>@if($product->category)<li class="breadcrumb-item"><a href="?category={{ $product->category->slug }}">{{ $product->category->name }}</a></li>@endif<li class="breadcrumb-item active">{{ Str::limit($product->name, 40) }}</li></ol></nav>

    <div class="row g-4">
        {{-- Image Gallery --}}
        <div class="col-md-6">
            <div class="bg-white rounded-4 border p-4 text-center position-relative" style="min-height:400px;">
                @if($product->getDiscountPercentage())
                <span class="position-absolute top-0 start-0 badge bg-danger m-3 fs-6 px-3 py-2">-{{ $product->getDiscountPercentage() }}%</span>
                @endif
                @php $mainImg = $product->thumbnail ? (str_starts_with($product->thumbnail,'http') ? $product->thumbnail : asset('storage/'.$product->thumbnail)) : 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22300%22 height=%22300%22><rect fill=%22%23f1f5f9%22 width=%22300%22 height=%22300%22/><text fill=%22%2394a3b8%22 x=%22150%22 y=%22160%22 text-anchor=%22middle%22 font-size=%2250%22>📦</text></svg>'; @endphp
                <img id="mainImage" src="{{ $mainImg }}" class="img-fluid rounded-3" style="max-height:400px;object-fit:contain;" alt="{{ $product->name }}">
            </div>
            @php $allImages = $product->thumbnail ? [$product->thumbnail] : []; $extras = json_decode($product->images ?? '[]', true) ?? []; $allImages = array_merge($allImages, $extras); @endphp
            @if(count($allImages) > 1)
            <div class="d-flex gap-2 mt-2 overflow-auto pb-2">
                @foreach($allImages as $img)
                @php $imgUrl = str_starts_with($img, 'http') ? $img : asset('storage/'.$img); @endphp
                <img src="{{ $imgUrl }}" class="rounded-3 border cursor-pointer" style="width:64px;height:64px;object-fit:cover;" onclick="document.getElementById('mainImage').src=this.src">
                @endforeach
            </div>
            @endif

            @if($product->video_url)
            <div class="mt-3"><h6 class="fw-bold"><i class="fas fa-play me-2 text-danger"></i>Video</h6>
                <div class="ratio ratio-16x9 rounded-4 overflow-hidden">
                    @php preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^\&\?\/]+)/', $product->video_url, $m); $ytId = $m[1] ?? ''; @endphp
                    @if($ytId)<iframe src="https://www.youtube.com/embed/{{ $ytId }}" allowfullscreen></iframe>
                    @elseif(str_ends_with($product->video_url, '.mp4') || str_contains($product->video_url, 'storage/videos'))<video controls class="w-100"><source src="{{ str_starts_with($product->video_url, 'videos/') ? asset('storage/'.$product->video_url) : $product->video_url }}" type="video/mp4"></video>
                    @else<video controls class="w-100"><source src="{{ $product->video_url }}" type="video/mp4"></video>@endif
                </div>
            </div>
            @endif
        </div>

        {{-- Product Info --}}
        <div class="col-md-6">
            <a href="{{ route('shop.show', $product->shop->slug ?? '#') }}" class="text-decoration-none small text-muted"><i class="fas fa-store me-1"></i>{{ $product->shop->name ?? 'Unknown Shop' }}</a>
            <h3 class="fw-bold mb-2 mt-1">{{ $product->name }}</h3>

            @if($product->reviews->avg('rating'))
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="text-warning">@for($i=1;$i<=5;$i++)<i class="fas fa-star{{ $i <= round($product->reviews->avg('rating')) ? '' : '-o' }}"></i>@endfor</span>
                <small class="text-muted">{{ number_format($product->reviews->avg('rating'),1) }} ({{ $product->reviews->count() }} ulasan)</small>
            </div>
            @endif

            <div class="d-flex align-items-center gap-3 mb-3">
                <span class="fs-2 fw-bold text-primary">Rp {{ number_format($product->getEffectivePrice(), 0, ',', '.') }}</span>
                @if($product->getDiscountPercentage())
                <span class="text-muted text-decoration-line-through fs-5">Rp {{ number_format($product->price, 0, ',', '.') }}</span>
                @endif
            </div>

            {{-- Short Description --}}
            @if($product->short_description)
            <div class="bg-light rounded-3 p-3 mb-3 lh-sm small">{!! $product->short_description !!}</div>
            @endif

            {{-- Variants --}}
            @if($product->variants->count() > 0)
            <div class="mb-3"><label class="fw-semibold small">Varian:</label>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($product->variants as $v)
                    <span class="badge bg-light text-dark border px-3 py-2">{{ $v->variant }} @if($v->price != $product->price)<span class="text-primary">Rp {{ number_format($v->price,0,',','.') }}</span>@endif · Stok: {{ $v->stock }}</span>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Info Cards --}}
            <div class="row g-2 mb-3 small">
                <div class="col-4"><div class="bg-light rounded-3 p-2 text-center"><div class="text-muted">Stok</div><span class="fw-bold">{{ $product->current_stock }}</span></div></div>
                <div class="col-4"><div class="bg-light rounded-3 p-2 text-center"><div class="text-muted">Terjual</div><span class="fw-bold">{{ $product->orderItems->sum('quantity') }}</span></div></div>
                <div class="col-4"><div class="bg-light rounded-3 p-2 text-center"><div class="text-muted">SKU</div><span class="fw-bold small">{{ $product->sku ?? '-' }}</span></div></div>
                @if($product->brand)<div class="col-4"><div class="bg-light rounded-3 p-2 text-center"><div class="text-muted">Brand</div><span class="fw-bold small">{{ $product->brand->name }}</span></div></div>@endif
                <div class="col-4"><div class="bg-light rounded-3 p-2 text-center"><div class="text-muted">Tipe</div><span class="fw-bold small">{{ $product->product_type === 'digital' ? 'Digital' : 'Fisik' }}</span></div></div>
                <div class="col-4"><div class="bg-light rounded-3 p-2 text-center"><div class="text-muted">Satuan</div><span class="fw-bold small">{{ $product->unit ?? 'pcs' }}</span></div></div>
            </div>

            {{-- Tags --}}
            @if($product->tags->count() > 0)
            <div class="mb-3">@foreach($product->tags as $tag)<span class="badge bg-secondary-subtle text-secondary me-1 mb-1">{{ $tag->name }}</span>@endforeach</div>
            @endif

            {{-- Actions --}}
            @auth
            <div class="d-flex gap-2 align-items-end flex-wrap">
                <form action="{{ route('cart.add') }}" method="POST" class="d-flex gap-2 align-items-end">@csrf<input type="hidden" name="product_id" value="{{ $product->id }}"><div><label class="small fw-medium">Jumlah</label><input type="number" name="quantity" class="form-control" value="1" min="1" max="{{ $product->max_qty }}" style="width:80px;"></div><button class="btn btn-primary btn-lg px-4"><i class="fas fa-shopping-cart me-2"></i>Keranjang</button></form>
                <form action="{{ route('wishlist.toggle') }}" method="POST">@csrf<input type="hidden" name="product_id" value="{{ $product->id }}"><button class="btn btn-outline-danger btn-lg"><i class="fas fa-heart"></i></button></form>
                <form action="{{ route('compare.add') }}" method="POST">@csrf<input type="hidden" name="product_id" value="{{ $product->id }}"><button class="btn btn-outline-info btn-lg"><i class="fas fa-balance-scale"></i></button></form>
                @if($product->current_stock == 0)
                <form action="{{ route('restock.request') }}" method="POST">@csrf<input type="hidden" name="product_id" value="{{ $product->id }}"><button class="btn btn-outline-warning"><i class="fas fa-bell me-1"></i>Kasih tahu kalau sudah ada</button></form>
                @endif
                <div class="mt-2"><form action="{{ route('alerts.set') }}" method="POST" class="d-flex gap-1">@csrf<input type="hidden" name="product_id" value="{{ $product->id }}"><input type="number" name="target_price" class="form-control form-control-sm" style="width:150px;" placeholder="Harga target" min="0"><button class="btn btn-outline-secondary btn-sm"><i class="fas fa-bell me-1"></i>Alert Harga</button></form></div>
            </div>
            @else
            <a href="{{ route('login') }}" class="btn btn-primary btn-lg"><i class="fas fa-sign-in-alt me-2"></i>Masuk untuk Beli</a>
            @endauth
        </div>
    </div>

    {{-- Description --}}
    @if($product->description)
    <div class="card border-0 shadow-sm rounded-4 mt-4"><div class="card-body p-4"><h5 class="fw-bold mb-3"><i class="fas fa-align-left me-2"></i>Deskripsi</h5><div class="lh-lg">{!! $product->description !!}</div></div></div>
    @endif

    {{-- Reviews --}}
    <div class="card border-0 shadow-sm rounded-4 mt-4"><div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3"><h5 class="fw-bold mb-0"><i class="fas fa-star me-2 text-warning"></i>Ulasan ({{ $product->reviews->count() }})</h5></div>
        @auth
        <form action="{{ route('reviews.store') }}" method="POST" class="mb-3 bg-light rounded-3 p-3">@csrf<input type="hidden" name="product_id" value="{{ $product->id }}"><div class="row g-2 align-items-end"><div class="col-auto"><select name="rating" class="form-select form-select-sm"><option>5</option><option>4</option><option>3</option><option>2</option><option>1</option></select></div><div class="col"><input type="text" name="comment" class="form-control form-control-sm" placeholder="Tulis ulasan..."></div><div class="col-auto"><button class="btn btn-primary btn-sm"><i class="fas fa-paper-plane"></i></button></div></div></form>
        @endauth
        @forelse($product->reviews->where('status',true) as $r)
        <div class="border-bottom pb-3 mb-3"><div class="d-flex justify-content-between"><span class="fw-semibold">{{ $r->customer->name ?? 'Anonim' }}</span><span class="text-warning">@for($i=1;$i<=5;$i++)<i class="fas fa-star{{ $i<=$r->rating?'':'-o' }}"></i>@endfor</span> <small class="text-muted">{{ $r->created_at->format('d M Y') }}</small></div><p class="mb-0 mt-1 small">{{ $r->comment }}</p></div>
        @empty<p class="text-muted text-center py-3">Belum ada ulasan. Jadilah yang pertama!</p>@endforelse
    </div></div>

    {{-- Related --}}
    @if($relatedProducts->count() > 0)
    <div class="mt-5"><h5 class="fw-bold mb-3">Produk Terkait</h5><div class="row g-3">@foreach($relatedProducts as $rp)<div class="col-6 col-md-3">@include('storefront.products._card', ['product' => $rp])</div>@endforeach</div></div>
    @endif
</div>
@endsection
