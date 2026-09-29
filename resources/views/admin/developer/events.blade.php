@extends('layouts.admin')

@section('title', 'Katalog Peristiwa')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Developers', ['label' => 'Peristiwa']]" />
@endsection

@section('content')
    <x-admin.page-header title="Katalog Peristiwa" subtitle="Daftar peristiwa yang dapat disubscribe lewat webhook." />

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Total Peristiwa" :value="number_format($total_events, 0, ',', '.')" icon="zap" color="primary" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Peristiwa Berlangganan" :value="number_format($subscribed_events, 0, ',', '.')" icon="webhook" color="success" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Kelompok Peristiwa" :value="count($groups)" icon="layers" color="info" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Belum Dipakai" :value="number_format(max(0, $total_events - $subscribed_events), 0, ',', '.')" icon="inbox" color="secondary" />
        </div>
    </div>

    @foreach ($groups as $group => $events)
        <x-admin.card class="mb-3" :title="$group" icon="layers" flush>
            <div class="table-responsive">
                <table class="table admin-table mb-0 table-hover">
                    <thead>
                        <tr>
                            <th scope="col">Peristiwa</th>
                            <th scope="col">Deskripsi</th>
                            <th scope="col">Isi Payload</th>
                            <th scope="col">Subscriber</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($events as $event)
                            <tr>
                                <td><code class="small">{{ $event['event'] }}</code></td>
                                <td>{{ $event['description'] }}</td>
                                <td>
                                    @foreach ($event['payload'] as $field)
                                        <x-admin.badge :text="$field" color="secondary" pill />
                                    @endforeach
                                </td>
                                <td>
                                    @forelse ($event['subscribers'] as $subscriber)
                                        <x-admin.badge
                                            :text="$subscriber['name']"
                                            :color="$subscriber['active'] ? 'success' : 'secondary'"
                                            pill
                                        />
                                    @empty
                                        <span class="text-secondary small">Belum ada</span>
                                    @endforelse
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-admin.card>
    @endforeach
@endsection
