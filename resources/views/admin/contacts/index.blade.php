@extends('layouts.admin')
@section('title','Contacts')
@section('content')
<h4 class="fw-bold mb-3"><i class="fas fa-address-book me-2 text-primary"></i> Kontak</h4>
<div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
<form action="{{ route('admin.contacts.update') }}" method="POST">@csrf @method('PUT')
<div class="row g-3">
    <div class="col-md-4"><label class="fw-medium">Alamat</label><textarea name="address" class="form-control" rows="2">{{ \App\Models\SystemSetting::get('contact_address') }}</textarea></div>
    <div class="col-md-4"><label class="fw-medium">Email</label><input type="email" name="email" class="form-control" value="{{ \App\Models\SystemSetting::get('contact_email') }}"></div>
    <div class="col-md-4"><label class="fw-medium">No. Telepon</label><input type="text" name="phone" class="form-control" value="{{ \App\Models\SystemSetting::get('contact_phone') }}"></div>
    <div class="col-md-4"><label class="fw-medium">WhatsApp</label><input type="text" name="whatsapp" class="form-control" value="{{ \App\Models\SystemSetting::get('contact_whatsapp') }}"></div>
    <div class="col-md-4"><label class="fw-medium">Facebook</label><input type="text" name="facebook" class="form-control" value="{{ \App\Models\SystemSetting::get('contact_facebook') }}"></div>
    <div class="col-md-4"><label class="fw-medium">Instagram</label><input type="text" name="instagram" class="form-control" value="{{ \App\Models\SystemSetting::get('contact_instagram') }}"></div>
    <div class="col-12"><button class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan</button></div>
</div></form></div></div>
@endsection
