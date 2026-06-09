@extends('layouts.vendor')

@section('title', 'Edit Produk')

@section('content')
<div class="mb-4">
    <a href="{{ route('vendor.products.index') }}" class="text-decoration-none small"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
    <h4 class="fw-bold mt-2 mb-1">Edit: {{ $product->name }}</h4>
</div>

<div class="card border-0 rounded-4 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('vendor.products.update', $product) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-medium">Nama <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $product->name) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">SKU</label>
                    <input type="text" name="sku" class="form-control" value="{{ old('sku', $product->sku) }}">
                </div>
                <div class="col-12">
                    <label class="form-label fw-medium">Foto Produk</label>
                    <div class="d-flex gap-2 align-items-center">
                        <input type="file" name="thumbnail" class="form-control" accept="image/*" style="max-width:400px;">
                        @if($product->thumbnail)
                            <img src="{{ asset('storage/'.$product->thumbnail) }}" class="rounded-3" style="width:64px;height:64px;object-fit:cover;">
                            <small class="text-muted">Upload untuk ganti</small>
                        @endif
                    </div>
                    <small class="text-muted">Max 2MB, jpg/png/webp. Kosongkan jika tidak diubah.</small>
                </div>
                <div class="col-12">
                    <label class="form-label fw-medium">Video Produk (URL)</label>
                    <input type="url" name="video_url" class="form-control" value="{{ old('video_url', $product->video_url) }}" placeholder="https://youtube.com/watch?v=...">
                    <small class="text-muted">Link YouTube, Vimeo, atau URL video langsung</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Kategori <span class="text-danger">*</span></label>
                    <select name="category_id" class="form-select" required>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ old('category_id', $product->category_id) == $cat->id ? 'selected' : '' }}>
                                {{ $cat->parent ? $cat->parent->name . ' > ' : '' }}{{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Brand</label>
                    <select name="brand_id" class="form-select">
                        <option value="">Pilih</option>
                        @foreach($brands as $brand)
                            <option value="{{ $brand->id }}" {{ old('brand_id', $product->brand_id) == $brand->id ? 'selected' : '' }}>{{ $brand->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Tipe</label>
                    <select name="product_type" class="form-select">
                        <option value="physical" {{ old('product_type', $product->product_type) == 'physical' ? 'selected' : '' }}>Fisik</option>
                        <option value="digital" {{ old('product_type', $product->product_type) == 'digital' ? 'selected' : '' }}>Digital</option>
                    </select>
                </div>
                <div class="col-md-3"><label class="form-label fw-medium">Harga (Rp) <span class="text-danger">*</span></label><input type="number" name="price" class="form-control" value="{{ old('price', (int)$product->price) }}" required></div>
                <div class="col-md-3"><label class="form-label fw-medium">Diskon (Rp)</label><input type="number" name="special_price" class="form-control" value="{{ old('special_price', (int)$product->special_price) }}"></div>
                <div class="col-md-2"><label class="form-label fw-medium">Stok <span class="text-danger">*</span></label><input type="number" name="current_stock" class="form-control" value="{{ old('current_stock', $product->current_stock) }}" required></div>
                <div class="col-md-2"><label class="form-label fw-medium">Min Qty</label><input type="number" name="min_qty" class="form-control" value="{{ old('min_qty', $product->min_qty) }}"></div>
                <div class="col-md-2"><label class="form-label fw-medium">Max Qty</label><input type="number" name="max_qty" class="form-control" value="{{ old('max_qty', $product->max_qty) }}"></div>
                <div class="col-md-3"><label class="form-label fw-medium">Pajak (%)</label><input type="number" name="tax" class="form-control" value="{{ old('tax', $product->tax) }}" step="0.01"></div>
                <div class="col-md-3"><label class="form-label fw-medium">Ongkir (Rp)</label><input type="number" name="shipping_cost" class="form-control" value="{{ old('shipping_cost', $product->shipping_cost) }}"></div>
                <div class="col-md-2"><label class="form-label fw-medium">Satuan</label><input type="text" name="unit" class="form-control" value="{{ old('unit', $product->unit) }}"></div>
                <div class="col-12"><label class="form-label fw-medium">Deskripsi Singkat</label><div id="quillShort" style="height:120px;"></div><input type="hidden" name="short_description" id="shortInput" value="{{ old('short_description', $product->short_description) }}"></div>
                <div class="col-12"><label class="form-label fw-medium">Deskripsi Lengkap</label><div id="quillEditor" style="height:250px;"></div><input type="hidden" name="description" id="descriptionInput" value="{{ old('description', $product->description) }}"></div>
                <div class="col-12"><button type="submit" class="btn btn-success px-4"><i class="fas fa-save me-2"></i>Perbarui</button></div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
var quillShort = new Quill('#quillShort', { theme: 'snow', modules: { toolbar: [['bold','italic','underline'],['link'],['clean']] }, placeholder: 'Deskripsi singkat...' });
quillShort.root.innerHTML = document.getElementById('shortInput').value || '';

var quill = new Quill('#quillEditor', { theme: 'snow', modules: { toolbar: [['bold','italic','underline','strike'],['blockquote','code-block'],[{list:'ordered'},{list:'bullet'}],['link','image'],['clean']] }, placeholder: 'Deskripsi lengkap produk...' });
quill.root.innerHTML = document.getElementById('descriptionInput').value || '';

document.querySelector('form').addEventListener('submit', function(){
    document.getElementById('shortInput').value = quillShort.root.innerHTML;
    document.getElementById('descriptionInput').value = quill.root.innerHTML;
});
</script>
@endpush
