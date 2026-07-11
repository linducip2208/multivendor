@extends('layouts.admin')
@section('title', 'Subscription Plans')
@section('content')
<div class="mb-4 d-flex justify-content-between"><h4 class="fw-bold"><i class="fas fa-crown me-2"></i>Paket Langganan Vendor</h4><a href="{{ route('admin.subscriptions.index') }}" class="btn btn-outline-secondary btn-sm">Daftar Langganan</a></div>
<div class="card border-0 rounded-4 shadow-sm mb-4" style="max-width:600px">
    <div class="card-body">
        <h6 class="fw-bold">Tambah Paket</h6>
        <form method="POST" action="{{ route('admin.subscriptions.plans.store') }}">
            @csrf
            <div class="row mb-2"><div class="col-6"><label class="small">Nama</label><input type="text" name="name" class="form-control form-control-sm" required></div><div class="col-6"><label class="small">Slug</label><input type="text" name="slug" class="form-control form-control-sm" required></div></div>
            <div class="row mb-2"><div class="col-4"><label class="small">Harga</label><input type="number" name="price" class="form-control form-control-sm" required></div><div class="col-4"><label class="small">Periode</label><select name="billing_period" class="form-select form-select-sm"><option>monthly</option><option>yearly</option><option>lifetime</option></select></div><div class="col-4"><label class="small">Komisi (%)</label><input type="number" name="commission_rate" class="form-control form-control-sm" value="0" step="0.1"></div></div>
            <div class="row mb-2"><div class="col"><label class="small">Max Produk</label><input type="number" name="max_products" class="form-control form-control-sm" value="0"></div><div class="col"><label class="small">Sort Order</label><input type="number" name="sort_order" class="form-control form-control-sm" value="0"></div></div>
            <div class="mb-2"><label class="small">Deskripsi</label><textarea name="description" class="form-control form-control-sm" rows="2"></textarea></div>
            <div class="form-check form-check-inline small"><input type="checkbox" name="can_chat" class="form-check-input" value="1"><label>Chat</label></div>
            <div class="form-check form-check-inline small"><input type="checkbox" name="can_export" class="form-check-input" value="1"><label>Export</label></div>
            <div class="form-check form-check-inline small"><input type="checkbox" name="can_bulk_import" class="form-check-input" value="1"><label>Bulk Import</label></div>
            <div class="form-check form-check-inline small"><input type="checkbox" name="can_pos" class="form-check-input" value="1"><label>POS</label></div>
            <div class="form-check form-check-inline small"><input type="checkbox" name="can_barcode" class="form-check-input" value="1"><label>Barcode</label></div>
            <div class="form-check form-check-inline small"><input type="checkbox" name="featured_shop" class="form-check-input" value="1"><label>Featured</label></div>
            <div class="mt-2"><button type="submit" class="btn btn-primary btn-sm">Tambah</button></div>
        </form>
    </div>
</div>
<div class="row g-3">
    @foreach($plans as $plan)
    <div class="col-md-4">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between"><h5 class="fw-bold">{{ $plan->name }}</h5><span class="badge bg-{{ $plan->is_active ? 'success' : 'secondary' }}-subtle">{{ $plan->is_active ? 'Aktif' : 'Nonaktif' }}</span></div>
                <div class="fs-3 fw-bold text-primary mb-2">Rp {{ number_format($plan->price,0,',','.') }}<small class="fs-6 fw-normal text-muted">/{{ $plan->billing_period }}</small></div>
                <p class="small text-muted">{{ $plan->description }}</p>
                <ul class="small mb-3">
                    <li>Max {{ $plan->max_products ?: 'Unlimited' }} produk</li>
                    <li>Komisi {{ $plan->commission_rate }}%</li>
                    @if($plan->can_chat)<li class="text-success">Chat custom</li>@endif
                    @if($plan->can_pos)<li class="text-success">POS</li>@endif
                    @if($plan->can_bulk_import)<li class="text-success">Bulk Import</li>@endif
                    @if($plan->featured_shop)<li class="text-success">Featured Shop</li>@endif
                </ul>
                <form method="POST" action="{{ route('admin.subscriptions.plans.destroy', $plan) }}" onsubmit="return confirm('Hapus paket ini?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger w-100">Hapus</button>
                </form>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endsection
