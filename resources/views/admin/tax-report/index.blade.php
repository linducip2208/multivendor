@extends('layouts.admin')
@section('title', 'Tax Report')
@section('content')
<div class="mb-4"><h4 class="fw-bold"><i class="fas fa-file-invoice-dollar me-2"></i>Laporan Pajak</h4></div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card card-stat"><div class="stat-label">Total Pajak (Bulan Ini)</div><div class="stat-value text-danger">Rp {{ number_format($taxCollected,0,',','.') }}</div></div>
    </div>
    <div class="col-md-3">
        <div class="card card-stat"><div class="stat-label">Penjualan Kena Pajak</div><div class="stat-value">Rp {{ number_format($taxableSales,0,',','.') }}</div></div>
    </div>
    <div class="col-md-3">
        <div class="card card-stat"><div class="stat-label">Tarif Pajak Aktif</div><div class="stat-value">{{ $vatTaxes->count() }} Pajak</div></div>
    </div>
    <div class="col-md-3">
        <div class="card card-stat"><div class="stat-label">Total Pajak (Tahun)</div><div class="stat-value text-success">Rp {{ number_format($monthlyTaxes->sum('total_tax'),0,',','.') }}</div></div>
    </div>
</div>

<div class="card border-0 rounded-4 shadow-sm mb-4">
    <div class="card-body">
        <h5 class="fw-bold">Pajak per Bulan — {{ $year }}</h5>
        <canvas id="taxChart" height="80"></canvas>
    </div>
</div>

<div class="card border-0 rounded-4 shadow-sm">
    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0">Daftar Pajak (PPN)</h5>
        <a href="{{ route('admin.tax-report.settings') }}" class="btn btn-outline-primary btn-sm">Pengaturan Pajak</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th class="text-uppercase small">NAMA</th><th class="text-uppercase small">TARIF</th><th class="text-uppercase small">STATUS</th></tr></thead>
            <tbody>
                @foreach($vatTaxes as $tax)
                <tr><td>{{ $tax->name }}</td><td><span class="badge bg-primary-subtle text-primary">{{ $tax->rate }}%</span></td><td><span class="badge bg-success-subtle text-success">Aktif</span></td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',()=>{
    const ctx=document.getElementById('taxChart').getContext('2d');
    new Chart(ctx,{type:'bar',data:{labels:['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'],datasets:[
        {label:'Pajak (Rp)',data:[{{ implode(',', array_map(fn($m) => ($monthlyTaxes[$m] ?? null)?->total_tax ?? 0, range(1,12))) }}],backgroundColor:'rgba(220,38,38,.7)',borderRadius:8},
        {label:'Penjualan (Rp)',data:[{{ implode(',', array_map(fn($m) => ($monthlyTaxes[$m] ?? null)?->total_sales ?? 0, range(1,12))) }}],backgroundColor:'rgba(79,70,229,.5)',borderRadius:8}
    ]},options:{responsive:true,plugins:{legend:{position:'top'}}}});
});
</script>
@endpush
