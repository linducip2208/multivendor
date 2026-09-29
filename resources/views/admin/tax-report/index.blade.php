@extends('layouts.admin')
@section('title', 'Laporan Pajak')
@section('content')
<div class="mb-4"><h4 class="fw-bold"><x-admin.icon name="receipt" :size="16" class="me-2" />Laporan Pajak</h4></div>

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

<x-admin.card :padding="false" class="mb-4">
    <div class="card-body">
        <h5 class="fw-bold">Pajak per Bulan — {{ $year }}</h5>
        <canvas id="taxChart" height="80"></canvas>
    </div>
</x-admin.card>

<x-admin.card :padding="false">
    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0">Daftar Pajak (PPN)</h5>
        <a href="{{ route('admin.tax-report.settings') }}" class="btn btn-outline-primary btn-sm">Pengaturan Pajak</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th class="text-uppercase small">NAMA</th><th class="text-uppercase small">TARIF</th><th class="text-uppercase small">STATUS</th></tr></thead>
            <tbody>
                @foreach($vatTaxes as $tax)
                <tr><td>{{ $tax->name }}</td><td><x-admin.badge color="primary">{{ $tax->rate }}%</x-admin.badge></td><td><x-admin.badge color="success">Aktif</x-admin.badge></td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-admin.card>

@php
    try {
        $efaktur = app(\App\Services\Backoffice\FinanceAdminService::class)->taxInvoices(25, (string) request('efaktur_q', ''));
    } catch (\Throwable $e) {
        $efaktur = [];
    }
@endphp
<x-admin.card :padding="false" class="mt-4" id="efaktur">
    <div class="card-header bg-transparent border-0 d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <h5 class="fw-bold mb-0">e-Faktur Pajak per Order</h5>
        <form method="GET" action="{{ url()->current() }}" class="d-flex gap-2">
            <input type="text" name="efaktur_q" class="form-control form-control-sm" placeholder="Cari INV / seri / order" value="{{ request('efaktur_q') }}">
            <button class="btn btn-outline-secondary btn-sm" type="submit">Cari</button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="efaktur-table">
            <thead class="table-light"><tr><th class="text-uppercase small">INVOICE</th><th class="text-uppercase small">SERI PAJAK</th><th class="text-uppercase small">TOKO</th><th class="text-uppercase small text-end">DPP</th><th class="text-uppercase small text-end">PPN</th><th class="text-uppercase small text-end">AKSI</th></tr></thead>
            <tbody>
                @forelse($efaktur as $faktur)
                <tr data-invoice="{{ $faktur['invoice_number'] }}" data-serial="{{ $faktur['tax_serial'] }}" data-order="{{ $faktur['order_number'] }}" data-shop="{{ $faktur['shop'] }}" data-dpp="{{ $faktur['dpp'] }}" data-rate="{{ $faktur['ppn_rate'] }}" data-ppn="{{ $faktur['ppn'] }}" data-total="{{ $faktur['grand_total'] }}" data-issued="{{ $faktur['issued_at'] }}">
                    <td><code class="small">{{ $faktur['invoice_number'] }}</code><small class="d-block text-secondary">{{ $faktur['order_number'] }}</small></td>
                    <td><code class="small">{{ $faktur['tax_serial'] }}</code></td>
                    <td>{{ $faktur['shop'] }}</td>
                    <td class="text-end">{{ $faktur['dpp_formatted'] }}</td>
                    <td class="text-end">{{ $faktur['ppn_formatted'] }} <small class="text-secondary">({{ rtrim(rtrim(number_format($faktur['ppn_rate'], 2, ',', '.'), '0'), ',') }}%)</small></td>
                    <td class="text-end text-nowrap">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="efakturPrint(this)">Cetak</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="efakturDownload(this)">Unduh</button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6"><x-admin.empty-state icon="receipt" title="Belum ada e-Faktur" text="Faktur terbit otomatis per order saat settlement berjalan." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.card>
<div id="efaktur-print" class="d-none">
    <h3>e-Faktur Pajak</h3>
    <table>
        <tr><td>No. Invoice</td><td id="efp-invoice"></td></tr>
        <tr><td>Nomor Seri Pajak</td><td id="efp-serial"></td></tr>
        <tr><td>No. Order</td><td id="efp-order"></td></tr>
        <tr><td>Toko</td><td id="efp-shop"></td></tr>
        <tr><td>DPP</td><td id="efp-dpp"></td></tr>
        <tr><td>PPN</td><td id="efp-ppn"></td></tr>
        <tr><td>Total</td><td id="efp-total"></td></tr>
        <tr><td>Diterbitkan</td><td id="efp-issued"></td></tr>
    </table>
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
function efakturRow(btn){return btn.closest('tr');}
function efakturCsv(row){
    const d=row.dataset;
    const lines=[['e-Faktur Pajak'],['No. Invoice',d.invoice],['Nomor Seri Pajak',d.serial],['No. Order',d.order],['Toko',d.shop],['DPP',d.dpp],['Tarif PPN (%)',d.rate],['PPN',d.ppn],['Total',d.total],['Diterbitkan',d.issued]];
    return lines.map(r=>r.map(c=>'"'+String(c??'').replaceAll('"','""')+'"').join(',')).join('\r\n');
}
function efakturDownload(btn){
    const blob=new Blob([efakturCsv(efakturRow(btn))],{type:'text/csv;charset=utf-8'});
    const a=document.createElement('a');
    a.href=URL.createObjectURL(blob);
    a.download=efakturRow(btn).dataset.invoice+'.csv';
    document.body.appendChild(a);a.click();a.remove();
    setTimeout(()=>URL.revokeObjectURL(a.href),2000);
}
function efakturPrint(btn){
    const d=efakturRow(btn).dataset;
    document.getElementById('efp-invoice').textContent=d.invoice;
    document.getElementById('efp-serial').textContent=d.serial;
    document.getElementById('efp-order').textContent=d.order;
    document.getElementById('efp-shop').textContent=d.shop;
    document.getElementById('efp-dpp').textContent=d.dpp;
    document.getElementById('efp-ppn').textContent=d.ppn+' ('+d.rate+'%)';
    document.getElementById('efp-total').textContent=d.total;
    document.getElementById('efp-issued').textContent=d.issued;
    window.print();
}
</script>
<style>@media print{body *{visibility:hidden;}#efaktur-print,#efaktur-print *{visibility:visible;}#efaktur-print{display:block!important;position:absolute;inset:0;padding:24px;background:#fff;}#efaktur-print table{border-collapse:collapse;width:100%;}#efaktur-print td{border:1px solid #999;padding:6px 10px;}}</style>
@endpush
