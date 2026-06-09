@extends('layouts.storefront')
@section('title', 'Group Buy — Beli Bareng Diskon Besar')
@section('content')
<div class="container">
    <h4 class="fw-bold mb-4"><i class="fas fa-users me-2 text-success"></i> Group Buy — Beli Bareng, Diskon Lebih Besar</h4>
    <div class="row g-4">
        @forelse($groups as $g)
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <div class="d-flex gap-3 align-items-start">
                        <div class="bg-light rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:80px;height:80px;">
                            @if($g->product->thumbnail)
                                @php $img = str_starts_with($g->product->thumbnail,'http') ? $g->product->thumbnail : asset('storage/'.$g->product->thumbnail); @endphp
                                <img src="{{ $img }}" style="width:80px;height:80px;object-fit:contain;" class="rounded-3">
                            @else
                                <i class="fas fa-box fa-2x text-muted opacity-25"></i>
                            @endif
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="fw-bold">{{ $g->product->name }}</h6>
                            <small class="text-muted">{{ $g->product->shop->name ?? '' }}</small>
                            <div class="mt-2">
                                <span class="text-muted text-decoration-line-through">Rp {{ number_format($g->product->price,0,',','.') }}</span>
                                <span class="fw-bold text-success fs-5 ms-2">Rp {{ number_format($g->special_price,0,',','.') }}</span>
                                <span class="badge bg-danger ms-2">-{{ $g->discount_percentage }}%</span>
                            </div>
                            <div class="mt-2">
                                <small class="text-muted">Terkumpul: {{ $g->current_count }} / {{ $g->target_count }} peserta</small>
                                <div class="progress mt-1" style="height:8px;">
                                    <div class="progress-bar bg-success" style="width:{{ min(100, ($g->current_count / $g->target_count) * 100) }}%"></div>
                                </div>
                            </div>
                            <div class="mt-2 d-flex justify-content-between align-items-center">
                                <small class="text-muted">Berakhir: {{ $g->end_date->format('d M Y H:i') }}</small>
                                @auth
                                <form action="{{ route('group-buys.join', $g) }}" method="POST">@csrf<button class="btn btn-success btn-sm"><i class="fas fa-user-plus me-1"></i>Ikut</button></form>
                                @endauth
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12 empty-state">
            <i class="fas fa-users"></i>
            <h5>Belum ada Group Buy</h5>
            <p class="text-muted">Group buy akan muncul saat admin membuat campaign beli bareng.</p>
            <a href="{{ route('products.index') }}" class="btn btn-primary">Lihat Produk</a>
        </div>
        @endforelse
    </div>
    {{ $groups->links() }}
</div>
@endsection
