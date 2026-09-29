@extends('layouts.admin')

@section('title', 'Kampanye')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Pemasaran', ['label' => 'Kampanye']]" />
@endsection

@section('content')
    <x-admin.page-header title="Kampanye" subtitle="Promo, flash sale, bundling, dan program referral.">
        <x-slot:actions>
            <a href="{{ route('admin.campaigns.create') }}" class="btn btn-primary btn-sm">
                <x-admin.icon name="plus" :size="14" /> Kampanye Baru
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    @isset($retention)
        <div class="row g-3 mb-3">
            <div class="col-6 col-xl-3"><x-admin.stat label="Keranjang Menunggu" :value="$retention['abandoned_pending'] ?? 0" icon="shopping-cart" color="warning" :hint="($retention['abandoned_jatuh_tempo'] ?? 0).' jatuh tempo diingatkan'" /></div>
            <div class="col-6 col-xl-3"><x-admin.stat label="Nilai Tertinggal" :value="$retention['abandoned_nilai'] ?? 0" money icon="cash" color="danger" /></div>
            <div class="col-6 col-xl-3"><x-admin.stat label="Voucher Ultah" :value="$retention['voucher_ultah'] ?? 0" icon="gift" color="info" hint="Kode ULTAH-* terbit" /></div>
            <div class="col-6 col-xl-3"><x-admin.stat label="Pengingat Flash" :value="$retention['langganan_flash'] ?? 0" icon="bell" color="primary" :hint="($retention['banner_tertarget'] ?? 0).' banner tertarget segmen'" /></div>
        </div>
    @endisset

    <x-admin.card class="mb-3" title="Filter" icon="filter">
        <x-admin.filters
            :action="route('admin.campaigns.index')"
            :filters="[
                ['name' => 'search', 'label' => 'Cari', 'placeholder' => 'Nama kampanye'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => array_merge(['' => 'Semua status'], array_combine(array_keys($counts), $counts))],
                ['name' => 'type', 'label' => 'Jenis', 'type' => 'select', 'options' => ['' => 'Semua jenis'] + \App\Services\Marketing\CampaignService::TYPES],
            ]"
        />
    </x-admin.card>

    <x-admin.card title="Daftar Kampanye" icon="target" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Nama</th>
                        <th scope="col">Jenis</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col" class="text-center">Aturan</th>
                        <th scope="col" class="text-end">Anggaran</th>
                        <th scope="col" class="text-end">Omzet</th>
                        <th scope="col" class="text-end">ROAS</th>
                        <th scope="col" class="text-end">Kuota</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                <a href="{{ $row['url'] }}">{{ $row['name'] }}</a>
                                <small class="d-block text-secondary">
                                    {{ $row['starts_at'] !== '' ? $row['starts_at'] : 'Tanpa mulai' }}
                                    &ndash;
                                    {{ $row['ends_at'] !== '' ? $row['ends_at'] : 'Tanpa akhir' }}
                                </small>
                            </td>
                            <td>{{ $row['type_label'] }}</td>
                            <td class="text-center">
                                <x-admin.badge
                                    :text="$row['live'] ? 'Berjalan' : $row['status_label']"
                                    :color="match($row['status']) { 'active' => 'success', 'paused' => 'warning', 'draft' => 'secondary', 'ended' => 'info', default => 'info' }"
                                    pill
                                />
                            </td>
                            <td class="text-end">{{ $row['rule_count'] }}</td>
                            <td class="text-end">{{ $row['budget_formatted'] }}</td>
                            <td class="text-end fw-semibold">{{ $row['revenue_formatted'] }}</td>
                            <td class="text-end">{{ number_format($row['roas'], 1, ',', '.') }}%</td>
                            <td class="text-end">
                                {{ number_format($row['used_count'], 0, ',', '.') }} / {{ $row['usage_limit'] === null ? '&infin;' : number_format($row['usage_limit'], 0, ',', '.') }}
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm" role="group" aria-label="Aksi kampanye {{ $row['name'] }}">
                                    <a href="{{ route('admin.campaigns.show', $row['id']) }}" class="btn btn-outline-secondary">Detail</a>
                                    <a href="{{ route('admin.campaigns.edit', $row['id']) }}" class="btn btn-outline-primary">Ubah</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <x-admin.empty-state
                                    icon="target"
                                    title="Belum ada kampanye"
                                    text="Buat kampanye pertama untuk mulai mengukur efektivitas promosi."
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
        <x-admin.pagination :paginator="\App\Support\AdminPaginator::fromArray($pagination, $pagination['total'], $pagination['per_page'], $pagination['current_page'])" size="sm" />
    </div>
@endsection
