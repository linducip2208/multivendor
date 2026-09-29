@extends('layouts.admin')

@section('title', 'Pelanggan '.$customer['name'])

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Pelanggan', ['label' => 'Pelanggan', 'href' => route('admin.customers.index')], ['label' => $customer['name']]]" />
@endsection

@section('content')
    <x-admin.page-header :title="$customer['name']" :subtitle="'Bergabung '.$customer['registered_at'].' · '.$customer['email']">
        <x-slot:actions>
            @if ($customer['phone'] !== '')
                <a href="tel:{{ $customer['phone'] }}" class="btn btn-outline-secondary btn-sm">
                    <x-admin.icon name="phone" :size="14" /> {{ $customer['phone'] }}
                </a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Nilai Seumur Hidup" :value="$customer['summary']['ltv']" money icon="cash" color="success" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Jumlah Pesanan" :value="$customer['summary']['order_count']" icon="shopping-bag" color="primary" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Rata-rata Pesanan" :value="$customer['summary']['aov']" money icon="calculator" color="info" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Poin Loyalty" :value="$customer['loyalty']['points_formatted']" icon="award" color="warning" />
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <x-admin.card title="Pesanan Terakhir" icon="shopping-bag" flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Nomor</th>
                                <th scope="col">Toko</th>
                                <th scope="col" class="text-center">Item</th>
                                <th scope="col" class="text-center">Status</th>
                                <th scope="col" class="text-end">Total</th>
                                <th scope="col">Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($customer['orders'] as $order)
                                <tr>
                                    <td><a href="{{ $order['url'] }}">{{ $order['order_number'] }}</a></td>
                                    <td>{{ $order['shop'] }}</td>
                                    <td class="text-end">{{ $order['items'] }}</td>
                                    <td class="text-center">
                                        <x-admin.badge
                                            :text="\App\Enums\OrderStatus::fromStored($order['order_status'])->label()"
                                            :color="\App\Enums\OrderStatus::fromStored($order['order_status'])->badge()"
                                            pill
                                        />
                                    </td>
                                    <td class="text-end fw-semibold">{{ $order['total_formatted'] }}</td>
                                    <td class="text-nowrap">{{ $order['created_at'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <x-admin.empty-state compact icon="shopping-bag" title="Belum ada pesanan" text="Pelanggan ini belum pernah melakukan pesanan." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>

        <div class="col-lg-4">
            <x-admin.card title="Profil" icon="user" class="mb-3">
                <dl class="row small mb-0">
                    <dt class="col-5 text-secondary">Email</dt>
                    <dd class="col-7 text-end text-break">{{ $customer['email'] }}</dd>
                    <dt class="col-5 text-secondary">Telepon</dt>
                    <dd class="col-7 text-end">{{ $customer['phone'] !== '' ? $customer['phone'] : '-' }}</dd>
                    <dt class="col-5 text-secondary">Kode referral</dt>
                    <dd class="col-7 text-end"><code>{{ $customer['referral_code'] !== '' ? $customer['referral_code'] : '-' }}</code></dd>
                    <dt class="col-5 text-secondary">Dompet</dt>
                    <dd class="col-7 text-end">{{ \App\Support\Currency::format($customer['wallet']['balance']) }}</dd>
                    <dt class="col-5 text-secondary">Dompet tertahan</dt>
                    <dd class="col-7 text-end">{{ \App\Support\Currency::format($customer['wallet']['pending']) }}</dd>
                </dl>
            </x-admin.card>

            <x-admin.card title="Pola Pembelian" icon="trending-up" class="mb-3">
                <dl class="row small mb-0">
                    <dt class="col-6 text-secondary">Pesanan pertama</dt>
                    <dd class="col-6 text-end">{{ $customer['summary']['first_order_at'] ?? '-' }}</dd>
                    <dt class="col-6 text-secondary">Pesanan terakhir</dt>
                    <dd class="col-6 text-end">{{ $customer['summary']['last_order_at'] ?? '-' }}</dd>
                    <dt class="col-6 text-secondary">Sejak pesanan terakhir</dt>
                    <dd class="col-6 text-end">{{ $customer['summary']['last_order_human'] }}</dd>
                    <dt class="col-6 text-secondary">Interval rata-rata</dt>
                    <dd class="col-6 text-end">{{ $customer['frequency']['interval_label'] }}</dd>
                    <dt class="col-6 text-secondary">Pesanan / bulan</dt>
                    <dd class="col-6 text-end">{{ number_format($customer['frequency']['per_month'], 2, ',', '.') }}</dd>
                    <dt class="col-6 text-secondary">Kupon dipakai</dt>
                    <dd class="col-6 text-end">{{ number_format($customer['coupons']['used'], 0, ',', '.') }}</dd>
                </dl>
            </x-admin.card>

            @if ($customer['segments'] !== [])
                <x-admin.card title="Segmen" icon="layers">
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($customer['segments'] as $segment)
                            <x-admin.badge :text="$segment['name']" color="primary" pill />
                        @endforeach
                    </div>
                </x-admin.card>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <x-admin.card title="Kategori Favorit" icon="category" flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Kategori</th>
                                <th scope="col" class="text-end">Unit</th>
                                <th scope="col" class="text-end">Belanja</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($customer['top_categories'] as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td class="text-end">{{ number_format($row['quantity'], 0, ',', '.') }}</td>
                                    <td class="text-end fw-semibold">{{ $row['spend_formatted'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3"><x-admin.empty-state compact icon="category" title="Belum ada data" text="Belum ada pembelian berkategori." /></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>
        <div class="col-lg-6">
            <x-admin.card title="Toko Favorit" icon="store" flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Toko</th>
                                <th scope="col" class="text-end">Pesanan</th>
                                <th scope="col" class="text-end">Belanja</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($customer['top_shops'] as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td class="text-end">{{ number_format($row['orders'], 0, ',', '.') }}</td>
                                    <td class="text-end fw-semibold">{{ $row['spend_formatted'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3"><x-admin.empty-state compact icon="store" title="Belum ada data" text="Belum ada pembelian pada toko mana pun." /></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <x-admin.card title="Aktivitas" icon="activity">
                <x-admin.activity-feed :items="$customer['activity']" :empty="'Belum ada aktivitas tercatat.'" />
            </x-admin.card>
        </div>
        <div class="col-lg-7">
            <x-admin.card title="Favorit" icon="heart" :subtitle="$customer['wishlist']['count'].' produk ditandai.'" class="mb-3">
                <div class="row g-2">
                    @forelse ($customer['wishlist']['items'] as $item)
                        <div class="col-12 col-sm-6">
                            <div class="border rounded-3 p-2 h-100">
                                <a href="{{ $item['url'] }}" class="fw-semibold d-block">{{ $item['name'] }}</a>
                                <small class="text-secondary">{{ $item['price_formatted'] }} · stok {{ number_format($item['stock'], 0, ',', '.') }}</small>
                            </div>
                        </div>
                    @empty
                        <div class="col-12">
                            <x-admin.empty-state compact icon="heart" title="Favorit kosong" text="Pelanggan belum menandai produk apa pun." />
                        </div>
                    @endforelse
                </div>
            </x-admin.card>

            <x-admin.card title="Ulasan" icon="star" :subtitle="'Rata-rata '.$customer['reviews']['average'].' dari '.$customer['reviews']['count'].' ulasan.'">
                <x-admin.activity-feed :items="$customer['reviews']['items']" :empty="'Belum ada ulasan dari pelanggan ini.'" />
            </x-admin.card>
        </div>
    </div>
@endsection
