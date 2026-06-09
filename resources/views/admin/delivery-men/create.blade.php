@extends('layouts.admin')
@section('title', 'Tambah Kurir')
@section('content')
<div class="mb-4"><a href="{{ route('admin.delivery-men.index') }}" class="small"><i class="fas fa-arrow-left me-1"></i>Kembali</a><h4 class="fw-bold mt-2">Tambah Kurir</h4></div>
<div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4"><form action="{{ route('admin.delivery-men.store') }}" method="POST">@csrf
<div class="row g-3"><div class="col-md-4"><label class="fw-medium">Nama <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required></div><div class="col-md-4"><label class="fw-medium">Email <span class="text-danger">*</span></label><input type="email" name="email" class="form-control" required></div><div class="col-md-4"><label class="fw-medium">No HP <span class="text-danger">*</span></label><input type="text" name="phone" class="form-control" required></div><div class="col-md-4"><label class="fw-medium">Password <span class="text-danger">*</span></label><input type="password" name="password" class="form-control" required></div><div class="col-12"><button class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan</button></div></div></form></div></div>
@endsection
