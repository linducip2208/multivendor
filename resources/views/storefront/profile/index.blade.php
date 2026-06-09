@extends('layouts.storefront')
@section('title','Profil Saya')
@section('content')
<div class="container" style="max-width:700px;">
<h4 class="fw-bold mb-4"><i class="fas fa-user-circle me-2 text-primary"></i> Profil Saya</h4>
<div class="row g-4"><div class="col-md-6"><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h6 class="fw-bold mb-3">Edit Profil</h6><form action="{{ route('profile.update') }}" method="POST">@csrf @method('PUT')<div class="mb-2"><label class="small fw-medium">Nama</label><input type="text" name="name" class="form-control form-control-sm" value="{{ auth()->user()->name }}" required></div><div class="mb-2"><label class="small fw-medium">No HP</label><input type="text" name="phone" class="form-control form-control-sm" value="{{ auth()->user()->phone }}"></div><div class="mb-2"><label class="small fw-medium">Password Baru</label><input type="password" name="password" class="form-control form-control-sm"></div><div class="mb-2"><label class="small fw-medium">Konfirmasi</label><input type="password" name="password_confirmation" class="form-control form-control-sm"></div><button class="btn btn-primary btn-sm">Simpan</button></form></div></div>
<div class="col-md-6"><div class="card border-0 shadow-sm rounded-4 mb-3"><div class="card-body p-3"><h6 class="fw-bold mb-2">Wallet</h6><span class="fw-bold fs-5 text-success">Rp {{ number_format(auth()->user()->wallet->balance ?? 0,0,',','.') }}</span></div></div>
<div class="card border-0 shadow-sm rounded-4 mb-3"><div class="card-body p-3"><h6 class="fw-bold mb-2">Referral Code</h6><code class="fs-6">{{ auth()->user()->referral_code }}</code><br><small class="text-muted">Bagikan kode ini ke teman, dapatkan 500 poin!</small></div></div></div></div>
<div class="card border-0 shadow-sm rounded-4 mt-4"><div class="card-body p-4"><h6 class="fw-bold mb-3">Alamat</h6>
<form action="{{ route('profile.address.store') }}" method="POST" class="row g-2 mb-3">@csrf
<div class="col-md-3"><input type="text" name="label" class="form-control form-control-sm" placeholder="Rumah/Kantor" required></div><div class="col-md-3"><input type="text" name="receiver_name" class="form-control form-control-sm" placeholder="Nama penerima" required></div><div class="col-md-3"><input type="text" name="receiver_phone" class="form-control form-control-sm" placeholder="No HP" required></div><div class="col-md-5"><input type="text" name="address" class="form-control form-control-sm" placeholder="Alamat lengkap" required></div><div class="col-md-3"><input type="text" name="city" class="form-control form-control-sm" placeholder="Kota" required></div><div class="col-md-3"><input type="text" name="province" class="form-control form-control-sm" placeholder="Provinsi" required></div><div class="col-md-3"><input type="text" name="postal_code" class="form-control form-control-sm" placeholder="Kode pos"></div><div class="col-md-3"><button class="btn btn-primary btn-sm w-100">Tambah Alamat</button></div>
</form>
@foreach(auth()->user()->addresses as $a)
<div class="border rounded-3 p-2 mb-2 d-flex justify-content-between align-items-center"><div><span class="fw-semibold small">{{ $a->label }}</span> — {{ $a->receiver_name }} ({{ $a->receiver_phone }})<br><small class="text-muted">{{ $a->address }}, {{ $a->city }}, {{ $a->province }}</small></div><form action="{{ route('profile.address.destroy',$a) }}" method="POST">@csrf @method('DELETE')<button class="btn btn-sm text-danger"><i class="fas fa-trash"></i></button></form></div>
@endforeach
</div></div></div>
</div>
@endsection
