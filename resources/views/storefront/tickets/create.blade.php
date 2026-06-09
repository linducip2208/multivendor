@extends('layouts.storefront')
@section('title', 'Buat Tiket')
@section('content')
<div class="container" style="max-width:600px;">
<h4 class="fw-bold mb-4"><i class="fas fa-plus me-2"></i> Buat Tiket Baru</h4>
<div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><form method="POST" action="{{ route('tickets.store') }}">@csrf
<div class="mb-3"><label class="fw-medium">Subjek <span class="text-danger">*</span></label><input type="text" name="subject" class="form-control" required></div>
<div class="row g-2 mb-3"><div class="col-6"><label class="fw-medium">Tipe</label><select name="type" class="form-select"><option value="order">Pesanan</option><option value="product">Produk</option><option value="payment">Pembayaran</option><option value="account">Akun</option><option value="other">Lainnya</option></select></div><div class="col-6"><label class="fw-medium">Prioritas</label><select name="priority" class="form-select"><option value="low">Rendah</option><option value="medium" selected>Sedang</option><option value="high">Tinggi</option><option value="urgent">Urgent</option></select></div></div>
<div class="mb-3"><label class="fw-medium">Deskripsi <span class="text-danger">*</span></label><textarea name="description" class="form-control" rows="6" required></textarea></div>
<button class="btn btn-primary w-100"><i class="fas fa-paper-plane me-2"></i>Kirim Tiket</button>
</form></div></div>
</div>
@endsection
