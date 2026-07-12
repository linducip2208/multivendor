@extends('layouts.admin')
@section('title', 'Edit Flash Deal')
@section('content')
<div class="mb-4"><a href="{{ route('admin.flashdeals.index') }}" class="small"><i class="fas fa-arrow-left me-1"></i>Kembali</a><h4 class="fw-bold mt-2">Edit Flash Deal: {{ $flashdeal->title }}</h4></div>
<div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
<form method="POST" action="{{ route('admin.flashdeals.update', $flashdeal) }}">@csrf @method('PUT')
<div class="row g-3">
    <div class="col-md-6"><label class="fw-medium">Judul</label><input type="text" name="title" class="form-control" value="{{ old('title', $flashdeal->title) }}" required></div>
    <div class="col-md-3"><label class="fw-medium">Mulai</label><input type="datetime-local" name="start_date" class="form-control" value="{{ old('start_date', $flashdeal->start_date->format('Y-m-d\TH:i')) }}" required></div>
    <div class="col-md-3"><label class="fw-medium">Berakhir</label><input type="datetime-local" name="end_date" class="form-control" value="{{ old('end_date', $flashdeal->end_date->format('Y-m-d\TH:i')) }}" required></div>
    <div class="col-md-6"><input type="hidden" name="status" value="0"><input type="hidden" name="featured" value="0"><div class="form-check mt-2"><input type="checkbox" name="status" class="form-check-input" id="st" value="1" {{ $flashdeal->status?'checked' : '' }}><label for="st" class="fw-medium">Aktif</label></div><div class="form-check"><input type="checkbox" name="featured" class="form-check-input" id="feat" value="1" {{ $flashdeal->featured?'checked' : '' }}><label for="feat">Featured</label></div></div>
    <div class="col-12"><h6 class="fw-bold mt-3">Produk ({{ $flashdeal->dealProducts->count() }})</h6>
        <div class="row g-2">
            @foreach($flashdeal->dealProducts as $dp)
            <div class="col-md-3"><div class="border rounded-3 p-2"><div class="fw-medium small">{{ Str::limit($dp->product->name ?? 'Produk', 30) }}</div><div class="d-flex gap-2 mt-1">
                <select name="products[{{ $dp->id }}][discount_type]" class="form-select form-select-sm"><option value="percentage" {{ $dp->discount_type==='percentage'?'selected' : '' }}>%</option><option value="flat" {{ $dp->discount_type==='flat'?'selected' : '' }}>Rp</option></select>
                <input type="hidden" name="products[{{ $dp->id }}][id]" value="{{ $dp->product_id }}">
                <input type="number" name="products[{{ $dp->id }}][discount_value]" class="form-control form-control-sm" value="{{ $dp->discount_value }}" min="0">
            </div></div></div>
            @endforeach
        </div>
    </div>
    <div class="col-12"><button class="btn btn-primary"><i class="fas fa-save me-2"></i>Perbarui</button></div>
</div>
</form></div></div>
@endsection
