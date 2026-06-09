@extends('layouts.admin')
@section('title', 'Export Laporan')
@section('content')
<h4 class="fw-bold mb-3"><i class="fas fa-file-pdf me-2 text-danger"></i> Export Laporan</h4>
<div class="row g-4">
    <div class="col-md-3"><a href="{{ route('admin.export.products') }}" class="card border-0 rounded-4 shadow-sm p-4 text-center text-decoration-none" style="color:inherit;"><i class="fas fa-box fa-3x text-success mb-2"></i><h6 class="fw-bold">Produk</h6><small class="text-muted">Export CSV</small></a></div>
    <div class="col-md-3"><a href="{{ route('admin.export.orders') }}" class="card border-0 rounded-4 shadow-sm p-4 text-center text-decoration-none" style="color:inherit;"><i class="fas fa-shopping-cart fa-3x text-primary mb-2"></i><h6 class="fw-bold">Pesanan</h6><small class="text-muted">Export CSV</small></a></div>
    <div class="col-md-3"><a href="{{ route('admin.export.customers') }}" class="card border-0 rounded-4 shadow-sm p-4 text-center text-decoration-none" style="color:inherit;"><i class="fas fa-users fa-3x text-info mb-2"></i><h6 class="fw-bold">Pelanggan</h6><small class="text-muted">Export CSV</small></a></div>
    <div class="col-md-3"><a href="{{ route('admin.export.transactions') }}" class="card border-0 rounded-4 shadow-sm p-4 text-center text-decoration-none" style="color:inherit;"><i class="fas fa-money-bill-wave fa-3x text-warning mb-2"></i><h6 class="fw-bold">Transaksi</h6><small class="text-muted">Export CSV</small></a></div>
</div>
@endsection
