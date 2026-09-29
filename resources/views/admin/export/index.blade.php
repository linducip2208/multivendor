@extends('layouts.admin')
@section('title', 'Export Laporan')
@section('content')
<h4 class="fw-bold mb-3"><x-admin.icon name="file-text" :size="16" class="me-2 text-danger" /> Export Laporan</h4>
<div class="row g-4">
    <div class="col-md-3"><a href="{{ route('admin.export.products') }}" class="card border-0 rounded-4 shadow-sm p-4 text-center text-decoration-none" style="color:inherit;"><x-admin.icon name="box" :size="48" class="text-success mb-2" /><h6 class="fw-bold">Produk</h6><small class="text-muted">Export CSV</small></a></div>
    <div class="col-md-3"><a href="{{ route('admin.export.orders') }}" class="card border-0 rounded-4 shadow-sm p-4 text-center text-decoration-none" style="color:inherit;"><x-admin.icon name="shopping-cart" :size="48" class="text-primary mb-2" /><h6 class="fw-bold">Pesanan</h6><small class="text-muted">Export CSV</small></a></div>
    <div class="col-md-3"><a href="{{ route('admin.export.customers') }}" class="card border-0 rounded-4 shadow-sm p-4 text-center text-decoration-none" style="color:inherit;"><x-admin.icon name="users" :size="48" class="text-info mb-2" /><h6 class="fw-bold">Pelanggan</h6><small class="text-muted">Export CSV</small></a></div>
    <div class="col-md-3"><a href="{{ route('admin.export.transactions') }}" class="card border-0 rounded-4 shadow-sm p-4 text-center text-decoration-none" style="color:inherit;"><x-admin.icon name="wallet" :size="48" class="text-warning mb-2" /><h6 class="fw-bold">Transaksi</h6><small class="text-muted">Export CSV</small></a></div>
</div>
@endsection
