@extends('layouts.admin')
@section('title', 'Theme Settings')
@section('content')
<div class="mb-4"><h4 class="fw-bold"><i class="fas fa-palette me-2"></i>Theme Settings</h4></div>
<div class="card border-0 rounded-4 shadow-sm" style="max-width:600px">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.theme.update') }}">
            @csrf @method('PUT')
            <div class="mb-3"><label class="form-label fw-medium">Warna Primary</label>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    @foreach($colors as $c)<label style="width:36px;height:36px;border-radius:50%;background:{{ $c }};cursor:pointer;border:2px solid {{ \App\Models\SystemSetting::get('theme_primary_color','#4F46E5')==$c ? '#000' : 'transparent' }}"><input type="radio" name="theme_primary_color" value="{{ $c }}" {{ \App\Models\SystemSetting::get('theme_primary_color','#4F46E5')==$c ? 'checked' : '' }} style="display:none"></label>@endforeach
                </div>
                <input type="color" name="theme_primary_color" class="form-control form-control-color" value="{{ \App\Models\SystemSetting::get('theme_primary_color','#4F46E5') }}">
            </div>
            <div class="mb-3"><label class="form-label fw-medium">Warna Primary Dark</label><input type="color" name="theme_primary_dark" class="form-control form-control-color" value="{{ \App\Models\SystemSetting::get('theme_primary_dark','#3730A3') }}"></div>
            <div class="mb-3"><label class="form-label fw-medium">Border Radius (px)</label><input type="number" name="theme_border_radius" class="form-control" value="{{ \App\Models\SystemSetting::get('theme_border_radius','14') }}"></div>
            <div class="mb-3"><label class="form-label fw-medium">Font</label><select name="theme_font_family" class="form-select"><option value="Inter" {{ \App\Models\SystemSetting::get('theme_font_family','Inter')=='Inter'?'selected':'' }}>Inter</option><option value="Poppins">Poppins</option><option value="Plus Jakarta Sans">Plus Jakarta Sans</option></select></div>
            <div class="mb-3"><div class="form-check"><input type="checkbox" name="theme_dark_mode_default" class="form-check-input" value="1" {{ \App\Models\SystemSetting::get('theme_dark_mode_default') ? 'checked' : '' }}><label class="form-check-label">Dark mode default</label></div></div>
            <div class="mb-3"><div class="form-check"><input type="checkbox" name="theme_show_language_switcher" class="form-check-input" value="1" {{ \App\Models\SystemSetting::get('theme_show_language_switcher','1') ? 'checked' : '' }}><label class="form-check-label">Tampilkan language switcher</label></div></div>
            <div class="mb-3"><label class="form-label fw-medium">Logo Text</label><input type="text" name="theme_logo_text" class="form-control" value="{{ \App\Models\SystemSetting::get('theme_logo_text',config('app.name')) }}"></div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan</button>
        </form>
    </div>
</div>
@endsection
