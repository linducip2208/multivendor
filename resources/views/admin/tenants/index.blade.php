@extends('layouts.admin')

@section('title', 'Tenant')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['SaaS', ['label' => 'Tenant']]" />
@endsection

@section('content')
    <x-admin.page-header title="Tenant" subtitle="Instalasi white-label yang berjalan di atas platform ini.">
        <x-slot:actions>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#tenant-modal" aria-haspopup="dialog">
                <x-admin.icon name="plus" :size="14" /> Tambah Tenant
            </button>
        </x-slot:actions>
    </x-admin.page-header>

    @unless ($feature_enabled && $pagination['total'] > 0)
        <x-admin.alert type="info" :dismissible="false" title="SaaS belum aktif pada instalasi ini" icon="layers">
            Belum ada tenant terdaftar dan fitur white-label dimatikan. Daftar di bawah sengaja dikosongkan — bukan disembunyikan — supaya jelas tidak ada instalasi lain yang bisa dilihat dari sini.
        </x-admin.alert>
    @endunless

    <div class="row g-3 mb-3">
        @foreach ($kpis as $kpi)
            <div class="col-6 col-xl-3">
                <x-admin.stat :label="$kpi['label']" :value="$kpi['value']" :icon="$kpi['icon']" :color="$kpi['color']" :hint="$kpi['hint']" />
            </div>
        @endforeach
    </div>

    <x-admin.card class="mb-3" title="Filter" icon="filter">
        <x-admin.filters
            :action="route('admin.tenants.index')"
            :filters="[
                ['name' => 'search', 'label' => 'Cari', 'placeholder' => 'Nama, slug, atau domain'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['' => 'Semua', 'active' => 'Aktif', 'inactive' => 'Nonaktif']],
            ]"
        />
    </x-admin.card>

    <x-admin.card title="Daftar Tenant" icon="globe" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Tenant</th>
                        <th scope="col">Domain</th>
                        <th scope="col">Mata Uang</th>
                        <th scope="col" class="text-end">Komisi Default</th>
                        <th scope="col">Langganan</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                <a href="{{ $row['url'] }}">{{ $row['name'] }}</a>
                                <small class="d-block text-secondary">{{ $row['slug'] }}</small>
                            </td>
                            <td>
                                <code class="small">{{ $row['domain'] }}</code>
                                @if ($row['extra_domains'] !== [])
                                    <small class="d-block text-secondary">{{ count($row['extra_domains']) }} domain tambahan</small>
                                @endif
                            </td>
                            <td>{{ $row['currency_code'] }}</td>
                            <td class="text-end">{{ number_format($row['commission_rate'], 2, ',', '.') }}%</td>
                            <td>
                                @if ($row['subscription'] !== null)
                                    <span class="d-block">{{ $row['subscription']['plan'] }}</span>
                                    <x-admin.badge
                                        :text="$row['subscription']['status_label']"
                                        :color="match($row['subscription']['status']) { 'active' => 'success', 'trialing' => 'info', 'past_due' => 'warning', 'cancelled' => 'secondary', default => 'danger' }"
                                        pill
                                    />
                                @else
                                    <span class="text-secondary">Tidak ada</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <x-admin.badge :text="$row['is_active'] ? 'Aktif' : 'Nonaktif'" :color="$row['is_active'] ? 'success' : 'secondary'" pill />
                                @if ($row['on_trial'])
                                    <x-admin.badge text="Masa Uji" color="info" pill />
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm" role="group" aria-label="Aksi tenant {{ $row['name'] }}">
                                    <a href="{{ $row['url'] }}" class="btn btn-outline-secondary">Detail</a>
                                    <x-admin.confirmation-form
                                        :action="route('admin.tenants.destroy', $row['id'])"
                                        message="Tenant akan dinonaktifkan dan dihapus. Langganannya dibatalkan. Lanjutkan?"
                                        label="Hapus"
                                        variant="outline-danger"
                                        icon="trash"
                                        size="btn-sm"
                                    />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-admin.empty-state
                                    icon="globe"
                                    title="Belum ada tenant"
                                    text="Instalasi ini berjalan sebagai satu tenant. Tambahkan tenant hanya bila platform dipakai sebagai white-label multi-penyewa."
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

    <x-admin.modal id="tenant-modal" title="Tambah Tenant" icon="plus" size="lg">
        <form method="POST" action="{{ route('admin.tenants.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-8">
                    <x-admin.form-field name="name" label="Nama Tenant" required :maxlength="120" />
                </div>
                <div class="col-md-4">
                    <x-admin.form-field name="domain" label="Domain Utama" required :maxlength="160" placeholder="tokomu.example" />
                </div>
                <div class="col-md-6">
                    <x-admin.form-field name="contact_email" label="Email Kontak" type="email" :maxlength="160" />
                </div>
                <div class="col-md-6">
                    <x-admin.form-field name="contact_phone" label="Telepon Kontak" :maxlength="30" />
                </div>
                <div class="col-md-4">
                    <x-admin.form-field name="currency_code" label="Mata Uang" value="IDR" :maxlength="3" />
                </div>
                <div class="col-md-4">
                    <x-admin.form-field name="locale" label="Locale" value="id" :maxlength="5" />
                </div>
                <div class="col-md-4">
                    <x-admin.form-field name="timezone" label="Zona Waktu" :value="config('app.timezone')" />
                </div>
                <div class="col-md-6">
                    <x-admin.form-field name="default_commission_rate" label="Komisi Default (%)" type="number" value="10" :min="0" :max="100" :step="0.01" />
                </div>
                <div class="col-md-6">
                    <x-admin.form-field name="trial_ends_at" label="Akhir Masa Uji" type="date" help="Kosongkan bila tidak ada masa uji." />
                </div>
                <div class="col-12">
                    <label class="form-label" for="tenant-features">Fitur Aktif</label>
                    <select class="form-select" id="tenant-features" name="enabled_features[]" multiple size="6" data-multi-select>
                        @foreach (\App\Enums\PlatformFeature::cases() as $feature)
                            <option value="{{ $feature->value }}" @selected($feature->isCore())>{{ $feature->label() }} ({{ $feature->value }})</option>
                        @endforeach
                    </select>
                    <small class="text-secondary">Fitur inti selalu tersedia dan tidak dapat dimatikan.</small>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="tenant-active" checked>
                        <label class="form-check-label" for="tenant-active">Tenant aktif</label>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Tenant</button>
            </div>
        </form>
    </x-admin.modal>
@endsection
