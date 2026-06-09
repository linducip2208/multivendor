@extends('layouts.admin')

@section('title', 'Edit Vendor')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.vendors.index') }}" class="text-decoration-none small">
        <i class="fas fa-arrow-left me-1"></i> Kembali ke daftar vendor
    </a>
    <h4 class="fw-bold mt-2 mb-1">Edit Vendor: {{ $shop->name }}</h4>
    <p class="text-muted small mb-0">Perbarui data vendor dan toko</p>
</div>

<div class="card border-0 rounded-4 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('admin.vendors.update', $shop) }}">
            @csrf @method('PUT')
            <div class="row g-3">
                <div class="col-12"><h6 class="fw-bold mb-3"><i class="fas fa-user me-2 text-primary"></i> Data Vendor</h6></div>
                <div class="col-md-6">
                    <label class="form-label fw-medium">Nama Vendor <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $shop->vendor->name) }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $shop->vendor->email) }}" required>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Password <small class="text-muted">(kosongkan jika tidak diubah)</small></label>
                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror">
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">No. HP</label>
                    <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $shop->vendor->phone) }}">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                        <option value="active" {{ old('status', $shop->status) === 'active' ? 'selected' : '' }}>Aktif</option>
                        <option value="pending" {{ old('status', $shop->status) === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="suspended" {{ old('status', $shop->status) === 'suspended' ? 'selected' : '' }}>Suspended</option>
                    </select>
                </div>

                <div class="col-12 mt-4"><h6 class="fw-bold mb-3"><i class="fas fa-store me-2 text-success"></i> Data Toko</h6></div>
                <div class="col-md-6">
                    <label class="form-label fw-medium">Nama Toko <span class="text-danger">*</span></label>
                    <input type="text" name="shop_name" class="form-control @error('shop_name') is-invalid @enderror" value="{{ old('shop_name', $shop->name) }}" required>
                    @error('shop_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium">Alamat Toko</label>
                    <input type="text" name="shop_address" class="form-control @error('shop_address') is-invalid @enderror" value="{{ old('shop_address', $shop->address) }}">
                    @error('shop_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label fw-medium">Deskripsi Toko</label>
                    <textarea name="shop_description" class="form-control" rows="3">{{ old('shop_description', $shop->description) }}</textarea>
                </div>

                <div class="col-12 mt-4"><h6 class="fw-bold mb-3"><i class="fas fa-percent me-2 text-warning"></i> Komisi</h6></div>
                <div class="col-md-3">
                    <label class="form-label fw-medium">Tipe Komisi <span class="text-danger">*</span></label>
                    <select name="commission_type" class="form-select @error('commission_type') is-invalid @enderror" required>
                        <option value="percentage" {{ old('commission_type', $shop->commission_type) === 'percentage' ? 'selected' : '' }}>Persentase (%)</option>
                        <option value="fixed" {{ old('commission_type', $shop->commission_type) === 'fixed' ? 'selected' : '' }}>Nominal Tetap (Rp)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium">Nilai Komisi <span class="text-danger">*</span></label>
                    <input type="number" name="commission_value" class="form-control @error('commission_value') is-invalid @enderror" value="{{ old('commission_value', $shop->commission_value) }}" step="0.01" min="0" required>
                    @error('commission_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 mt-4">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-save me-2"></i> Perbarui Vendor
                    </button>
                    <a href="{{ route('admin.vendors.index') }}" class="btn btn-outline-secondary ms-2">Batal</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
