@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Dasbor')
@section('subtitle', $shop->name)

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Dasbor'],
])

@section('actions')
    <a href="{{ route('vendor.orders.index') }}" class="btn btn-outline-secondary">
        <x-admin.icon name="list" :size="16" class="me-1" />
        <span>Pesanan</span>
    </a>
    <a href="{{ route('vendor.products.create') }}" class="btn btn-primary">
        <x-admin.icon name="plus" :size="16" class="me-1" />
        <span>Tambah Produk</span>
    </a>
@endsection

@section('content')
    @if ($shopStatus['tone'] !== 'active')
        <x-admin.alert :type="$shopStatus['tone'] === 'suspended' ? 'danger' : 'warning'" :title="$shopStatus['label']">
            Toko Anda berstatus <strong>{{ $shopStatus['label'] }}</strong>. Produk baru dan transaksi baru
            ditahan sampai status toko kembali aktif.
            @if ($shopStatus['tone'] === 'suspended')
                Hubungi admin platform untuk informasi lebih lanjut.
            @endif
        </x-admin.alert>
    @endif

    <x-admin.filters
        :action="route('vendor.dashboard')"
        :filters="[
            ['name' => 'days', 'label' => 'Periode', 'type' => 'select', 'value' => $from->diffInDays($to) + 1, 'options' => [
                7 => '7 hari',
                30 => '30 hari',
                90 => '90 hari',
                180 => '180 hari',
                365 => '365 hari',
            ]],
        ]"
    />

    @isset($completeness)
        <x-admin.card title="Kelengkapan toko" icon="check-circle" class="mb-3">
            <div class="d-flex align-items-center gap-3 mb-2">
                <x-admin.badge :text="$completeness['score'].'% - '.$completeness['label']" :color="$completeness['badge']" pill />
                @isset($performance)
                    <x-admin.badge :text="'Respons chat '.$performance['response_rate'].'%'" :color="$performance['response_badge']" pill />
                    <x-admin.badge :text="'Rating '.number_format($performance['rating'], 1, ',', '.')" :color="$performance['rating_badge']" pill />
                    <span class="text-secondary small">Pemenuhan {{ $performance['fulfillment_rate'] }}%</span>
                @endisset
            </div>
            <div class="progress progress-sm mb-2" role="progressbar" aria-label="Skor kelengkapan toko" aria-valuenow="{{ $completeness['score'] }}" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar bg-{{ $completeness['badge'] === 'danger' ? 'bg-danger' : ($completeness['badge'] === 'warning' ? 'bg-warning' : 'bg-success') }}" style="width: {{ max(2, $completeness['score']) }}%"></div>
            </div>
            <ul class="list-unstyled mb-0 small">
                @foreach ($completeness['items'] as $item)
                    <li class="d-flex align-items-center gap-2 py-1 {{ $loop->last ? '' : 'border-bottom' }}">
                        <x-admin.icon :name="$item['done'] ? 'check' : 'circle'" :size="14" class="{{ $item['done'] ? 'text-success' : 'text-secondary' }}" />
                        <span class="{{ $item['done'] ? '' : 'text-secondary' }}">{{ $item['label'] }}</span>
                    </li>
                @endforeach
            </ul>
        </x-admin.card>
    @endisset

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat
                label="Pendapatan kotor"
                :value="$kpi['gross_revenue']->toFloat()"
                icon="wallet"
                color="primary"
                :trend="$kpi['revenue_delta']['value']"
                trend-label="vs periode lalu"
                :href="route('vendor.finance.revenue')"
            />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat
                label="Pendapatan bersih"
                :value="$kpi['net_revenue']->toFloat()"
                icon="cash-coin"
                color="success"
                :hint="'Kurang pajak '.Currency::format($kpi['tax']->toFloat()).' dan komisi '.Currency::format($kpi['commission']->toFloat())"
                :href="route('vendor.finance.revenue')"
            />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat
                label="Pesanan"
                :value="$kpi['orders']"
                icon="shopping-cart"
                color="info"
                :trend="$kpi['order_delta']['value']"
                trend-label="vs periode lalu"
                :href="route('vendor.orders.index')"
            />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat
                label="Nilai pesanan rata-rata"
                :value="$kpi['aov']->toFloat()"
                icon="chart-line"
                color="warning"
                :href="route('vendor.finance.revenue')"
            />
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat
                label="Konversi"
                :value="$kpi['conversion'].' %'"
                icon="target"
                color="success"
                :hint="$__int($kpi['visitors']).' pengunjung unik'"
            />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat
                label="Menunggu kirim"
                :value="$pendingFulfillment['total']"
                icon="truck"
                color="warning"
                :href="route('vendor.fulfillment.index')"
            />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat
                label="Penarikan diproses"
                :value="$pendingPayouts['amount']->toFloat()"
                icon="wallet-2"
                color="primary"
                :hint="$__int($pendingPayouts['total']).' permintaan'"
                :href="route('vendor.finance.payouts')"
            />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat
                label="Rating produk"
                :value="number_format($reviews['average'], 2, ',', '.')"
                icon="star"
                color="danger"
                :hint="$__int($reviews['total']).' ulasan'"
                :href="route('vendor.reviews.index')"
            />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <x-admin.chart
                id="vendor-revenue-trend"
                title="Tren 30 hari"
                :labels="$trend['labels']"
                :data="[
                    ['label' => 'Pendapatan', 'data' => $trend['revenue']],
                    ['label' => 'Pesanan', 'data' => $trend['orders']],
                ]"
                :height="300"
                filled
            />

            <x-admin.card title="Pesanan terbaru" icon="list" class="mt-3" :padding="false" flush>
                <x-slot:actions>
                    <a href="{{ route('vendor.orders.index') }}" class="btn btn-sm btn-ghost-light">
                        Lihat semua
                    </a>
                </x-slot:actions>

                <x-admin.table dense>
                    <x-slot:table>
                        \App\Support\TableBuilder::make()
                            ->columns([
                                'order' => ['label' => 'Pesanan', 'width' => '26%'],
                                'customer' => ['label' => 'Pelanggan'],
                                'status' => ['label' => 'Status'],
                                'total' => ['label' => 'Total', 'align' => 'end'],
                                'date' => ['label' => 'Tanggal', 'align' => 'end'],
                            ])
                            ->rows(
                                $recentOrders->map(fn ($order) => [
                                    'order' => '<a href="'.route('vendor.orders.show', $order).'" class="fw-medium">'.e($order->order_number).'</a>',
                                    'customer' => e($order->customer?->name ?? 'Pelanggan'),
                                    'status' => $__orderStatus($order->order_status),
                                    'total' => Currency::format($order->total),
                                    'date' => '<span class="text-secondary small">'.e($order->created_at->format('d/m/Y H:i')).'</span>',
                                ])->all()
                            )
                            ->empty('Belum ada pesanan pada 30 hari terakhir.')
                    </x-slot:table>
                </x-admin.table>
            </x-admin.card>
        </div>

        <div class="col-12 col-xl-4">
            <x-admin.card title="Produk terlaris" icon="award" :padding="false">
                @forelse ($bestProducts as $product)
                    <div class="d-flex align-items-center gap-2 py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                        <span class="flex-shrink-0" style="width: 36px; height: 36px;">
                            @if ($product['thumbnail'])
                                <img src="{{ $product['thumbnail'] }}" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover;border-radius:6px;">
                            @else
                                <span class="d-flex align-items-center justify-content-center w-100 h-100 text-secondary bg-secondary-lt">
                                    <x-admin.icon name="package" :size="16" />
                                </span>
                            @endif
                        </span>
                        <span class="flex-grow-1 text-truncate">
                            <span class="d-block text-truncate fw-medium">{{ $product['name'] }}</span>
                            <span class="d-block text-secondary small">{{ $__int($product['units']) }} unit terjual</span>
                        </span>
                        <span class="text-end fw-medium">{{ Currency::format($product['revenue']->toFloat()) }}</span>
                    </div>
                @empty
                    <x-admin.empty-state icon="package" title="Belum ada penjualan" text="Produk terlaris muncul setelah ada pesanan lunas." compact />
                @endforelse
            </x-admin.card>

            <x-admin.card title="Stok menipis" icon="alert-triangle" class="mt-3" :padding="false">
                <x-slot:actions>
                    <a href="{{ route('vendor.products.low-stock') }}" class="btn btn-sm btn-ghost-light">Kelola</a>
                </x-slot:actions>

                @forelse ($lowStock as $product)
                    <a href="{{ $product['url'] }}" class="d-flex align-items-center justify-content-between gap-2 py-2 text-reset {{ $loop->last ? '' : 'border-bottom' }}">
                        <span class="text-truncate">
                            <span class="d-block text-truncate fw-medium">{{ $product['name'] }}</span>
                            <span class="d-block text-secondary small">SKU {{ $product['sku'] ?: '—' }}</span>
                        </span>
                        <x-admin.badge
                            :text="$product['stock'].' / '.$product['threshold']"
                            :color="$product['stock'] <= 0 ? 'danger' : 'warning'"
                            pill
                        />
                    </a>
                @empty
                    <x-admin.empty-state icon="check-circle" title="Stok aman" text="Tidak ada produk di bawah ambang stok." compact />
                @endforelse
            </x-admin.card>

            <x-admin.card title="Pelanggan teratas" icon="users" class="mt-3" :padding="false">
                @forelse ($topCustomers as $customer)
                    <a href="{{ route('vendor.customers.show', $customer['id']) }}" class="d-flex align-items-center justify-content-between gap-2 py-2 text-reset {{ $loop->last ? '' : 'border-bottom' }}">
                        <span class="d-flex align-items-center gap-2 min-w-0">
                            <x-admin.avatar :name="$customer['name']" size="sm" />
                            <span class="text-truncate fw-medium">{{ $customer['name'] }}</span>
                        </span>
                        <span class="text-end">
                            <span class="d-block fw-medium">{{ Currency::format($customer['spend']->toFloat()) }}</span>
                            <span class="d-block text-secondary small">{{ $customer['orders'] }} pesanan</span>
                        </span>
                    </a>
                @empty
                    <x-admin.empty-state icon="users" text="Belum ada data pelanggan." compact />
                @endforelse
            </x-admin.card>
        </div>
    </div>

    @isset($forecast)
        <div class="row g-3 mt-0">
            <div class="col-12 col-xl-8">
                <x-admin.card title="Prediksi stok habis" icon="chart-line" :padding="false">
                    <x-slot:actions>
                        <a href="{{ route('vendor.analytics.products') }}" class="btn btn-sm btn-ghost-light">Analitik produk</a>
                    </x-slot:actions>

                    <x-admin.table dense>
                        <x-slot:table>
                            \App\Support\TableBuilder::make()
                                ->columns([
                                    'product' => ['label' => 'Produk'],
                                    'left' => ['label' => 'Sisa hari', 'align' => 'end'],
                                    'restock' => ['label' => 'Saran restock', 'align' => 'end'],
                                    'state' => ['label' => 'Status', 'align' => 'end'],
                                ])
                                ->rows(
                                    collect($forecast)->take(8)->map(fn (array $row) => [
                                        'product' => '<span class="fw-medium d-block text-truncate">'.e($row['name']).'</span><span class="text-secondary small">Stok '.e($__int($row['stock'])).($row['stockout_at'] ? ' · habis ± '.\Carbon\Carbon::parse($row['stockout_at'])->format('d M Y') : '').'</span>',
                                        'left' => $row['days_left'] === null ? '—' : e(number_format($row['days_left'], 1, ',', '.')),
                                        'restock' => '<span class="fw-medium">'.e($__int($row['suggested_restock'])).' unit</span>',
                                        'state' => match ($row['state']) {
                                            'out_of_stock' => '<span class="badge bg-danger-lt text-danger">Habis</span>',
                                            'critical' => '<span class="badge bg-danger-lt text-danger">Kritis</span>',
                                            'low' => '<span class="badge bg-warning-lt text-warning">Menipis</span>',
                                            default => '<span class="badge bg-success-lt text-success">Aman</span>',
                                        },
                                    ])->all()
                                )
                                ->empty('Belum ada data forecast.')
                        </x-slot:table>
                    </x-admin.table>
                </x-admin.card>
            </div>

            <div class="col-12 col-xl-4">
                <x-admin.card title="Peringatan anomali" icon="alert-triangle">
                    @isset($anomalyAlerts)
                        @foreach ($anomalyAlerts as $alert)
                            <div class="d-flex align-items-start gap-2 py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                                <x-admin.badge
                                    :text="$alert['label']"
                                    :color="$alert['level'] === 'danger' ? 'danger' : ($alert['level'] === 'warning' ? 'warning' : 'success')"
                                    pill
                                />
                                <span class="text-secondary small">{{ $alert['detail'] }}</span>
                            </div>
                        @endforeach
                    @endisset
                </x-admin.card>
            </div>
        </div>
    @endisset

    <div class="row g-3 mt-0">
        <div class="col-12 col-lg-4">
            <x-admin.card title="Komposisi status pesanan" icon="activity">
                <x-admin.activity-feed
                    :items="collect($orderStatus)->map(fn ($row) => [
                        'actor' => $row['label'],
                        'action' => $__int($row['total']).' pesanan',
                        'at' => $row['badge'],
                        'icon' => 'list',
                    ])->all()"
                    empty="Belum ada pesanan pada periode ini."
                />
            </x-admin.card>
        </div>

        <div class="col-12 col-lg-4">
            <x-admin.card title="Performa promo" icon="megaphone">
                @forelse ($campaigns as $campaign)
                    <div class="d-flex align-items-center justify-content-between gap-2 py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                        <span class="text-truncate">
                            <span class="d-block text-truncate fw-medium">{{ $campaign['name'] }}</span>
                            <span class="d-block text-secondary small">
                                {{ $campaign['code'] ?: str_replace('_', ' ', $campaign['type']) }}
                                @if ($campaign['ends_at'])
                                    · berakhir {{ $__date($campaign['ends_at'], 'd M Y') }}
                                @endif
                            </span>
                        </span>
                        <span class="text-end">
                            <span class="d-block fw-medium">{{ Currency::format($campaign['revenue']->toFloat()) }}</span>
                            <span class="d-block text-secondary small">{{ $__int($campaign['orders']) }} pesanan</span>
                        </span>
                    </div>
                @empty
                    <x-admin.empty-state icon="megaphone" text="Belum ada promo aktif." compact />
                @endforelse
            </x-admin.card>
        </div>

        <div class="col-12 col-lg-4">
            <x-admin.card title="Ulasan terbaru" icon="star">
                @forelse ($reviews['latest'] as $review)
                    <div class="py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <span class="fw-medium text-truncate">{{ $review->customer?->name ?? 'Pelanggan' }}</span>
                            <span class="text-warning small">
                                @for ($i = 0; $i < (int) $review->rating; $i++)
                                    <x-admin.icon name="star-filled" :size="12" />
                                @endfor
                            </span>
                        </div>
                        <p class="text-secondary small mb-1">{{ \Illuminate\Support\Str::limit($review->comment, 120) }}</p>
                        <span class="text-secondary small">{{ $review->product?->name }}</span>
                    </div>
                @empty
                    <x-admin.empty-state icon="star" text="Belum ada ulasan." compact />
                @endforelse
            </x-admin.card>
        </div>
    </div>

    <div class="row g-3 mt-0">
        <div class="col-12 col-xl-6">
            <x-admin.card title="Menunggu dikirim" icon="truck" :padding="false">
                <x-slot:actions>
                    <a href="{{ route('vendor.fulfillment.index') }}" class="btn btn-sm btn-ghost-light">Buka antrean</a>
                </x-slot:actions>

                <x-admin.table dense>
                    <x-slot:table>
                        \App\Support\TableBuilder::make()
                            ->columns([
                                'order' => ['label' => 'Pesanan'],
                                'customer' => ['label' => 'Pelanggan'],
                                'total' => ['label' => 'Total', 'align' => 'end'],
                                'action' => ['label' => '', 'align' => 'end', 'width' => '110px'],
                            ])
                            ->rows(
                                $pendingFulfillment['items']->map(fn ($order) => [
                                    'order' => '<a href="'.route('vendor.orders.show', $order).'" class="fw-medium">'.e($order->order_number).'</a>',
                                    'customer' => e($order->customer?->name ?? 'Pelanggan'),
                                    'total' => Currency::format($order->total),
                                    'action' => in_array($order->order_status, \App\Services\Vendor\VendorFulfillmentService::shippableStatuses(), true)
                                        ? '<a href="'.route('vendor.orders.show', $order).'" class="btn btn-sm btn-primary">Kirim</a>'
                                        : '<span class="text-secondary small">Tahan</span>',
                                ])->all()
                            )
                            ->empty('Semua pesanan sudah dikirim.')
                    </x-slot:table>
                </x-admin.table>
            </x-admin.card>
        </div>

        <div class="col-12 col-xl-6">
            <x-admin.card title="Penarikan dana berjalan" icon="wallet-2" :padding="false">
                <x-slot:actions>
                    <a href="{{ route('vendor.finance.payouts') }}" class="btn btn-sm btn-ghost-light">Riwayat</a>
                </x-slot:actions>

                <x-admin.table dense>
                    <x-slot:table>
                        \App\Support\TableBuilder::make()
                            ->columns([
                                'amount' => ['label' => 'Nominal'],
                                'bank' => ['label' => 'Rekening'],
                                'status' => ['label' => 'Status'],
                                'date' => ['label' => 'Diajukan', 'align' => 'end'],
                            ])
                            ->rows(
                                $pendingPayouts['items']->map(fn ($request) => [
                                    'amount' => '<span class="fw-medium">'.Currency::format($request->amount).'</span>',
                                    'bank' => e($__maskAccount($request->bank_account_number)),
                                    'status' => $__status($request->status),
                                    'date' => '<span class="text-secondary small">'.e($request->created_at->format('d/m/Y')).'</span>',
                                ])->all()
                            )
                            ->empty('Tidak ada permintaan penarikan yang menunggu.')
                    </x-slot:table>
                </x-admin.table>
            </x-admin.card>
        </div>
    </div>
@endsection
