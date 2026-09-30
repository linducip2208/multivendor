@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Pengembalian Dana')
@section('subtitle', 'Pengajuan pengembalian dana pada pesanan toko Anda')

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Pengembalian Dana'],
])

@section('actions')
    <a href="{{ route('vendor.returns.index') }}" class="btn btn-outline-secondary">
        <x-admin.icon name="rotate-ccw" :size="16" class="me-1" />
        <span>Retur</span>
    </a>
@endsection

@section('content')
    @php
        $statusOptions = ['' => 'Semua status'] + collect([
            'none' => 'Tidak diminta',
            'requested' => ['Diajukan', 'warning'],
            'approved' => ['Disetujui', 'info'],
            'rejected' => ['Ditolak', 'danger'],
            'refunded' => ['Selesai', 'success'],
        ])->map(fn ($row, $key) => $key.' ('.Currency::number((int) ($counts[$key] ?? 0)).')')->all();
    @endphp

    <x-admin.filters
        :action="route('vendor.refund.index')"
        :filters="[
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'value' => $status, 'options' => $statusOptions],
            ['name' => 'search', 'label' => 'Nomor pesanan', 'placeholder' => 'Cari pesanan'],
        ]"
    />

    <x-admin.card :padding="false">
        <x-admin.table>
            <x-slot:table>
                \App\Support\TableBuilder::make()
                    ->columns([
                        'item' => ['label' => 'Produk', 'width' => '28%'],
                        'order' => ['label' => 'Pesanan'],
                        'amount' => ['label' => 'Nominal', 'align' => 'end'],
                        'reason' => ['label' => 'Alasan'],
                        'status' => ['label' => 'Status'],
                        'date' => ['label' => 'Diminta', 'align' => 'end'],
                        'actions' => ['label' => '', 'align' => 'end', 'width' => '200px'],
                    ])
                    ->rows(
                        $refunds->map(fn ($item) => [
                            'item' => '<span class="fw-medium d-block text-truncate">'.e($item->product?->name ?? 'Produk dihapus').'</span><span class="text-secondary small">'.e($item->quantity).' unit</span>',
                            'order' => '<a href="'.route('vendor.orders.show', $item->order_id).'" class="fw-medium">'.e($item->order?->order_number ?? '—').'</a>',
                            'amount' => '<span class="fw-medium">'.e(Currency::format($item->refund_amount ?: $item->sub_total)).'</span>',
                            'reason' => '<span class="text-secondary small">'.e(\Illuminate\Support\Str::limit((string) $item->refund_reason, 60) ?: '—').'</span>',
                            'status' => $__status($item->refund_status),
                            'date' => '<span class="text-secondary small">'.e($item->refund_requested_at ? \Carbon\Carbon::parse($item->refund_requested_at)->format('d/m/Y') : '—').'</span>',
                            'actions' => in_array($item->refund_status, $decisions, true)
                                ? '<form method="POST" action="'.route('vendor.refund.update', $item->id).'" class="row g-1 justify-content-end" data-confirm="Kirim keputusan pengembalian dana untuk produk ini?">'
                                    .csrf_field().'@method("PUT")'
                                    .'<div class="col-auto"><input type="hidden" name="status" value="approved">'
                                    .'<button type="submit" class="btn btn-sm btn-success">Setujui</button></div>'
                                    .'<div class="col-auto"><input type="hidden" name="status" value="rejected">'
                                    .'<button type="submit" class="btn btn-sm btn-outline-danger">Tolak</button></div>'
                                    .'</form>'
                                : '<span class="text-secondary small">Menunggu keputusan</span>',
                        ])->all()
                    )
                    ->empty('Belum ada pengajuan pengembalian dana.')
            </x-slot:table>
        </x-admin.table>
    </x-admin.card>

    <x-admin.pagination :paginator="$refunds" class="mt-3" />

    <x-admin.card>
        <h3 class="h6 mb-2">Analitik alasan retur</h3>
        <p class="text-secondary small mb-2">Retur dicatat per item dengan alasan terstruktur.</p>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>Alasan</th><th class="text-end">Jumlah</th><th class="text-end">Nominal</th></tr></thead>
                <tbody>
                    @foreach (($reasonAnalytics ?? []) as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td class="text-end">{{ $row['total'] }}</td>
                            <td class="text-end">{{ Currency::format($row['nominal']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-admin.card>
@endsection
