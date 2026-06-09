@extends('layouts.storefront')
@section('title', 'Checkout')

@section('content')
<div class="container">
    <h4 class="fw-bold mb-4"><i class="fas fa-credit-card me-2 text-success"></i> Checkout</h4>

    <form action="{{ route('checkout.process') }}" method="POST">
        @csrf
        <div class="row g-4">
            <div class="col-lg-8">
                @if($addresses->count() > 0)
                <div class="card border-0 shadow-sm rounded-4 mb-3">
                    <div class="card-header bg-transparent border-0 pt-3"><h6 class="fw-bold mb-0"><i class="fas fa-map-marker-alt me-2"></i>Alamat Pengiriman</h6></div>
                    <div class="card-body">
                        @foreach($addresses as $addr)
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="address_id" value="{{ $addr->id }}" id="addr{{ $addr->id }}" {{ $addr->is_default ? 'checked' : '' }} required>
                            <label class="form-check-label" for="addr{{ $addr->id }}">
                                <span class="fw-semibold">{{ $addr->label }}</span> — {{ $addr->receiver_name }} ({{ $addr->receiver_phone }})<br>
                                <small class="text-muted">{{ $addr->address }}, {{ $addr->city }}, {{ $addr->province }} {{ $addr->postal_code }}</small>
                            </label>
                        </div>
                        @endforeach
                    </div>
                </div>
                @else
                <div class="alert alert-warning">Anda belum punya alamat. <a href="#">Tambah alamat</a></div>
                @endif

                <div class="card border-0 shadow-sm rounded-4 mb-3">
                    <div class="card-header bg-transparent border-0 pt-3"><h6 class="fw-bold mb-0"><i class="fas fa-box me-2"></i>Pesanan</h6></div>
                    <div class="card-body p-0">
                        @foreach($shops as $shopData)
                        <div class="p-3 border-bottom">
                            <div class="fw-semibold small mb-2"><i class="fas fa-store text-muted me-1"></i> {{ $shopData['shop']->name }}</div>
                            @foreach($shopData['items'] as $item)
                            <div class="d-flex justify-content-between small mb-1">
                                <span>{{ $item->product->name }} ×{{ $item->quantity }}</span>
                                <span>Rp {{ number_format($item->price * $item->quantity, 0, ',', '.') }}</span>
                            </div>
                            @endforeach
                        </div>
                        @endforeach
                    </div>
                    <div class="card-footer bg-transparent text-end fw-bold">Total: Rp {{ number_format($total, 0, ',', '.') }}</div>
                </div>

                @if($shippingProviders->count() > 0)
                <div class="card border-0 shadow-sm rounded-4 mb-3">
                    <div class="card-header bg-transparent border-0 pt-3"><h6 class="fw-bold mb-0"><i class="fas fa-truck me-2"></i>Pengiriman</h6></div>
                    <div class="card-body">
                        @foreach($shops as $shopId => $shopData)
                        <div class="mb-2">
                            <label class="small fw-semibold">{{ $shopData['shop']->name }}</label>
                            <select name="shipping_methods[{{ $shopId }}][service]" class="form-select form-select-sm">
                                <option value="">Pilih kurir</option>
                                @foreach($shippingProviders as $sp)
                                <option value="{{ $sp->name }} - Reguler">{{ $sp->name }} Reguler - Rp 15.000</option>
                                @endforeach
                            </select>
                            <input type="hidden" name="shipping_methods[{{ $shopId }}][cost]" value="15000">
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <div class="card border-0 shadow-sm rounded-4 mb-3">
                    <div class="card-header bg-transparent border-0 pt-3"><h6 class="fw-bold mb-0"><i class="fas fa-ticket-alt me-2"></i>Kupon</h6></div>
                    <div class="card-body">
                        <div class="input-group"><input type="text" name="coupon_code" class="form-control" placeholder="Masukkan kode kupon"><button type="button" class="btn btn-outline-primary">Pakai</button></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3"><i class="fas fa-credit-card me-2"></i>Pembayaran</h6>
                        @if($paymentGateways->count() > 0)
                            @foreach($paymentGateways as $pg)
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="payment_provider_id" value="{{ $pg->id }}" id="pay{{ $pg->id }}" required>
                                <label class="form-check-label" for="pay{{ $pg->id }}">{{ $pg->name }} <small class="text-muted">({{ $pg->api_format }})</small></label>
                            </div>
                            @endforeach
                        @else
                            <div class="alert alert-info small">Belum ada payment gateway. Admin harus menambahkan provider di menu Integrasi.</div>
                        @endif
                        <hr>
                        <div class="mb-3">
                            <label class="small fw-medium">Catatan</label>
                            <textarea name="note" class="form-control form-control-sm" rows="2"></textarea>
                        </div>
                        <button type="submit" class="btn btn-success w-100 btn-lg"><i class="fas fa-lock me-2"></i> Bayar Sekarang</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
