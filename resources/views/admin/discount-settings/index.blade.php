@extends('layouts.admin')
@section('title', 'Discount Settings')
@section('content')
<div class="mb-4"><h4 class="fw-bold"><i class="fas fa-tags me-2"></i>Pengaturan Diskon</h4></div>
<div class="card border-0 rounded-4 shadow-sm" style="max-width:600px">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.discount-settings.update') }}">
            @csrf @method('PUT')
            <h6 class="fw-bold border-bottom pb-2 mb-3">Bearer Diskon</h6>
            <div class="mb-3"><label class="form-label fw-medium">Yang Menanggung Diskon</label>
                <select name="discount_bearer" class="form-select">
                    <option value="admin" {{ \App\Models\SystemSetting::get('discount_bearer')=='admin'?'selected':'' }}>Admin (Platform)</option>
                    <option value="vendor" {{ \App\Models\SystemSetting::get('discount_bearer')=='vendor'?'selected':'' }}>Vendor (Toko)</option>
                    <option value="split" {{ \App\Models\SystemSetting::get('discount_bearer')=='split'?'selected':'' }}>Split (Admin & Vendor)</option>
                </select>
            </div>
            <div class="row mb-3"><div class="col"><label class="form-label fw-medium">Share Admin (%)</label><input type="number" name="discount_admin_share" class="form-control" value="{{ \App\Models\SystemSetting::get('discount_admin_share','50') }}" min="0" max="100"></div><div class="col"><label class="form-label fw-medium">Share Vendor (%)</label><input type="number" name="discount_vendor_share" class="form-control" value="{{ \App\Models\SystemSetting::get('discount_vendor_share','50') }}" min="0" max="100"></div></div>

            <h6 class="fw-bold border-bottom pb-2 mb-3 mt-4">Bearer Kupon</h6>
            <div class="mb-3"><label class="form-label fw-medium">Yang Menanggung Kupon</label>
                <select name="coupon_bearer" class="form-select">
                    <option value="admin" {{ \App\Models\SystemSetting::get('coupon_bearer')=='admin'?'selected':'' }}>Admin (Platform)</option>
                    <option value="vendor" {{ \App\Models\SystemSetting::get('coupon_bearer')=='vendor'?'selected':'' }}>Vendor (Toko)</option>
                    <option value="split" {{ \App\Models\SystemSetting::get('coupon_bearer')=='split'?'selected':'' }}>Split</option>
                </select>
            </div>
            <div class="row mb-3"><div class="col"><label class="form-label fw-medium">Share Admin (%)</label><input type="number" name="coupon_admin_share" class="form-control" value="{{ \App\Models\SystemSetting::get('coupon_admin_share','50') }}" min="0" max="100"></div><div class="col"><label class="form-label fw-medium">Share Vendor (%)</label><input type="number" name="coupon_vendor_share" class="form-control" value="{{ \App\Models\SystemSetting::get('coupon_vendor_share','50') }}" min="0" max="100"></div></div>

            <h6 class="fw-bold border-bottom pb-2 mb-3 mt-4">Batasan</h6>
            <div class="mb-3"><label class="form-label fw-medium">Diskon Maksimal (%)</label><input type="number" name="discount_max_percentage" class="form-control" value="{{ \App\Models\SystemSetting::get('discount_max_percentage','70') }}" max="100"></div>
            <div class="mb-3"><div class="form-check"><input type="checkbox" name="discount_require_approval" class="form-check-input" value="1" {{ \App\Models\SystemSetting::get('discount_require_approval') ? 'checked' : '' }}><label class="form-check-label">Diskon besar perlu approval admin</label></div></div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan</button>
        </form>
    </div>
</div>
@endsection
