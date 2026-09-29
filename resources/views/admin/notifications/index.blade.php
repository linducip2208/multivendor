@extends('layouts.admin')

@section('title', 'Pusat Notifikasi')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Marketing', ['label' => 'Notifikasi']]" />
@endsection

@section('content')
    <x-admin.page-header title="Pusat Notifikasi" subtitle="Notifikasi yang dikirim ke pelanggan, beserta status bacanya.">
        <x-slot:actions>
            <x-admin.badge :text="number_format($devices, 0, ',', '.').' perangkat terdaftar'" color="secondary" pill />
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.card class="mb-3" title="Filter" icon="filter">
        <x-admin.filters
            :action="route('admin.notifications')"
            :filters="[
                ['name' => 'category', 'label' => 'Kategori', 'type' => 'select', 'options' => array_merge(
                    ['' => 'Semua kategori'],
                    array_filter(array_combine(array_keys($counts), $counts), fn ($value, $key): bool => ! in_array($key, ['all', 'unread', 'read'], true), ARRAY_FILTER_USE_BOTH),
                )],
                ['name' => 'read', 'label' => 'Status Baca', 'type' => 'select', 'options' => ['' => 'Semua', 'unread' => 'Belum dibaca', 'read' => 'Sudah dibaca']],
            ]"
        />
    </x-admin.card>

    <x-admin.card title="Kotak Notifikasi" icon="bell" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Judul</th>
                        <th scope="col">Isi</th>
                        <th scope="col" class="text-center">Kategori</th>
                        <th scope="col" class="text-center">Kanal</th>
                        <th scope="col" class="text-center">Dibaca</th>
                        <th scope="col">Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                @if ($row['action_url'] !== '')
                                    <a href="{{ $row['action_url'] }}">{{ $row['title'] }}</a>
                                @else
                                    {{ $row['title'] }}
                                @endif
                                <small class="d-block text-secondary"><code>{{ $row['uuid'] }}</code></small>
                            </td>
                            <td>{{ \Illuminate\Support\Str::limit($row['body'], 90) }}</td>
                            <td class="text-center">
                                <x-admin.badge :text="\Illuminate\Support\Str::headline($row['category'])" color="info" pill />
                            </td>
                            <td class="text-center">{{ \Illuminate\Support\Str::headline($row['channel']) }}</td>
                            <td class="text-center">
                                <x-admin.badge :text="$row['read'] ? 'Dibaca' : 'Belum'" :color="$row['read'] ? 'success' : 'warning'" pill />
                            </td>
                            <td class="text-nowrap">{{ $row['at'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-admin.empty-state icon="bell" title="Belum ada notifikasi" text="Notifikasi pelanggan akan muncul di sini setelah dikirim." />
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
