@extends('layouts.admin')
@section('title', 'SMS Gateway')
@section('content')
<h4 class="fw-bold mb-3"><i class="fas fa-sms me-2 text-info"></i> SMS Gateway</h4>
<div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
<form action="{{ route('admin.sms-gateway.update') }}" method="POST">@csrf @method('PUT')
<div class="row g-3">
    <div class="col-md-4"><label class="fw-medium">Provider</label><select name="provider" class="form-select"><option value="twilio" {{ \App\Models\SystemSetting::get('sms_provider')==='twilio'?'selected'=>'' }}>Twilio</option><option value="nexmo" {{ \App\Models\SystemSetting::get('sms_provider')==='nexmo'?'selected'=>'' }}>Vonage/Nexmo</option><option value="zenziva" {{ \App\Models\SystemSetting::get('sms_provider')==='zenziva'?'selected'=>'' }}>Zenziva</option><option value="none" {{ \App\Models\SystemSetting::get('sms_provider','none')==='none'?'selected'=>'' }}>Off</option></select></div>
    <div class="col-md-4"><label class="fw-medium">API Key / SID</label><input type="text" name="api_key" class="form-control" value="{{ \App\Models\SystemSetting::get('sms_api_key') }}"></div>
    <div class="col-md-4"><label class="fw-medium">API Secret / Token</label><input type="text" name="api_secret" class="form-control" value="{{ \App\Models\SystemSetting::get('sms_api_secret') }}"></div>
    <div class="col-md-4"><label class="fw-medium">Sender ID</label><input type="text" name="sender_id" class="form-control" value="{{ \App\Models\SystemSetting::get('sms_sender_id') }}"></div>
    <div class="col-12"><button class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan</button></div>
</div></form></div></div>
@endsection
