@extends('layouts.vendor')
@section('title', 'Bulk Import')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-upload me-2 text-info"></i> Bulk Import Produk</h4>
</div>
<div class="row g-4">
    <div class="col-lg-5"><div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
        <form action="{{ route('vendor.bulk-import.store') }}" method="POST" enctype="multipart/form-data">@csrf
            <div class="mb-3"><label class="fw-medium">Upload File CSV / Excel</label><input type="file" name="file" class="form-control" accept=".csv,.xlsx,.xls" required><small class="text-muted">Max 10MB</small></div>
            <button class="btn btn-primary w-100"><i class="fas fa-upload me-2"></i>Import Produk</button>
        </form>
    </div></div></div>
    <div class="col-lg-7"><div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
        <h6 class="fw-bold mb-3"><i class="fas fa-info-circle me-2"></i>Format CSV</h6>
        <p class="text-muted small">Kolom yang wajib: <code>name</code>, <code>price</code>, <code>stock</code></p>
        <p class="text-muted small">Kolom opsional: <code>sku</code>, <code>category_id</code>, <code>description</code></p>
        <pre class="bg-light rounded-3 p-3 small">name,price,stock,sku,category_id,description
"iPhone 15",15000000,10,IP15-001,1,"Smartphone Apple"
"Kemeja Pria",150000,50,KMJ-001,4,"Bahan katun premium"</pre>
    </div></div></div>
</div>
@endsection
