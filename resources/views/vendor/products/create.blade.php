@extends('layouts.vendor')
@section('title', 'Tambah Produk — Multi-Step')
@push('head')
<style>.step-wizard{display:flex;gap:0;margin-bottom:24px}.step-wizard .step{flex:1;text-align:center;padding:12px 8px;background:#f1f5f9;border-right:2px solid #fff;font-size:.8rem;font-weight:600;color:#94a3b8;cursor:pointer;transition:all .2s}.step-wizard .step.active{background:#4F46E5;color:#fff}.step-wizard .step.done{background:#dbeafe;color:#1e40af}.tab-pane{display:none}.tab-pane.active{display:block}.variant-row{border:1px solid #e2e8f0;border-radius:10px;padding:12px;margin-bottom:8px}</style>
@endpush
@section('content')
<div class="mb-4"><a href="{{ route('vendor.products.index') }}" class="small"><i class="fas fa-arrow-left me-1"></i>Kembali</a><h4 class="fw-bold mt-2">Tambah Produk Baru</h4></div>

<form method="POST" action="{{ route('vendor.products.store') }}" enctype="multipart/form-data" id="productForm">@csrf

<div class="step-wizard">
    <div class="step active" onclick="showTab(0)">1. Basic Info</div>
    <div class="step" onclick="showTab(1)">2. Harga & Stok</div>
    <div class="step" onclick="showTab(2)">3. Gambar & Video</div>
    <div class="step" onclick="showTab(3)">4. Varian & SKU</div>
    <div class="step" onclick="showTab(4)">5. SEO & Tag</div>
</div>

<div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">

{{-- Tab 1: Basic Info --}}
<div class="tab-pane active" id="tab0">
    <div class="row g-3">
        <div class="col-md-8"><label class="fw-medium">Nama Produk <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" value="{{ old('name') }}" required></div>
        <div class="col-md-4"><label class="fw-medium">SKU</label><input type="text" name="sku" class="form-control" value="{{ old('sku') }}"></div>
        <div class="col-md-4"><label class="fw-medium">Kategori <span class="text-danger">*</span></label><select name="category_id" class="form-select" required>@foreach(\App\Models\Category::where('status',true)->get() as $c)<option value="{{ $c->id }}">{{ $c->parent ? $c->parent->name.' > ' : '' }}{{ $c->name }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="fw-medium">Brand</label><select name="brand_id" class="form-select">@foreach(\App\Models\Brand::where('status',true)->get() as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="fw-medium">Tipe <span class="text-danger">*</span></label><select name="product_type" class="form-select" id="productType" required><option value="physical">Fisik</option><option value="digital">Digital</option></select></div>
        <div class="col-md-2"><label class="fw-medium">Satuan</label><input type="text" name="unit" class="form-control" value="{{ old('unit','pcs') }}"></div>
        <div class="col-md-2"><label class="fw-medium">Min Qty</label><input type="number" name="min_qty" class="form-control" value="1" min="1"></div>
        <div class="col-md-2"><label class="fw-medium">Max Qty</label><input type="number" name="max_qty" class="form-control" value="10" min="1"></div>
    </div>
</div>

{{-- Tab 2: Harga & Stok --}}
<div class="tab-pane" id="tab1">
    <div class="row g-3">
        <div class="col-md-4"><label class="fw-medium">Harga (Rp) <span class="text-danger">*</span></label><input type="number" name="price" class="form-control" value="{{ old('price') }}" required></div>
        <div class="col-md-4"><label class="fw-medium">Harga Diskon (Rp)</label><input type="number" name="special_price" class="form-control" value="{{ old('special_price') }}"></div>
        <div class="col-md-4"><label class="fw-medium">Stok <span class="text-danger">*</span></label><input type="number" name="current_stock" class="form-control" value="{{ old('current_stock',0) }}" required></div>
        <div class="col-md-3"><label class="fw-medium">Pajak (%)</label><input type="number" name="tax" class="form-control" value="0" step="0.01"></div>
        <div class="col-md-3"><label class="fw-medium">Ongkir (Rp)</label><input type="number" name="shipping_cost" class="form-control" value="0"></div>
        <div class="col-md-3"><label class="fw-medium">Tipe Diskon</label><select name="discount_type" class="form-select"><option value="">None</option><option value="flat">Nominal (Rp)</option><option value="percentage">Persentase (%)</option></select></div>
        <div class="col-md-3"><label class="fw-medium">Berlaku s/d</label><input type="datetime-local" name="discount_end" class="form-control"></div>
    </div>
</div>

{{-- Tab 3: Gambar & Video --}}
<div class="tab-pane" id="tab2">
    <div class="row g-3">
        <div class="col-12"><label class="fw-medium">Foto Utama</label><input type="file" name="thumbnail" class="form-control" accept="image/*"><small class="text-muted">Max 2MB, jpg/png/webp</small></div>
        <div class="col-12"><label class="fw-medium">Foto Tambahan (max 5)</label><input type="file" name="images[]" class="form-control" accept="image/*" multiple><small class="text-muted">Bisa pilih beberapa file sekaligus</small></div>
        <div class="col-12"><label class="fw-medium">Video URL</label><input type="url" name="video_url" class="form-control" placeholder="YouTube / Vimeo / MP4 URL"><small class="text-muted">Atau upload langsung:</small><input type="file" name="video_file" class="form-control mt-1" accept="video/*"><small class="text-muted">Max 50MB, mp4/webm. Upload ke server.</small></div>
    </div>
</div>

{{-- Tab 4: Varian --}}
<div class="tab-pane" id="tab3">
    <p class="text-muted small mb-3">Tambah varian seperti warna, ukuran, dll. Setiap varian bisa punya harga & stok sendiri.</p>
    <div id="variantContainer">
        <div class="variant-row row g-2">
            <div class="col-md-3"><label class="small">Nama Varian</label><input type="text" name="variants[0][name]" class="form-control form-control-sm" placeholder="Warna: Merah"></div>
            <div class="col-md-2"><label class="small">SKU</label><input type="text" name="variants[0][sku]" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="small">Harga</label><input type="number" name="variants[0][price]" class="form-control form-control-sm" step="1"></div>
            <div class="col-md-2"><label class="small">Stok</label><input type="number" name="variants[0][stock]" class="form-control form-control-sm" value="0"></div>
            <div class="col-md-1 d-flex align-items-end"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.variant-row').remove()"><i class="fas fa-times"></i></button></div>
        </div>
    </div>
    <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addVariant()"><i class="fas fa-plus me-1"></i>Tambah Varian</button>
</div>

{{-- Tab 5: SEO & Tags --}}
<div class="tab-pane" id="tab4">
    <div class="row g-3">
        <div class="col-12"><label class="fw-medium">Deskripsi Singkat</label><div id="quillShort" style="height:120px;"></div><input type="hidden" name="short_description" id="shortInput"></div>
        <div class="col-12"><label class="fw-medium">Deskripsi Lengkap</label><div id="quillEditor" style="height:200px;"></div><input type="hidden" name="description" id="descInput"></div>
        <div class="col-md-6"><label class="fw-medium">Meta Title</label><input type="text" name="meta_title" class="form-control" maxlength="255"></div>
        <div class="col-md-6"><label class="fw-medium">Meta Description</label><input type="text" name="meta_description" class="form-control" maxlength="500"></div>
        <div class="col-12"><label class="fw-medium">Tags (pisahkan dengan koma)</label><input type="text" name="tags" class="form-control" placeholder="contoh: sepatu, sneakers, casual, pria"></div>
    </div>
</div>

<div class="d-flex justify-content-between mt-4">
    <button type="button" class="btn btn-outline-secondary" id="prevBtn" onclick="prevTab()" style="display:none"><i class="fas fa-arrow-left me-1"></i>Sebelumnya</button>
    <button type="button" class="btn btn-primary" id="nextBtn" onclick="nextTab()">Selanjutnya <i class="fas fa-arrow-right ms-1"></i></button>
    <button type="submit" class="btn btn-success" id="submitBtn" style="display:none"><i class="fas fa-save me-2"></i>Simpan Produk</button>
</div>

</div></div></form>
@endsection

@push('scripts')
<script>
let currentTab=0;const totalTabs=5;
function showTab(n){
    document.querySelectorAll('.tab-pane').forEach((t,i)=>t.classList.toggle('active',i===n));
    document.querySelectorAll('.step').forEach((s,i)=>{s.classList.toggle('active',i===n);if(i<n)s.classList.add('done');else s.classList.remove('done')});
    document.getElementById('prevBtn').style.display=n===0?'none' : 'inline-block';
    document.getElementById('nextBtn').style.display=n===totalTabs-1?'none' : 'inline-block';
    document.getElementById('submitBtn').style.display=n===totalTabs-1?'inline-block' : 'none';
    currentTab=n;
}
function nextTab(){if(currentTab<totalTabs-1)showTab(currentTab+1)}
function prevTab(){if(currentTab>0)showTab(currentTab-1)}
let vCount=1;
function addVariant(){
    const html=`<div class="variant-row row g-2"><div class="col-md-3"><input type="text" name="variants[${vCount}][name]" class="form-control form-control-sm" placeholder="Warna: Biru"></div><div class="col-md-2"><input type="text" name="variants[${vCount}][sku]" class="form-control form-control-sm"></div><div class="col-md-2"><input type="number" name="variants[${vCount}][price]" class="form-control form-control-sm"></div><div class="col-md-2"><input type="number" name="variants[${vCount}][stock]" class="form-control form-control-sm" value="0"></div><div class="col-md-1 d-flex align-items-end"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.variant-row').remove()"><i class="fas fa-times"></i></button></div></div>`;
    document.getElementById('variantContainer').insertAdjacentHTML('beforeend',html);vCount++;
}
var quillShort=new Quill('#quillShort',{theme:'snow',modules:{toolbar:[['bold','italic','underline'],['link'],['clean']]}});
var quill=new Quill('#quillEditor',{theme:'snow',modules:{toolbar:[['bold','italic','underline','strike'],[{list:'ordered'},{list:'bullet'}],['link','image'],['clean']]}});
document.getElementById('productForm').addEventListener('submit',function(){document.getElementById('shortInput').value=quillShort.root.innerHTML;document.getElementById('descInput').value=quill.root.innerHTML});
</script>
@endpush
