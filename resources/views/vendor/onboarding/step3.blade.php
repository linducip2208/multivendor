@extends('layouts.vendor')
@section('title', 'Onboarding - Step 3')
@section('content')
<div style="max-width:500px;margin:0 auto">
    <div class="text-center mb-4"><div class="mb-2"><span class="badge bg-primary">Step 3/4</span></div><h4 class="fw-bold">Pengaturan Pengiriman</h4><p class="text-muted small">Pilih metode pengiriman yang tersedia</p></div>
    <form method="POST" action="{{ route('vendor.onboarding.step3.store') }}">
        @csrf
        @foreach($shippingMethods as $method)
        <div class="card border rounded-3 p-3 mb-2"><div class="form-check"><input type="checkbox" name="shipping_methods[{{ $method->id }}]" class="form-check-input" value="0" id="sm{{ $method->id }}"><label class="form-check-label fw-medium" for="sm{{ $method->id }}">{{ $method->name }}</label><div class="text-muted small mt-1">Estimasi: {{ $method->duration ?? '-' }} | Biaya: Rp {{ number_format($method->cost ?? 0,0,',','.') }}</div></div></div>
        @endforeach
        @if($shippingMethods->isEmpty())
        <div class="text-muted text-center py-4">Belum ada metode pengiriman. Bisa diatur nanti.</div>
        @endif
        <div class="d-flex justify-content-between mt-3">
            <a href="{{ route('vendor.onboarding.step2') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Sebelumnya</a>
            <button type="submit" class="btn btn-primary">Lanjut <i class="fas fa-arrow-right ms-1"></i></button>
        </div>
    </form>
</div>
@endsection
