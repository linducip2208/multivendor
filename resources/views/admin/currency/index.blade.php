@extends('layouts.admin')
@section('title', 'Mata Uang')
@section('content')
<h4 class="fw-bold mb-3"><i class="fas fa-dollar-sign me-2 text-success"></i> Mata Uang</h4>
<div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4"><form action="{{ route('admin.currency.update') }}" method="POST">@csrf @method('PUT')
<div class="row g-3">
    <div class="col-md-4"><label class="fw-medium">Kode Mata Uang</label><input type="text" name="currency_code" class="form-control" value="{{ old('currency_code', \App\Models\SystemSetting::get('currency_code','IDR')) }}" maxlength="3"></div>
    <div class="col-md-4"><label class="fw-medium">Simbol</label><input type="text" name="currency_symbol" class="form-control" value="{{ old('currency_symbol', \App\Models\SystemSetting::get('currency_symbol','Rp')) }}" maxlength="5"></div>
    <div class="col-md-4"><label class="fw-medium">Posisi Simbol</label><select name="symbol_position" class="form-select"><option value="left" {{ \App\Models\SystemSetting::get('symbol_position')==='left'?'selected' : '' }}>Kiri (Rp 100.000)</option><option value="right" {{ \App\Models\SystemSetting::get('symbol_position')==='right'?'selected' : '' }}>Kanan (100.000 Rp)</option></select></div>
    <div class="col-md-4"><label class="fw-medium">Desimal</label><input type="number" name="decimal_point" class="form-control" value="{{ old('decimal_point', \App\Models\SystemSetting::get('decimal_point','0')) }}" min="0" max="4"></div>
    <div class="col-12"><button class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan</button></div>
</div></form></div></div>
@endsection
