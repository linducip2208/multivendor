@extends('layouts.vendor')
@section('title', 'Onboarding - Step 2')
@section('content')
<div style="max-width:500px;margin:0 auto">
    <div class="text-center mb-4"><div class="mb-2"><span class="badge bg-primary">Step 2/4</span></div><h4 class="fw-bold">Info Pembayaran</h4><p class="text-muted small">Data rekening untuk pencairan dana</p></div>
    <form method="POST" action="{{ route('vendor.onboarding.step2.store') }}">
        @csrf
        <div class="mb-3"><label class="form-label fw-medium">Nama Bank</label><input type="text" name="bank_name" class="form-control" value="{{ old('bank_name', $shop->bank_name) }}" required></div>
        <div class="mb-3"><label class="form-label fw-medium">No Rekening</label><input type="text" name="bank_account_number" class="form-control" value="{{ old('bank_account_number', $shop->bank_account_number) }}" required></div>
        <div class="mb-3"><label class="form-label fw-medium">Nama Pemilik Rekening</label><input type="text" name="bank_account_name" class="form-control" value="{{ old('bank_account_name', $shop->bank_account_name) }}" required></div>
        <div class="mb-3"><label class="form-label fw-medium">NPWP (opsional)</label><input type="text" name="tin" class="form-control" value="{{ old('tin', $shop->tin) }}"></div>
        <div class="d-flex justify-content-between">
            <a href="{{ route('vendor.onboarding.step1') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Sebelumnya</a>
            <button type="submit" class="btn btn-primary">Lanjut <i class="fas fa-arrow-right ms-1"></i></button>
        </div>
    </form>
</div>
@endsection
