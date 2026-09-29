@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Pelanggan')
@section('subtitle', 'Pembeli yang pernah memesan di toko Anda')

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Pelanggan'],
])

@section('actions')
    <a href="{{ route('vendor.analytics.customers') }}" class="btn btn-outline-secondary">
        <x-admin.icon name="chart-bar" :size="16" class="me-1" />
        <span>Analitik</span>
    </a>
@endsection

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl">
            <x-admin.stat label="Total pelanggan" :value="$stats['total']" icon="users" color="primary" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="VIP" :value="$stats['vip']" icon="award" color="warning" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Pembeli berulang" :value="$stats['repeat']" icon="refresh" color="success" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Pembeli baru" :value="$stats['new']" icon="user-plus" color="info" />
        </div>
    </div>

    <x-admin.filters
        :action="route('vendor.customers.index')"
        :filters="[
            ['name' => 'search', 'label' => 'Cari pelanggan', 'placeholder' => 'Nama, email, atau telepon'],
            ['name' => 'segment', 'label' => 'Segmen', 'type' => 'select', 'value' => $selected, 'options' => ['' => 'Semua segmen'] + $segments],
        ]"
    />

    <x-admin.card :padding="false">
        <x-admin.table dense>
            <x-slot:table>
                \App\Support\TableBuilder::make()
                    ->columns([
                        'customer' => ['label' => 'Pelanggan', 'width' => '34%'],
                        'orders' => ['label' => 'Pesanan', 'align' => 'end'],
                        'spend' => ['label' => 'Total belanja', 'align' => 'end'],
                        'last' => ['label' => 'Pesanan terakhir', 'align' => 'end'],
                        'joined' => ['label' => 'Terdaftar', 'align' => 'end'],
                    ])
                    ->rows(
                        $customers->map(fn ($customer) => [
                            'customer' => '<a href="'.route('vendor.customers.show', $customer->id).'" class="d-block text-reset text-truncate">'
                                .'<span class="d-block text-truncate fw-medium">'.e($customer->name).'</span>'
                                .'<span class="d-block text-secondary small text-truncate">'.e($customer->email ?: '—').'</span></a>',
                            'orders' => e(Currency::number($customer->total_orders)),
                            'spend' => '<span class="fw-medium">'.e(Currency::format($customer->total_spend)).'</span>',
                            'last' => '<span class="text-secondary small">'.e(\Carbon\Carbon::parse($customer->last_order_at)->diffForHumans(short: true)).'</span>',
                            'joined' => '<span class="text-secondary small">'.e(\Carbon\Carbon::parse($customer->created_at)->format('d M Y')).'</span>',
                        ])->all()
                    )
                    ->empty('Belum ada pelanggan yang memesan di toko Anda.')
            </x-slot:table>
        </x-admin.table>
    </x-admin.card>

    <x-admin.pagination :paginator="$customers" class="mt-3" />
@endsection
