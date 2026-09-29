@extends('layouts.admin')
@section('title', 'Tambah Kurir')
@section('content')
<div class="mb-4"><a href="{{ route('admin.delivery-men.index') }}" class="small"><x-admin.icon name="arrow-left" :size="16" class="me-1" />Kembali</a><h4 class="fw-bold mt-2">Tambah Kurir</h4></div>
<x-admin.card :padding="false"><div class="card-body p-4"><form action="{{ route('admin.delivery-men.store') }}" method="POST">@csrf
<div class="row g-3"><div class="col-md-4"><label class="fw-medium">Nama <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required></div><div class="col-md-4"><label class="fw-medium">Email <span class="text-danger">*</span></label><input type="email" name="email" class="form-control" required></div><div class="col-md-4"><label class="fw-medium">No HP <span class="text-danger">*</span></label><input type="text" name="phone" class="form-control" required></div><div class="col-md-4"><label class="fw-medium">Password <span class="text-danger">*</span></label><input type="password" name="password" class="form-control" required></div><div class="col-12"><button class="btn btn-primary"><x-admin.icon name="check" :size="16" class="me-2" />Simpan</button></div></div></form></div></x-admin.card>
@endsection
