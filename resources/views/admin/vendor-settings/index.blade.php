@extends('layouts.admin')
@section('title','Vendor Registration Settings')
@section('content')
<h4 class="fw-bold mb-3"><i class="fas fa-store-alt me-2 text-success"></i> Vendor Registration</h4>
<div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
<form action="{{ route('admin.vendor-settings.update') }}" method="POST">@csrf @method('PUT')
<div class="row g-3">
    <div class="col-md-6"><div class="form-check form-switch"><input type="checkbox" name="registration_open" class="form-check-input" value="1" {{ \App\Models\SystemSetting::get('vendor_registration_open','1')?'checked' : '' }}><label class="fw-medium">Buka Pendaftaran Vendor</label></div></div>
    <div class="col-md-6"><label class="fw-medium">Komisi Default (%)</label><input type="number" name="default_commission" class="form-control" value="{{ \App\Models\SystemSetting::get('vendor_default_commission','5') }}" step="0.01" min="0" max="100"></div>
    <div class="col-md-6"><label class="fw-medium">Minimal Withdraw (Rp)</label><input type="number" name="min_withdraw" class="form-control" value="{{ \App\Models\SystemSetting::get('vendor_min_withdraw','50000') }}" min="0"></div>
    <div class="col-md-6"><label class="fw-medium">Auto-Approve Vendor</label><select name="auto_approve" class="form-select"><option value="0" {{ \App\Models\SystemSetting::get('vendor_auto_approve')!=='1'?'selected' : '' }}>Manual (Admin review)</option><option value="1" {{ \App\Models\SystemSetting::get('vendor_auto_approve')==='1'?'selected' : '' }}>Auto</option></select></div>
    <div class="col-12"><button class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan</button></div>
</div></form></div></div>
@endsection
