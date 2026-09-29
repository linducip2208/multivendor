@extends('layouts.admin')

@section('title', 'Kotak Masuk Percakapan')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['CRM', ['label' => 'Percakapan']]" />
@endsection

@section('content')
    <x-admin.page-header title="Percakapan" subtitle="Balasan pelanggan dan catatan internal tim." />

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Total" :value="$counts['all']" icon="message-circle" color="primary" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Belum Terbaca" :value="$counts['unread']" icon="mail" color="warning" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Terbuka" :value="$counts['open']" icon="inbox" color="info" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Ditutup" :value="$counts['closed']" icon="check" color="success" />
        </div>
    </div>

    <x-admin.card class="mb-3" title="Filter" icon="filter">
        <x-admin.filters
            :action="route('admin.conversations.index')"
            :filters="[
                ['name' => 'search', 'label' => 'Cari', 'placeholder' => 'Subjek atau nomor percakapan'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => array_merge(['' => 'Semua status'], array_combine(array_keys($counts), $counts))],
            ]"
        />
    </x-admin.card>

    <x-admin.card title="Antrean" icon="inbox" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Subjek</th>
                        <th scope="col">Toko</th>
                        <th scope="col">Peserta</th>
                        <th scope="col" class="text-center">Pesan</th>
                        <th scope="col" class="text-center">Belum Dibaca</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col">Terakhir</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr @class(['table-active' => $row['unread'] > 0])>
                            <td>
                                <a href="{{ $row['url'] }}">{{ $row['subject'] }}</a>
                                <small class="d-block text-secondary">{{ $row['uuid'] }}</small>
                            </td>
                            <td>{{ $row['shop'] }}</td>
                            <td>
                                @foreach ($row['participants'] as $participant)
                                    <x-admin.badge :text="$participant" color="secondary" pill />
                                @endforeach
                            </td>
                            <td class="text-end">{{ $row['messages'] }}</td>
                            <td class="text-end">
                                @if ($row['unread'] > 0)
                                    <x-admin.badge :text="number_format($row['unread'], 0, ',', '.')" color="warning" pill />
                                @else
                                    <span class="text-secondary">0</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <x-admin.badge
                                    :text="$row['status_label']"
                                    :color="match($row['status']) { 'open' => 'info', 'pending' => 'warning', 'closed' => 'success', 'spam' => 'danger', default => 'secondary' }"
                                    pill
                                />
                            </td>
                            <td class="text-nowrap">{{ $row['last_message_at'] }}</td>
                            <td class="text-end">
                                <a href="{{ $row['url'] }}" class="btn btn-sm btn-outline-primary">Buka</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-admin.empty-state icon="message-circle" title="Kotak masuk kosong" text="Belum ada percakapan yang masuk." />
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
