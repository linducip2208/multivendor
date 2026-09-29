@extends('layouts.admin')

@section('title', 'Detail Tenant '.$tenant['name'])

@section('breadcrumb')
    <x-admin.breadcrumb :items="['SaaS', ['label' => 'Tenant', 'href' => route('admin.tenants.index')], ['label' => $tenant['name']]]" />
@endsection

@section('content')
    <x-admin.page-header :title="$tenant['name']" :subtitle="$tenant['domain'].' · '.$tenant['currency_code'].' · komisi '.$tenant['commission_rate'].'%'">
        <x-slot:actions>
            <a href="{{ route('admin.saas.subscriptions') }}" class="btn btn-outline-secondary btn-sm">Langganan</a>
            <a href="{{ route('admin.tenants.index') }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Status" :value="$tenant['is_active'] ? 'Aktif' : 'Nonaktif'" icon="check" :color="$tenant['is_active'] ? 'success' : 'secondary'" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Akhir Masa Uji" :value="$tenant['trial_ends_at'] !== '' ? $tenant['trial_ends_at'] : 'Tidak ada'" icon="clock" color="info" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Domain" :value="count($tenant['extra_domains']) + 1" icon="globe" color="secondary" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Fitur Aktif" :value="count($tenant['features'])" icon="toggle-left" color="warning" />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <x-admin.card title="Profil Tenant" icon="user" class="mb-3">
                <form method="POST" action="{{ route('admin.tenants.update', $tenant['id']) }}">
                    @csrf
                    @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-8">
                            <x-admin.form-field name="name" label="Nama" :value="$tenant['name']" required :maxlength="120" />
                        </div>
                        <div class="col-md-4">
                            <x-admin.form-field name="domain" label="Domain" :value="$tenant['domain']" required :maxlength="160" />
                        </div>
                        <div class="col-md-6">
                            <x-admin.form-field name="contact_email" label="Email Kontak" type="email" :value="$tenant['contact_email']" :maxlength="160" />
                        </div>
                        <div class="col-md-6">
                            <x-admin.form-field name="contact_phone" label="Telepon Kontak" :value="$tenant['contact_phone']" :maxlength="30" />
                        </div>
                        <div class="col-md-4">
                            <x-admin.form-field name="currency_code" label="Mata Uang" :value="$tenant['currency_code']" :maxlength="3" />
                        </div>
                        <div class="col-md-4">
                            <x-admin.form-field name="locale" label="Locale" :value="$tenant['locale']" :maxlength="5" />
                        </div>
                        <div class="col-md-4">
                            <x-admin.form-field name="timezone" label="Zona Waktu" :value="$tenant['timezone']" />
                        </div>
                        <div class="col-md-6">
                            <x-admin.form-field name="default_commission_rate" label="Komisi Default (%)" type="number" :value="$tenant['commission_rate']" :min="0" :max="100" :step="0.01" />
                        </div>
                        <div class="col-md-6">
                            <x-admin.form-field name="trial_ends_at" label="Akhir Masa Uji" type="date" :value="$tenant['trial_ends_at'] !== '' ? $tenant['trial_ends_at'] : null" />
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="tenant-detail-active" @checked($tenant['is_active'])>
                                <label class="form-check-label" for="tenant-detail-active">Tenant aktif</label>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-primary">Simpan Tenant</button>
                    </div>
                </form>
            </x-admin.card>

            <x-admin.card title="Langganan" icon="refresh" flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Paket</th>
                                <th scope="col" class="text-center">Siklus</th>
                                <th scope="col" class="text-end">Nilai</th>
                                <th scope="col" class="text-center">Status</th>
                                <th scope="col">Berakhir</th>
                                <th scope="col" class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($subscriptions as $subscription)
                                <tr>
                                    <td>{{ $subscription['plan'] }}</td>
                                    <td class="text-center">{{ \Illuminate\Support\Str::headline($subscription['billing_cycle']) }}</td>
                                    <td class="text-end">{{ $subscription['price_formatted'] }}</td>
                                    <td class="text-center">
                                        <x-admin.badge
                                            :text="$subscription['status_label']"
                                            :color="match($subscription['status']) { 'active' => 'success', 'trialing' => 'info', 'past_due' => 'warning', 'cancelled' => 'secondary', default => 'danger' }"
                                            pill
                                        />
                                        @if ($subscription['on_grace'])
                                            <x-admin.badge text="Masa Tenggang" color="warning" pill />
                                        @endif
                                    </td>
                                    <td class="text-nowrap">{{ $subscription['ends_at'] !== '' ? $subscription['ends_at'] : '-' }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('admin.saas.subscriptions.update', $subscription['id']) }}" class="d-flex gap-1">
                                            @csrf
                                            <label class="visually-hidden" for="sub-status-{{ $subscription['id'] }}">Status langganan {{ $subscription['plan'] }}</label>
                                            <select class="form-select form-select-sm" id="sub-status-{{ $subscription['id'] }}" name="status">
                                                @foreach (\App\Services\Backoffice\SaasService::SUBSCRIPTION_STATUSES as $value => $label)
                                                    <option value="{{ $value }}" @selected($subscription['status'] === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <x-admin.empty-state compact icon="refresh" title="Belum ada langganan" text="Tenant ini belum memiliki langganan aktif." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>

        <div class="col-lg-6">
            <x-admin.card title="Pemakaian" icon="gauge" subtitle="Terakhir diperbarui {{ $usage['updated_at'] }}">
                @foreach ($usage as $key => $meter)
                    @if (is_array($meter) && isset($meter['key']))
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small">
                                <span class="text-secondary">{{ $meter['label'] }}</span>
                                <span class="fw-semibold">
                                    {{ $meter['used_formatted'] }}@if ($meter['limit'] !== null) / {{ number_format($meter['limit'], 0, ',', '.') }}@endif
                                </span>
                            </div>
                            @if ($meter['percent'] !== null)
                                <div class="progress" role="progressbar" aria-label="{{ $meter['label'] }}" aria-valuenow="{{ $meter['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar {{ $meter['percent'] >= 90 ? 'bg-danger' : ($meter['percent'] >= 70 ? 'bg-warning' : 'bg-success') }}" style="width: {{ min(100, $meter['percent']) }}%"></div>
                                </div>
                            @else
                                <div class="progress" role="progressbar" aria-label="{{ $meter['label'] }} tanpa batas" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar bg-secondary" style="width: 0%"></div>
                                </div>
                            @endif
                        </div>
                    @endif
                @endforeach
            </x-admin.card>
        </div>
    </div>
@endsection
