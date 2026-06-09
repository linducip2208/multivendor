@extends('layouts.admin')
@section('title', '3rd Party Settings')
@section('content')
<h4 class="fw-bold mb-3"><i class="fas fa-cogs me-2 text-secondary"></i> Pengaturan Pihak Ketiga</h4>
<div class="row g-4">
    {{-- reCAPTCHA --}}
    <div class="col-md-6"><div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4"><h6 class="fw-bold mb-3"><i class="fas fa-shield-alt me-2 text-primary"></i>reCAPTCHA</h6>
    <form action="{{ route('admin.third-party.update') }}" method="POST">@csrf @method('PUT')<input type="hidden" name="section" value="recaptcha">
    <div class="mb-2"><label class="small">Site Key</label><input type="text" name="recaptcha_site_key" class="form-control form-control-sm" value="{{ \App\Models\SystemSetting::get('recaptcha_site_key') }}"></div>
    <div class="mb-2"><label class="small">Secret Key</label><input type="text" name="recaptcha_secret_key" class="form-control form-control-sm" value="{{ \App\Models\SystemSetting::get('recaptcha_secret_key') }}"></div>
    <button class="btn btn-sm btn-primary">Simpan</button></form></div></div></div>
    {{-- Google Maps --}}
    <div class="col-md-6"><div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4"><h6 class="fw-bold mb-3"><i class="fas fa-map-marker-alt me-2 text-danger"></i>Google Maps API</h6>
    <form action="{{ route('admin.third-party.update') }}" method="POST">@csrf @method('PUT')<input type="hidden" name="section" value="map">
    <div class="mb-2"><label class="small">API Key</label><input type="text" name="map_api_key" class="form-control form-control-sm" value="{{ \App\Models\SystemSetting::get('map_api_key') }}"></div>
    <button class="btn btn-sm btn-primary">Simpan</button></form></div></div></div>
    {{-- Social Media Chat --}}
    <div class="col-md-6"><div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4"><h6 class="fw-bold mb-3"><i class="fab fa-whatsapp me-2 text-success"></i>Social Media Chat</h6>
    <form action="{{ route('admin.third-party.update') }}" method="POST">@csrf @method('PUT')<input type="hidden" name="section" value="social">
    <div class="mb-2"><label class="small">WhatsApp Number (62xxx)</label><input type="text" name="whatsapp_number" class="form-control form-control-sm" value="{{ \App\Models\SystemSetting::get('whatsapp_number') }}"></div>
    <div class="mb-2"><label class="small">WhatsApp Message</label><input type="text" name="whatsapp_message" class="form-control form-control-sm" value="{{ \App\Models\SystemSetting::get('whatsapp_message','Halo%20saya%20mau%20tanya') }}"></div>
    <div class="mb-2"><label class="small">Facebook Messenger</label><input type="text" name="fb_page_id" class="form-control form-control-sm" value="{{ \App\Models\SystemSetting::get('fb_page_id') }}"></div>
    <button class="btn btn-sm btn-primary">Simpan</button></form></div></div></div>
    {{-- Analytics --}}
    <div class="col-md-6"><div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4"><h6 class="fw-bold mb-3"><i class="fas fa-chart-line me-2 text-warning"></i>Analytics Scripts</h6>
    <form action="{{ route('admin.third-party.update') }}" method="POST">@csrf @method('PUT')<input type="hidden" name="section" value="analytics">
    <div class="mb-2"><label class="small">Google Analytics ID (G-xxx)</label><input type="text" name="ga_id" class="form-control form-control-sm" value="{{ \App\Models\SystemSetting::get('ga_id') }}"></div>
    <div class="mb-2"><label class="small">Facebook Pixel ID</label><input type="text" name="fb_pixel_id" class="form-control form-control-sm" value="{{ \App\Models\SystemSetting::get('fb_pixel_id') }}"></div>
    <button class="btn btn-sm btn-primary">Simpan</button></form></div></div></div>
</div>
@endsection
