@extends('layouts.admin')
@section('title', 'Tax Settings')
@section('content')
<div class="mb-4"><h4 class="fw-bold"><i class="fas fa-cog me-2"></i>Pengaturan Pajak</h4></div>
<div class="card border-0 rounded-4 shadow-sm" style="max-width:600px">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.tax-report.settings.update') }}">
            @csrf @method('PUT')
            <div class="mb-3"><label class="form-label fw-medium">NPWP Perusahaan</label><input type="text" name="tax_number" class="form-control" value="{{ \App\Models\SystemSetting::get('tax_number') }}"></div>
            <div class="mb-3"><label class="form-label fw-medium">Nama Perusahaan</label><input type="text" name="tax_company_name" class="form-control" value="{{ \App\Models\SystemSetting::get('tax_company_name') }}"></div>
            <div class="mb-3"><label class="form-label fw-medium">Alamat</label><textarea name="tax_address" class="form-control" rows="3">{{ \App\Models\SystemSetting::get('tax_address') }}</textarea></div>
            <div class="mb-3"><label class="form-label fw-medium">Tarif Pajak Default (%)</label><input type="number" name="tax_default_rate" class="form-control" value="{{ \App\Models\SystemSetting::get('tax_default_rate', '11') }}" step="0.1"></div>
            <div class="mb-3"><div class="form-check"><input type="checkbox" name="tax_include_in_price" class="form-check-input" value="1" {{ \App\Models\SystemSetting::get('tax_include_in_price') ? 'checked' : '' }}><label class="form-check-label">Harga sudah termasuk pajak</label></div></div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan</button>
        </form>
    </div>
</div>
@endsection
