@extends('layouts.admin')

@section('title', 'Delivery '.$endpoint['name'])

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Developers', ['label' => 'Webhooks', 'href' => route('admin.webhooks.index')], ['label' => 'Delivery']]" />
@endsection

@section('content')
    <x-admin.page-header :title="$endpoint['name']" :subtitle="$endpoint['host']">
        <x-slot:actions>
            <a href="{{ route('admin.webhooks.index') }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Menunggu" :value="number_format($summary['pending'], 0, ',', '.')" icon="clock" color="warning" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Terkirim" :value="number_format($summary['delivered'], 0, ',', '.')" icon="check" color="success" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Gagal" :value="number_format($summary['failed'], 0, ',', '.')" icon="x-circle" color="danger" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Kehabisan Percobaan" :value="number_format($summary['exhausted'], 0, ',', '.')" icon="alert-triangle" color="secondary" />
        </div>
    </div>

    <x-admin.card class="mb-3" title="Endpoint" icon="webhook">
        <dl class="row small mb-0">
            <dt class="col-4 text-secondary">URL</dt>
            <dd class="col-8 text-end text-break"><code>{{ $endpoint['url'] }}</code></dd>
            <dt class="col-4 text-secondary">Secret</dt>
            <dd class="col-8 text-end"><code>{{ $endpoint['secret_masked'] }}</code></dd>
            <dt class="col-4 text-secondary">Event</dt>
            <dd class="col-8 text-end">
                @foreach ($endpoint['events'] as $event)
                    <x-admin.badge :text="$event" color="info" pill />
                @endforeach
            </dd>
        </dl>
    </x-admin.card>

    <x-admin.card class="mb-3" title="Filter" icon="filter">
        <x-admin.filters
            :action="route('admin.webhooks.deliveries', $endpoint['id'])"
            :filters="[['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => [
                '' => 'Semua status',
                'pending' => 'Menunggu',
                'delivered' => 'Terkirim',
                'failed' => 'Gagal',
                'exhausted' => 'Kehabisan percobaan',
            ]]]"
        />
    </x-admin.card>

    <x-admin.card title="Riwayat Delivery" icon="activity" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Waktu</th>
                        <th scope="col">Event</th>
                        <th scope="col" class="text-center">Percobaan</th>
                        <th scope="col" class="text-center">HTTP</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col">Respons</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="text-nowrap">{{ $row['created_at'] }}</td>
                            <td>
                                <code class="small">{{ $row['event'] }}</code>
                                <small class="d-block text-secondary">{{ $row['event_id'] }}</small>
                            </td>
                            <td class="text-center">{{ $row['attempt'] }} / {{ $row['max_attempts'] }}</td>
                            <td class="text-center">
                                @if ($row['response_status'] === null)
                                    <span class="text-secondary">-</span>
                                @else
                                    <x-admin.badge
                                        :text="(string) $row['response_status']"
                                        :color="$row['response_status'] < 300 ? 'success' : 'danger'"
                                        pill
                                    />
                                @endif
                            </td>
                            <td class="text-center">
                                <x-admin.badge
                                    :text="$row['status']"
                                    :color="match($row['status']) { 'delivered' => 'success', 'failed' => 'danger', 'exhausted' => 'secondary', default => 'warning' }"
                                    pill
                                />
                            </td>
                            <td class="small text-secondary">{{ $row['response'] !== '' ? \Illuminate\Support\Str::limit($row['response'], 60) : '-' }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.webhooks.deliveries.replay', [$endpoint['id'], $row['id']]) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Kirim Ulang</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-admin.empty-state icon="activity" title="Belum ada delivery" text="Endpoint ini belum menerima notifikasi apa pun." />
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
