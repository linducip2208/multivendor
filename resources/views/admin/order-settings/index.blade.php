@extends('layouts.admin')
@section('title', 'Order & Invoice Settings')
@section('content')
<div class="mb-4"><h4 class="fw-bold"><i class="fas fa-receipt me-2"></i>Pengaturan Order & Invoice</h4></div>
<div class="card border-0 rounded-4 shadow-sm" style="max-width:700px">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.order-settings.update') }}">
            @csrf @method('PUT')
            <h6 class="fw-bold border-bottom pb-2 mb-3">Pengaturan Order</h6>
            <div class="mb-3"><label class="form-label fw-medium">Prefix Order</label><input type="text" name="order_prefix" class="form-control" value="{{ \App\Models\SystemSetting::get('order_prefix','ORD') }}"></div>
            <div class="row mb-3"><div class="col"><label class="form-label fw-medium">Minimal Order (Rp)</label><input type="number" name="order_min_amount" class="form-control" value="{{ \App\Models\SystemSetting::get('order_min_amount','0') }}"></div><div class="col"><label class="form-label fw-medium">Maksimal Order (Rp)</label><input type="number" name="order_max_amount" class="form-control" value="{{ \App\Models\SystemSetting::get('order_max_amount','') }}"></div></div>
            <div class="mb-3"><label class="form-label fw-medium">Waktu Batal Otomatis (jam)</label><input type="number" name="order_cancel_time" class="form-control" value="{{ \App\Models\SystemSetting::get('order_cancel_time','24') }}"></div>
            <div class="mb-3"><div class="form-check"><input type="checkbox" name="order_auto_confirm" class="form-check-input" value="1" {{ \App\Models\SystemSetting::get('order_auto_confirm') ? 'checked' : '' }}><label class="form-check-label">Auto-konfirmasi order setelah pembayaran</label></div></div>
            <div class="mb-3"><div class="form-check"><input type="checkbox" name="order_guest_checkout" class="form-check-input" value="1" {{ \App\Models\SystemSetting::get('order_guest_checkout') ? 'checked' : '' }}><label class="form-check-label">Guest checkout (tanpa login)</label></div></div>

            <h6 class="fw-bold border-bottom pb-2 mb-3 mt-4">Pengaturan Invoice</h6>
            <div class="mb-3"><label class="form-label fw-medium">Prefix Invoice</label><input type="text" name="invoice_prefix" class="form-control" value="{{ \App\Models\SystemSetting::get('invoice_prefix','INV') }}"></div>
            <div class="mb-3"><label class="form-label fw-medium">Syarat & Ketentuan Invoice</label><textarea name="invoice_terms" class="form-control" rows="3">{{ \App\Models\SystemSetting::get('invoice_terms') }}</textarea></div>
            <div class="mb-3"><label class="form-label fw-medium">Footer Invoice</label><input type="text" name="invoice_footer" class="form-control" value="{{ \App\Models\SystemSetting::get('invoice_footer') }}"></div>

            <h6 class="fw-bold border-bottom pb-2 mb-3 mt-4">Verifikasi Pengiriman</h6>
            <div class="mb-3"><div class="form-check"><input type="checkbox" name="delivery_verification" class="form-check-input" value="1" {{ \App\Models\SystemSetting::get('delivery_verification') ? 'checked' : '' }}><label class="form-check-label">Aktifkan verifikasi kode pengiriman</label></div></div>
            <div class="mb-3"><label class="form-label fw-medium">Panjang Kode OTP</label><input type="number" name="delivery_otp_length" class="form-control" value="{{ \App\Models\SystemSetting::get('delivery_otp_length','6') }}"></div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan Pengaturan</button>
        </form>
    </div>
</div>
@endsection
