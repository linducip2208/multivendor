@extends('layouts.admin')

@section('title', 'Analitik Marketing')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Analitik', ['label' => 'Marketing']]" />
@endsection

@section('content')
    <x-admin.page-header title="Efektivitas Pemasaran" subtitle="Kampanye, kupon, keranjang tertinggal, dan afiliasi.">
        <x-slot:actions>
            <a href="{{ route('admin.campaigns.index') }}" class="btn btn-primary btn-sm">
                <x-admin.icon name="target" :size="14" /> Kelola Kampanye
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card mb-3">
        <div class="card-body">
            @include('admin.partials.date-range', ['range' => $range])
        </div>
    </div>

    <x-admin.tabs :tabs="$tabs" class="mb-3" />

    <div class="row g-3 mb-3">
        @foreach ($report['kpis'] as $kpi)
            <div class="col-6 col-xl-4">
                <x-admin.stat :label="$kpi['label']" :value="$kpi['value']" :money="$kpi['money'] ?? false" :hint="$kpi['hint'] ?? null" />
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <x-admin.card title="Keranjang Tertinggal" icon="shopping-cart">
                <x-admin.chart
                    type="doughnut"
                    :labels="['Sudah Pulih', 'Belum Pulih']"
                    :data="[[
                        'label' => 'Keranjang',
                        'data' => [$report['carts']['recovered'], max(0, $report['carts']['total'] - $report['carts']['recovered'])],
                    ]]"
                    :height="240"
                />
                <dl class="row small mb-0 mt-3">
                    <dt class="col-7 text-secondary">Total keranjang</dt>
                    <dd class="col-5 text-end">{{ number_format($report['carts']['total'], 0, ',', '.') }}</dd>
                    <dt class="col-7 text-secondary">Nilai tertinggal</dt>
                    <dd class="col-5 text-end fw-semibold">{{ \App\Support\Currency::format($report['carts']['value']) }}</dd>
                    <dt class="col-7 text-secondary">Sudah diaperingatkan</dt>
                    <dd class="col-5 text-end">{{ number_format($report['carts']['reminded'], 0, ',', '.') }}</dd>
                </dl>
            </x-admin.card>
        </div>
        <div class="col-lg-4">
            <x-admin.card title="Afiliasi" icon="link">
                <dl class="row small mb-0">
                    <dt class="col-7 text-secondary">Klik</dt>
                    <dd class="col-5 text-end">{{ number_format($report['affiliates']['clicks'], 0, ',', '.') }}</dd>
                    <dt class="col-7 text-secondary">Berubah menjadi pesanan</dt>
                    <dd class="col-5 text-end">{{ number_format($report['affiliates']['converted'], 0, ',', '.') }}</dd>
                    <dt class="col-7 text-secondary">Tingkat konversi</dt>
                    <dd class="col-5 text-end">{{ number_format($report['affiliates']['conversion_rate'], 1, ',', '.') }}%</dd>
                    <dt class="col-7 text-secondary">Total afiliasi</dt>
                    <dd class="col-5 text-end">{{ number_format($report['affiliates']['affiliates'], 0, ',', '.') }}</dd>
                    <dt class="col-7 text-secondary">Omzet influenci</dt>
                    <dd class="col-5 text-end fw-semibold">{{ \App\Support\Currency::format($report['affiliates']['revenue']) }}</dd>
                    <dt class="col-7 text-secondary">Komisi dibayar</dt>
                    <dd class="col-5 text-end">{{ \App\Support\Currency::format($report['affiliates']['commission']) }}</dd>
                </dl>
                <a href="{{ route('admin.affiliates.index') }}" class="btn btn-outline-secondary btn-sm w-100 mt-3">Kelola afiliasi</a>
            </x-admin.card>
        </div>
        <div class="col-lg-4">
            <x-admin.card title="Kupon" icon="ticket">
                <dl class="row small mb-0">
                    <dt class="col-7 text-secondary">Penggunaan kupon</dt>
                    <dd class="col-5 text-end">{{ number_format($report['coupons']['redemptions'], 0, ',', '.') }}</dd>
                    <dt class="col-7 text-secondary">Nilai diskon diberikan</dt>
                    <dd class="col-5 text-end fw-semibold">{{ \App\Support\Currency::format($report['coupons']['discount']) }}</dd>
                    <dt class="col-7 text-secondary">Tasio penggunaan</dt>
                    <dd class="col-5 text-end">{{ number_format($report['coupons']['redemption_rate'], 1, ',', '.') }}%</dd>
                </dl>
            </x-admin.card>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <x-admin.card title="Kupon Terlaris" subtitle="Berdasarkan jumlah pemakaian." icon="ticket" flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Kode</th>
                                <th scope="col">Judul</th>
                                <th scope="col" class="text-end">Dipakai</th>
                                <th scope="col" class="text-end">Diskon</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($report['coupons']['top'] as $row)
                                <tr>
                                    <td><code>{{ $row['code'] }}</code></td>
                                    <td>{{ $row['title'] }}</td>
                                    <td class="text-end">{{ number_format($row['uses'], 0, ',', '.') }}</td>
                                    <td class="text-end">{{ \App\Support\Currency::format($row['discount']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4"><x-admin.empty-state compact icon="ticket" title="Belum ada kupon terpakai" text="Tidak ada penggunaan kupon pada periode ini." /></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>
        <div class="col-lg-6">
            <x-admin.card title="Flash Sale Terakhir" icon="zap" flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Judul</th>
                                <th scope="col" class="text-center">Diskon</th>
                                <th scope="col">Mulai</th>
                                <th scope="col">Selesai</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($report['flash_deals'] as $row)
                                <tr>
                                    <td>{{ $row['title'] }}</td>
                                    <td class="text-center"><x-admin.badge :text="'-'.$row['discount'].'%'" color="danger" pill /></td>
                                    <td>{{ $row['starts_at'] }}</td>
                                    <td>{{ $row['ends_at'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4"><x-admin.empty-state compact icon="zap" title="Belum ada flash sale" text="Belum ada flash sale yang dibuat." /></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>
    </div>

    <x-admin.card title="Kampanye" icon="target" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Kampanye</th>
                        <th scope="col" class="text-center">Jenis</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col" class="text-end">Anggaran</th>
                        <th scope="col" class="text-end">Omzet</th>
                        <th scope="col" class="text-end">Klik</th>
                        <th scope="col" class="text-end">Konversi</th>
                        <th scope="col" class="text-end">ROAS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($campaigns['rows'] as $row)
                        <tr>
                            <td><a href="{{ route('admin.campaigns.show', $row['id']) }}">{{ $row['name'] }}</a></td>
                            <td class="text-center">{{ \Illuminate\Support\Str::headline($row['type']) }}</td>
                            <td class="text-center">
                                <x-admin.badge :text="\Illuminate\Support\Str::headline($row['status'])" :color="match($row['status']) { 'active' => 'success', 'paused' => 'warning', 'draft' => 'secondary', default => 'info' }" pill />
                            </td>
                            <td class="text-end">{{ \App\Support\Currency::format($row['budget']) }}</td>
                            <td class="text-end fw-semibold">{{ \App\Support\Currency::format($row['revenue']) }}</td>
                            <td class="text-end">{{ number_format($row['clicks'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($row['conversions'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($row['roas'], 1, ',', '.') }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-admin.empty-state
                                    icon="target"
                                    title="Belum ada kampanye"
                                    text="Mulai dengan membuat kampanye pertama Anda."
                                    action-label="Buat Kampanye"
                                    action-url="{{ route('admin.campaigns.create') }}"
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <div class="mt-3">
        <x-admin.pagination :paginator="\App\Support\AdminPaginator::fromArray($campaigns, $campaigns['total'], $campaigns['per_page'], $campaigns['current_page'])" size="sm" />
    </div>
@endsection
