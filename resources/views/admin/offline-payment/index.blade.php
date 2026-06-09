@extends('layouts.admin')
@section('title', 'Metode Pembayaran Offline')
@section('content')
<h4 class="fw-bold mb-3"><i class="fas fa-money-check me-2 text-info"></i> Metode Pembayaran Offline</h4>
<div class="row g-3">
<form action="{{ route('admin.offline-payment.update') }}" method="POST">@csrf @method('PUT')
@php $methods = ['bank_transfer'=>'Transfer Bank','cod'=>'Cash on Delivery (COD)','manual'=>'Pembayaran Manual']; @endphp
@foreach($methods as $key=>$label)
<div class="col-md-6 mb-3"><div class="card border-0 rounded-4 shadow-sm p-4">
    <div class="d-flex justify-content-between mb-2"><h6 class="fw-bold mb-0">{{ $label }}</h6><div class="form-check form-switch"><input type="checkbox" name="methods[{{ $key }}][active]" class="form-check-input" value="1" {{ \App\Models\SystemSetting::get('payment_'.$key.'_active') ? 'checked' : '' }}></div></div>
    <textarea name="methods[{{ $key }}][details]" class="form-control form-control-sm" rows="2" placeholder="Info rekening/catatan pembayaran...">{{ \App\Models\SystemSetting::get('payment_'.$key.'_details') }}</textarea>
</div></div>
@endforeach
<div class="col-12"><button class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan</button></div>
</form>
</div>
@endsection
