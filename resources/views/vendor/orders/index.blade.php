@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Pesanan')
@section('subtitle', 'Seluruh pesanan yang masuk ke toko Anda')

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Pesanan'],
])

@section('actions')
    <a href="{{ route('vendor.fulfillment.index') }}" class="btn btn-primary">
        <x-admin.icon name="truck" :size="16" class="me-1" />
        <span>Antrean kirim</span>
    </a>
@endsection

@section('content')
    <div class="row g-2 mb-3">
        @foreach ($statusCases as $case)
            @php $count = (int) ($statusCounts[$case->stored()] ?? 0); @endphp
            @if ($count === 0 && $status !== $case->stored())
                @continue
            @endif
            <div class="col-6 col-md-4 col-xl-2">
                <a href="{{ route('vendor.orders.index', ['status' => $case->stored()]) }}" class="text-decoration-none">
                    <x-admin.card class="h-100 {{ $status === $case->stored() ? 'border-primary' : '' }}">
                        <div class="text-center py-1">
                            <div class="h3 mb-0 text-{{ $case->badge() }}">{{ $count }}</div>
                            <div class="text-secondary small">{{ $case->label() }}</div>
                        </div>
                    </x-admin.card>
                </a>
            </div>
        @endforeach
    </div>

    <x-admin.filters
        :action="route('vendor.orders.index')"
        :filters="[
            ['name' => 'search', 'label' => 'Cari pesanan', 'placeholder' => 'Nomor pesanan'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'value' => $status, 'options' => ['' => 'Semua status'] + collect($statusCases)->mapWithKeys(fn ($case) => [$case->stored() => $case->label()])->all()],
        ]"
    />

    <x-admin.card :padding="false">
        <x-admin.table dense>
            <x-slot:table>
                \App\Support\TableBuilder::make()
                    ->columns([
                        'order' => ['label' => 'Pesanan', 'width' => '22%'],
                        'customer' => ['label' => 'Pelanggan'],
                        'items' => ['label' => 'Item', 'align' => 'end'],
                        'status' => ['label' => 'Status'],
                        'payment' => ['label' => 'Pembayaran'],
                        'total' => ['label' => 'Total', 'align' => 'end'],
                        'date' => ['label' => 'Tanggal', 'align' => 'end'],
                        'actions' => ['label' => '', 'align' => 'end', 'width' => '190px'],
                    ])
                    ->rows(
                        $orders->map(fn ($order) => [
                            'order' => '<a href="'.route('vendor.orders.show', $order).'" class="fw-medium">'.e($order->order_number).'</a>'
                                .($order->source === 'pos' ? '<span class="badge bg-secondary-lt text-secondary ms-1">POS</span>' : ''),
                            'customer' => '<span class="text-truncate d-block">'.e($order->customer?->name ?? 'Pelanggan').'</span>',
                            'items' => e(Currency::number($order->items_count ?? $order->items->sum('quantity'))),
                            'status' => $__orderStatus($order->order_status),
                            'payment' => $__paymentStatus($order->payment_status),
                            'total' => '<span class="fw-medium text-nowrap">'.e(Currency::format($order->total)).'</span>',
                            'date' => '<span class="text-secondary small">'.e($order->created_at->format('d/m/Y H:i')).'</span>',
                            'actions' => '<div class="d-flex gap-1 justify-content-end">'
                                .'<a href="'.route('vendor.orders.show', $order).'" class="btn btn-sm btn-ghost-light">Detail</a>'
                                .(in_array($order->order_status, \App\Services\Vendor\OrderEditService::editableStatuses(), true)
                                    ? '<a href="'.route('vendor.orders.edit', $order).'" class="btn btn-sm btn-ghost-light">Ubah</a>'
                                    : '')
                                .'</div>',
                        ])->all()
                    )
                    ->empty('Tidak ada pesanan yang cocok.')
            </x-slot:table>
        </x-admin.table>
    </x-admin.card>

    <x-admin.pagination :paginator="$orders" class="mt-3" />
@endsection
