@extends('layouts.admin')
@section('title', 'Tambah Flash Deal')
@section('content')
<div class="mb-4"><a href="{{ route('admin.flashdeals.index') }}" class="small"><i class="fas fa-arrow-left me-1"></i>Kembali</a><h4 class="fw-bold mt-2">Tambah Flash Deal</h4></div>
<div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
<form method="POST" action="{{ route('admin.flashdeals.store') }}">@csrf
<div class="row g-3">
    <div class="col-md-6"><label class="fw-medium">Judul <span class="text-danger">*</span></label><input type="text" name="title" class="form-control" value="{{ old('title') }}" required></div>
    <div class="col-md-3"><label class="fw-medium">Mulai <span class="text-danger">*</span></label><input type="datetime-local" name="start_date" class="form-control" value="{{ old('start_date') }}" required></div>
    <div class="col-md-3"><label class="fw-medium">Berakhir <span class="text-danger">*</span></label><input type="datetime-local" name="end_date" class="form-control" value="{{ old('end_date') }}" required></div>
    <div class="col-md-6"><div class="form-check mt-2"><input type="checkbox" name="status" class="form-check-input" id="st" value="1" checked><label for="st" class="fw-medium">Aktif</label></div><div class="form-check"><input type="checkbox" name="featured" class="form-check-input" id="feat" value="1"><label for="feat">Featured</label></div></div>
    <div class="col-12"><h6 class="fw-bold mt-3">Pilih Produk</h6>
        <div class="row g-2" id="productList">
            @foreach(\App\Models\Product::where('status','approved')->take(30)->get() as $p)
            <div class="col-md-4">
                <div class="border rounded-3 p-2">
                    <div class="form-check"><input type="checkbox" name="products[{{ $p->id }}][id]" value="{{ $p->id }}" class="form-check-input product-check"><label class="fw-medium small">{{ Str::limit($p->name, 40) }}</label></div>
                    <div class="d-flex gap-2 mt-1"><select name="products[{{ $p->id }}][discount_type]" class="form-select form-select-sm"><option value="percentage">%</option><option value="flat">Rp</option></select><input type="number" name="products[{{ $p->id }}][discount_value]" class="form-control form-control-sm" placeholder="Nilai" min="0"></div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    <div class="col-12"><button class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan</button></div>
</div>
</form></div></div>
@endsection
