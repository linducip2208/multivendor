@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', $customer->name)
@section('subtitle', $customer->email)

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Pelanggan', 'href' => route('vendor.customers.index')],
    ['label' => $customer->name],
])

@section('actions')
    <a href="{{ route('vendor.chat.customer', $customer->id) }}" class="btn btn-primary">
        <x-admin.icon name="message-circle" :size="16" class="me-1" />
        <span>Chat</span>
    </a>
@endsection

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl">
            <x-admin.stat label="Segmen" :value="$segments[$stats['segment']] ?? $stats['segment']" icon="award" color="primary" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Total pesanan" :value="$stats['orders']" icon="shopping-cart" color="info" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Total belanja" :value="$stats['spend']->toFloat()" icon="wallet" color="success" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Nilai rata-rata" :value="$stats['aov']->toFloat()" icon="chart-line" color="warning" />
        </div>
        <div class="col-12 col-xl">
            <x-admin.stat label="Rating rata-rata" :value="number_format($stats['average_rating'], 2, ',', '.')" icon="star" color="danger" />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <x-admin.card title="Riwayat pesanan" icon="list" :padding="false">
                <x-admin.table dense>
                    <x-slot:table>
                        \App\Support\TableBuilder::make()
                            ->columns([
                                'order' => ['label' => 'Pesanan'],
                                'items' => ['label' => 'Item', 'align' => 'end'],
                                'status' => ['label' => 'Status'],
                                'payment' => ['label' => 'Pembayaran'],
                                'total' => ['label' => 'Total', 'align' => 'end'],
                                'date' => ['label' => 'Tanggal', 'align' => 'end'],
                            ])
                            ->rows(
                                $orders->map(fn ($order) => [
                                    'order' => '<a href="'.route('vendor.orders.show', $order).'" class="fw-medium">'.e($order->order_number).'</a>',
                                    'items' => e(Currency::number($order->items_count ?? $order->items->count())),
                                    'status' => $__orderStatus($order->order_status),
                                    'payment' => $__paymentStatus($order->payment_status),
                                    'total' => '<span class="fw-medium">'.e(Currency::format($order->total)).'</span>',
                                    'date' => '<span class="text-secondary small">'.e($order->created_at->format('d/m/Y')).'</span>',
                                ])->all()
                            )
                            ->empty('Pelanggan ini belum pernah memesan.')
                    </x-slot:table>
                </x-admin.table>
            </x-admin.card>

            <x-admin.card title="Produk yang dibeli" icon="package" class="mt-3" :padding="false">
                <x-admin.table dense>
                    <x-slot:table>
                        \App\Support\TableBuilder::make()
                            ->columns([
                                'product' => ['label' => 'Produk'],
                                'units' => ['label' => 'Unit', 'align' => 'end'],
                                'spend' => ['label' => 'Belanja', 'align' => 'end'],
                            ])
                            ->rows(
                                $products->map(fn ($row) => [
                                    'product' => '<span class="fw-medium d-block text-truncate">'.e($row->product_name).'</span>',
                                    'units' => e(Currency::number($row->units)),
                                    'spend' => '<span class="fw-medium">'.e(Currency::format($row->spend)).'</span>',
                                ])->all()
                            )
                            ->empty('Belum ada produk yang dibeli.')
                    </x-slot:table>
                </x-admin.table>
            </x-admin.card>

            <x-admin.card title="Ulasan" icon="star" class="mt-3" :padding="false">
                <x-admin.table dense>
                    <x-slot:table>
                        \App\Support\TableBuilder::make()
                            ->columns([
                                'rating' => ['label' => 'Rating', 'width' => '110px'],
                                'product' => ['label' => 'Produk'],
                                'comment' => ['label' => 'Ulasan'],
                                'date' => ['label' => 'Tanggal', 'align' => 'end'],
                            ])
                            ->rows(
                                $reviews->map(fn ($review) => [
                                    'rating' => '<span class="text-warning">'.str_repeat('<i class="fa-solid fa-star"></i>', (int) $review->rating).'</span>',
                                    'product' => '<span class="text-truncate d-block">'.e($review->product?->name ?? '—').'</span>',
                                    'comment' => e(\Illuminate\Support\Str::limit((string) $review->comment, 140)),
                                    'date' => '<span class="text-secondary small">'.e($review->created_at->format('d/m/Y')).'</span>',
                                ])->all()
                            )
                            ->empty('Belum ada ulasan dari pelanggan ini.')
                    </x-slot:table>
                </x-admin.table>
            </x-admin.card>
        </div>

        <div class="col-12 col-xl-4">
            <x-admin.card title="Kontak" icon="user">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-secondary fw-normal">Nama</dt>
                    <dd class="col-7">{{ $customer->name }}</dd>
                    <dt class="col-5 text-secondary fw-normal">Email</dt>
                    <dd class="col-7 text-break">{{ $customer->email ?: '—' }}</dd>
                    <dt class="col-5 text-secondary fw-normal">Telepon</dt>
                    <dd class="col-7">{{ $customer->phone ?: '—' }}</dd>
                    <dt class="col-5 text-secondary fw-normal">Terdaftar</dt>
                    <dd class="col-7">{{ $customer->created_at?->format('d M Y') }}</dd>
                </dl>
            </x-admin.card>

            <x-admin.card title="Aktivitas terakhir" icon="activity" class="mt-3">
                <x-admin.activity-feed
                    :items="$activity->map(fn ($entry) => [
                        'actor' => $entry->subject ?? 'Pelanggan',
                        'action' => \Illuminate\Support\Str::headline((string) $entry->activity),
                        'at' => \Carbon\Carbon::parse($entry->created_at)->diffForHumans(short: true),
                        'icon' => 'activity',
                    ])->all()"
                    empty="Belum ada aktivitas tercatat."
                />
            </x-admin.card>
        </div>
    </div>
@endsection
