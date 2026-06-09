@extends('layouts.admin')
@section('title', 'Email Templates')
@section('content')
<h4 class="fw-bold mb-3"><i class="fas fa-envelope me-2 text-info"></i> Email Templates</h4>
<div class="row g-3">
@php $templates=['order_confirmation'=>'Konfirmasi Pesanan','order_shipped'=>'Pesanan Dikirim','order_delivered'=>'Pesanan Sampai','order_canceled'=>'Pesanan Dibatalkan','welcome'=>'Welcome Email','password_reset'=>'Reset Password','invoice'=>'Invoice','withdraw_approved'=>'Withdraw Approved','vendor_registration'=>'Vendor Registration']; @endphp
@foreach($templates as $key=>$label)
<div class="col-md-6"><div class="card border-0 rounded-4 shadow-sm p-4"><h6 class="fw-bold"><i class="fas fa-file-alt me-2 text-muted"></i>{{ $label }}</h6><div class="mt-2">
<form method="POST" action="{{ route('admin.email-templates.update') }}">@csrf @method('PUT')<input type="hidden" name="key" value="{{ $key }}"><div class="mb-2"><label class="small">Subject</label><input type="text" name="subject" class="form-control form-control-sm" value="{{ \App\Models\SystemSetting::get('email_'.$key.'_subject','') }}"></div><div class="mb-2"><label class="small">Body (HTML)</label><textarea name="body" class="form-control form-control-sm" rows="3">{{ \App\Models\SystemSetting::get('email_'.$key.'_body','') }}</textarea></div><button class="btn btn-sm btn-primary">Simpan</button></form>
</div></div></div>
@endforeach
</div>
<p class="text-muted small mt-2">Variable: <code>{{name}}</code> <code>{{email}}</code> <code>{{order_number}}</code> <code>{{total}}</code> <code>{{shop_name}}</code></p>
@endsection
