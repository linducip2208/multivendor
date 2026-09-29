@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Pendapatan')
@section('subtitle', $range->from->format('d M Y').' s/d '.$range->to->format('d M Y'))

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Keuangan', 'href' => route('vendor.finance.payouts')],
    ['label' => 'Pendapatan'],
])

@section('content')
    @include('admin.partials.date-range')

    <x-admin.tabs class="mb-3" :tabs="[
        ['label' => 'Pendapatan', 'href' => route('vendor.finance.revenue'), 'active' => true, 'icon' => 'wallet'],
        ['label' => 'Komisi', 'href' => route('vendor.finance.commission'), 'icon' => 'percent'],
        ['label' => 'Pencairan', 'href' => route('vendor.finance.payouts'), 'icon' => 'cash-coin'],
    ]" />

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl">
            <x-admin.stat label="Kotor" :value="$gross->toFloat()" icon="wallet" color="primary" :trend="$growth['value']" trend-label="vs periode lalu" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Bersih" :value="$net->toFloat()" icon="cash-coin" color="success" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Pesanan" :value="$orders" icon="shopping-cart" color="info" :trend="$order_growth['value']" trend-label="vs periode lalu" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Nilai rata-rata" :value="$aov->toFloat()" icon="chart-line" color="warning" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Pajak" :value="$tax->toFloat()" icon="receipt" color="secondary" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Ongkir" :value="$shipping->toFloat()" icon="truck" color="secondary" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Diskon" :value="$discount->toFloat()" icon="tag" color="danger" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Komisi platform" :value="$commission->toFloat()" icon="percent" color="dark" />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <x-admin.chart
                id="vendor-revenue-series"
                title="Pendapatan harian"
                :labels="collect($series)->pluck('label')->all()"
                :data="[['label' => 'Pendapatan', 'data' => collect($series)->pluck('revenue')->map(fn ($value) => (float) $value)->all()]]"
                :height="320"
                filled
            />
        </div>

        <div class="col-12 col-xl-4">
            <x-admin.card title="Sumber pendapatan" icon="target" :padding="false">
                <x-admin.table dense>
                    <x-slot:table>
                        \App\Support\TableBuilder::make()
                            ->columns([
                                'source' => ['label' => 'Sumber'],
                                'orders' => ['label' => 'Pesanan', 'align' => 'end'],
                                'revenue' => ['label' => 'Nilai', 'align' => 'end'],
                            ])
                            ->rows(
                                collect($sources)->map(fn (array $row) => [
                                    'source' => '<span class="text-truncate d-block fw-medium">'.e($row['label']).'</span>',
                                    'orders' => e(Currency::number($row['orders'])),
                                    'revenue' => '<span class="fw-medium">'.e(Currency::format($row['revenue']->toFloat())).'</span>',
                                ])->all()
                            )
                            ->empty('Tidak ada penjualan pada periode ini.')
                    </x-slot:table>
                </x-admin.table>
            </x-admin.card>
        </div>
    </div>

    @php
        try {
            $plScope = new \App\Services\Vendor\VendorScope;
            $plService = app(\App\Services\Vendor\VendorFinanceService::class, ['scope' => $plScope]);
            $plShopId = $plScope->shopId();
            $plFrom = request('pl_from', $range->from->format('Y-m-d'));
            $plTo = request('pl_to', $range->to->format('Y-m-d'));
            $pl = $plService->profitLoss($plShopId, $plFrom.' 00:00:00', $plTo.' 23:59:59');
        } catch (\Throwable $e) {
            $pl = null; $plFrom = null; $plTo = null;
        }
    @endphp
    @if($pl)
    <x-admin.card title="Laba / Rugi per Toko per Periode" icon="book" class="mt-3" :padding="false">
        <div class="card-body">
            <form method="GET" action="{{ url()->current() }}" class="row g-2 align-items-end">
                @foreach(request()->except(['pl_from', 'pl_to']) as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <div class="col-6 col-md-3">
                    <label class="form-label small text-secondary">Dari</label>
                    <input type="date" name="pl_from" class="form-control" value="{{ $plFrom }}">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small text-secondary">Sampai</label>
                    <input type="date" name="pl_to" class="form-control" value="{{ $plTo }}">
                </div>
                <div class="col-6 col-md-3">
                    <button class="btn btn-outline-primary w-100" type="submit">Terapkan periode</button>
                </div>
                <div class="col-6 col-md-3">
                    <button class="btn btn-primary w-100" type="button" onclick="plDownloadCsv()">Unduh CSV akuntansi</button>
                </div>
            </form>
            <div class="row g-3 mt-1">
                <div class="col-6 col-xl-2"><x-admin.stat label="Bruto" :value="$pl['gross']" icon="wallet" color="primary" /></div>
                <div class="col-6 col-xl-2"><x-admin.stat label="Pajak" :value="$pl['tax']" icon="receipt" color="secondary" /></div>
                <div class="col-6 col-xl-2"><x-admin.stat label="Ongkir" :value="$pl['shipping']" icon="truck" color="secondary" /></div>
                <div class="col-6 col-xl-2"><x-admin.stat label="Diskon" :value="$pl['discount']" icon="tag" color="danger" /></div>
                <div class="col-6 col-xl-2"><x-admin.stat label="Komisi" :value="$pl['commission']" icon="percent" color="dark" /></div>
                <div class="col-6 col-xl-2"><x-admin.stat label="Bersih" :value="$pl['net']" icon="cash-coin" color="success" /></div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="pl-table">
                <thead class="table-light"><tr><th class="text-uppercase small">PESANAN</th><th class="text-uppercase small">TANGGAL</th><th class="text-uppercase small text-end">BRUTO</th><th class="text-uppercase small text-end">PAJAK</th><th class="text-uppercase small text-end">DISKON</th><th class="text-uppercase small text-end">KOMISI</th><th class="text-uppercase small text-end">BERSIH</th></tr></thead>
                <tbody>
                    @forelse($pl['rows'] as $row)
                    <tr><td><code class="small">{{ $row['order_number'] }}</code></td><td class="text-nowrap">{{ $row['date'] }}</td><td class="text-end">{{ Currency::format($row['gross']) }}</td><td class="text-end">{{ Currency::format($row['tax']) }}</td><td class="text-end">{{ Currency::format($row['discount']) }}</td><td class="text-end text-danger">{{ Currency::format($row['commission']) }}</td><td class="text-end fw-semibold">{{ Currency::format($row['net']) }}</td></tr>
                    @empty
                    <tr><td colspan="7"><x-admin.empty-state icon="book" title="Tidak ada order" text="Tidak ada penjualan pada periode ini." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>
    @push('scripts')
    <script>
    function plDownloadCsv(){
        const rows=[['Nomor Pesanan','Tanggal','Bruto','Pajak','Diskon','Komisi','Bersih']];
        document.querySelectorAll('#pl-table tbody tr').forEach(tr=>{
            const cells=[...tr.querySelectorAll('td')];
            if(cells.length>=7) rows.push(cells.slice(0,7).map(td=>td.innerText.trim()));
        });
        const csv=rows.map(r=>r.map(c=>'"'+String(c).replaceAll('"','""')+'"').join(',')).join('\r\n');
        const a=document.createElement('a');
        a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8'}));
        a.download='laba-rugi-{{ $plFrom }}-{{ $plTo }}.csv';
        document.body.appendChild(a);a.click();a.remove();
        setTimeout(()=>URL.revokeObjectURL(a.href),2000);
    }
    </script>
    @endpush
    @endif
@endsection
