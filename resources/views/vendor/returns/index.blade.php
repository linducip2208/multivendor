@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Retur pelanggan')
@section('subtitle', 'Permintaan retur pada pesanan toko Anda')

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Retur'],
])

@section('actions')
    <a href="{{ route('vendor.refund.index') }}" class="btn btn-outline-secondary">
        <x-admin.icon name="rotate-ccw" :size="16" class="me-1" />
        <span>Pengajuan pengembalian dana</span>
    </a>
@endsection

@section('content')
    @php
        $statusOptions = ['' => 'Semua status'] + collect([
            'requested' => ['Diajukan', 'warning'],
            'approved' => ['Disetujui', 'info'],
            'received' => ['Diterima', 'primary'],
            'rejected' => ['Ditolak', 'danger'],
            'completed' => ['Selesai', 'success'],
        ])->map(fn ($row, $key) => $key.' ('.Currency::number((int) ($counts[$key] ?? 0)).')')->all();
    @endphp

    <x-admin.filters
        :action="route('vendor.returns.index')"
        :filters="[['name' => 'status', 'label' => 'Status', 'type' => 'select', 'value' => $status, 'options' => $statusOptions]]"
    />

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl">
            <x-admin.stat label="Total retur" :value="Currency::number($returns->total())" icon="rotate-ccw" color="primary" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Nilai pada halaman ini" :value="$value->toFloat()" icon="wallet" color="danger" />
        </div>
    </div>

    <x-admin.card :padding="false">
        <x-admin.table>
            <x-slot:table>
                \App\Support\TableBuilder::make()
                    ->columns([
                        'rma' => ['label' => 'No. RMA', 'width' => '180px'],
                        'order' => ['label' => 'Pesanan'],
                        'item' => ['label' => 'Produk'],
                        'reason' => ['label' => 'Alasan'],
                        'amount' => ['label' => 'Nilai', 'align' => 'end'],
                        'status' => ['label' => 'Status'],
                        'date' => ['label' => 'Diajukan', 'align' => 'end'],
                    ])
                    ->rows(
                        $returns->map(fn ($return) => [
                            'rma' => '<span class="font-monospace fw-medium">'.e($return->rma_number).'</span>',
                            'order' => '<a href="'.route('vendor.orders.show', $return->order_id).'" class="fw-medium">'.e($return->order?->order_number ?? '—').'</a>',
                            'item' => '<span class="text-truncate d-block">'.e($return->orderItem?->product_name ?? 'Seluruh pesanan').'</span>',
                            'reason' => '<span class="text-secondary small">'.e(\Illuminate\Support\Str::limit($return->description ?? $return->reason, 60)).'</span>',
                            'amount' => '<span class="fw-medium">'.e(Currency::format($return->amount)).'</span>',
                            'status' => $__status($return->status),
                            'date' => '<span class="text-secondary small">'.e(\Carbon\Carbon::parse($return->created_at)->format('d/m/Y')).'</span>',
                        ])->all()
                    )
                    ->empty('Belum ada permintaan retur.')
            </x-slot:table>
        </x-admin.table>
    </x-admin.card>

    <x-admin.pagination :paginator="$returns" class="mt-3" />
@endsection
