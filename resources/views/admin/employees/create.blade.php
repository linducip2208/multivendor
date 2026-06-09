@extends('layouts.admin')
@section('title','Tambah Employee')
@section('content')
<div class="mb-4"><a href="{{ route('admin.employees.index') }}" class="small"><i class="fas fa-arrow-left me-1"></i>Kembali</a><h4 class="fw-bold mt-2">Tambah Employee</h4></div>
<div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4"><form action="{{ route('admin.employees.store') }}" method="POST">@csrf
<div class="row g-3"><div class="col-md-4"><label class="fw-medium">Nama</label><input type="text" name="name" class="form-control" required></div><div class="col-md-4"><label class="fw-medium">Email</label><input type="email" name="email" class="form-control" required></div><div class="col-md-4"><label class="fw-medium">Role</label><select name="role" class="form-select"><option value="employee">Employee</option><option value="admin">Admin</option></select></div><div class="col-md-4"><label class="fw-medium">Password</label><input type="password" name="password" class="form-control" required></div><div class="col-12"><button class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan</button></div></div></form></div></div>
@endsection
