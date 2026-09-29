@extends('layouts.admin')

@section('title', 'Jejak Audit')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Sistem', ['label' => 'Jejak Audit']]" />
@endsection

@section('content')
    <x-admin.page-header title="Jejak Audit" subtitle="Setiap tindakan administratif yang tercatat beserta nilai lama dan baru." />

    <x-admin.alert type="info" :dismissible="false" title="Sumber kebenaran" icon="history">
        Jejak ini ditulis oleh <code>App\Services\AuditLogger</code> pada setiap aksi yang mengubah data. Nilai rahasia tidak pernah ditulis ke sini.
    </x-admin.alert>

    <x-admin.card class="mb-3" title="Filter" icon="filter">
        <x-admin.filters
            :action="request()->url()"
            :filters="[
                ['name' => 'action', 'label' => 'Aksi', 'placeholder' => 'Contoh: order.return'],
                ['name' => 'entity', 'label' => 'Entitas', 'placeholder' => 'Contoh: Order'],
            ]"
            :auto-submit="false"
        />
    </x-admin.card>

    <x-admin.card title="Catatan" icon="activity" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Waktu</th>
                        <th scope="col">Pelaku</th>
                        <th scope="col">Aksi</th>
                        <th scope="col">Entitas</th>
                        <th scope="col">Perubahan</th>
                        <th scope="col">IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="text-nowrap">{{ $row['at'] }}</td>
                            <td>
                                {{ $row['actor_name'] }}
                                <small class="d-block text-secondary">{{ $row['actor_role'] }}</small>
                            </td>
                            <td><code class="small">{{ $row['action'] }}</code></td>
                            <td>
                                @if ($row['entity_type'] !== '')
                                    <code class="small">{{ $row['entity_type'] }}#{{ $row['entity_id'] }}</code>
                                @else
                                    <span class="text-secondary small">-</span>
                                @endif
                            </td>
                            <td class="small text-secondary">{{ $row['changes'] }}</td>
                            <td><code class="small">{{ $row['ip_address'] !== '' ? $row['ip_address'] : '-' }}</code></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-admin.empty-state icon="history" title="Belum ada jejak audit" text="Jejak akan muncul setelah administrator atau sistem melakukan aksi yang tercatat." />
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
