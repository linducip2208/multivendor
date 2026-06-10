@extends('layouts.storefront')
@section('title', 'Keranjang')

@section('content')
<div class="container">
    <h4 class="fw-bold mb-4"><i class="fas fa-shopping-cart me-2 text-primary"></i> Keranjang Belanja</h4>

    @if(count($shops) > 0)
    <div class="row g-4">
        <div class="col-lg-8">
            @foreach($shops as $shopData)
            <div class="card border-0 shadow-sm rounded-4 mb-3">
                <div class="card-header bg-transparent border-0 pt-3 px-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-store text-muted"></i>
                        <span class="fw-semibold">{{ $shopData['shop']->name }}</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    @foreach($shopData['items'] as $item)
                    <div class="d-flex align-items-center gap-3 p-3 border-bottom">
                        <div class="bg-light rounded-3 d-flex align-items-center justify-content-center" style="width:64px;height:64px;">
                            @if($item->product->thumbnail)<img src="{{ url('img/'.$item->product->thumbnail) }}" class="rounded-3" style="width:64px;height:64px;object-fit:contain;">@else<i class="fas fa-box text-muted"></i>@endif
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-semibold small">{{ $item->product->name }}</div>
                            @if($item->variant)<small class="text-muted">{{ $item->variant->variant }}</small>@endif
                            <div class="fw-bold text-primary small">Rp {{ number_format($item->price, 0, ',', '.') }}</div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <form action="{{ route('cart.update', $item) }}" method="POST" class="d-flex align-items-center gap-2">
                                @csrf @method('PUT')
                                <input type="number" name="quantity" class="form-control form-control-sm" value="{{ $item->quantity }}" min="1" style="width:60px;" onchange="this.form.submit()">
                            </form>
                            <form action="{{ route('cart.remove', $item) }}" method="POST">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm text-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="card-footer bg-transparent text-end">
                    <small>Subtotal: <span class="fw-bold">Rp {{ number_format($shopData['subtotal'], 0, ',', '.') }}</span></small>
                </div>
            </div>
            @endforeach
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3">Ringkasan Belanja</h6>
                    <div class="d-flex justify-content-between mb-2"><span>Total ({{ collect($shops)->sum(fn($s) => $s['items']->count()) }} item)</span><span class="fw-bold fs-5 text-primary">Rp {{ number_format($total, 0, ',', '.') }}</span></div>
                    <hr>
                    <a href="{{ route('checkout.index') }}" class="btn btn-primary w-100 btn-lg"><i class="fas fa-credit-card me-2"></i> Checkout</a>
                    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary w-100 mt-2">Lanjut Belanja</a>
                </div>
            </div>
        </div>
    </div>
    @else
    <div class="text-center py-5">
        <i class="fas fa-shopping-cart fa-4x text-muted mb-3 opacity-25"></i>
        <h5>Keranjang kosong</h5>
        <a href="{{ route('products.index') }}" class="btn btn-primary mt-2">Lihat Produk</a>
    </div>
    @endif
</div>
@endsection
