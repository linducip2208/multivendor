@extends('layouts.admin')

@section('title', 'Referral')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Pemasaran', ['label' => 'Referral']]" />
@endsection

@section('content')
    <x-admin.page-header title="Referral" subtitle="Kode referral pelanggan dan konversi undangan yang tercatat." />

    <div class="row g-3 mb-3">
        @foreach ($kpis as $kpi)
            <div class="col-6 col-xl-3">
                <x-admin.stat :label="$kpi['label']" :value="$kpi['value']" :icon="$kpi['icon']" :color="$kpi['color']" :hint="$kpi['hint']" />
            </div>
        @endforeach
    </div>

    <x-admin.card class="mb-3" title="Filter" icon="filter">
        <x-admin.filters
            :action="route('admin.referrals.index')"
            :filters="[['name' => 'search', 'label' => 'Cari', 'placeholder' => 'Nama atau email pelanggan']]"
        />
    </x-admin.card>

    <x-admin.card title="Kode Referral Pelanggan" icon="link" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Pelanggan</th>
                        <th scope="col">Kode</th>
                        <th scope="col" class="text-end">Mengundang</th>
                        <th scope="col" class="text-end">Berhasil Beli</th>
                        <th scope="col" class="text-end">Tingkat Konversi</th>
                        <th scope="col">Bergabung</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                <a href="{{ route('admin.customers.360', $row['id']) }}">{{ $row['name'] }}</a>
                                <small class="d-block text-secondary">{{ $row['email'] }}</small>
                            </td>
                            <td><code>{{ $row['code'] }}</code></td>
                            <td class="text-end">{{ number_format($row['invited'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($row['converted'], 0, ',', '.') }}</td>
                            <td class="text-end">
                                <x-admin.badge
                                    :text="number_format($row['rate'], 1, ',', '.').'%'"
                                    :color="$row['rate'] >= 50 ? 'success' : ($row['rate'] > 0 ? 'warning' : 'secondary')"
                                    pill
                                />
                            </td>
                            <td class="text-nowrap">{{ $row['joined'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-admin.empty-state icon="link" title="Belum ada kode referral" text="Kode referral dibuat otomatis saat pelanggan mendaftar." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <div class="mt-3">
        <x-admin.pagination :paginator="\App\Support\AdminPaginator::fromArray($pagination, $pagination['total'], $pagination['per_page'], $pagination['current_page'])" size="sm" />
    </div>

    @isset($rows)
        @php
            $topReferrers = collect($rows)->sortByDesc(fn ($r) => (float) ($r['converted'] ?? 0))->take(5)->values();
        @endphp
        @if ($topReferrers->isNotEmpty())
            <x-admin.card title="Papan Peringkat Referral" icon="trophy" class="mt-3" flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Pelanggan</th>
                                <th scope="col" class="text-end">Mengundang</th>
                                <th scope="col" class="text-end">Berhasil Beli</th>
                                <th scope="col" class="text-end">Konversi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($topReferrers as $i => $top)
                                <tr>
                                    <td class="fw-semibold">{{ $i + 1 }}</td>
                                    <td>{{ $top['name'] }} <small class="d-block text-secondary"><code>{{ $top['code'] }}</code></small></td>
                                    <td class="text-end">{{ number_format($top['invited'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($top['converted'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($top['rate'] ?? 0, 1, ',', '.') }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer text-secondary small">
                    Tautan berbagi pelanggan memakai parameter <code>?ref=KODE</code>. Komisi afiliasi otomatis
                    dihitung dari order lalu dibayarkan via dompet (lihat menu Afiliasi).
                </div>
            </x-admin.card>
        @endif
    @endisset
@endsection
