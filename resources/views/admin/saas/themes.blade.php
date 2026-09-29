@extends('layouts.admin')

@section('title', 'Tema')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['SaaS', ['label' => 'Tema']]" />
@endsection

@section('content')
    <x-admin.page-header title="Tema" subtitle="Pilihan tampilan storefront dan tema kustom per tenant." />

    <x-admin.card title="Tema Bawaan" subtitle="Tersedia di setiap instalasi." icon="palette" class="mb-3">
        <div class="row g-3">
            @foreach ($bundled as $theme)
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <x-admin.icon name="palette" :size="20" />
                            <x-admin.badge :text="$theme['code']" color="secondary" pill />
                        </div>
                        <p class="fw-semibold mb-1">{{ $theme['name'] }}</p>
                        <p class="small text-secondary mb-2">{{ $theme['description'] }}</p>
                        @if (! $theme['customizable'])
                            <small class="text-secondary">Tidak dapat dikustomisasi.</small>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </x-admin.card>

    <div class="row g-3">
        <div class="col-lg-7">
            <x-admin.card title="Tema Tenant" subtitle="Tema kustom yang disimpan pada setiap tenant." icon="layers" flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Tenant</th>
                                <th scope="col">Warna</th>
                                <th scope="col" class="text-center">Mode Gelap</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($custom as $theme)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.tenants.show', $theme['id']) }}">{{ $theme['name'] }}</a>
                                    </td>
                                    <td>
                                        <span class="d-inline-flex align-items-center gap-2">
                                            <span class="badge rounded-1" style="width: 18px; height: 18px; background-color: {{ $theme['color'] }};" aria-hidden="true"></span>
                                            <code class="small">{{ $theme['color'] }}</code>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <x-admin.badge :text="$theme['dark'] ? 'Ya' : 'Tidak'" :color="$theme['dark'] ? 'dark' : 'secondary'" pill />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">
                                        <x-admin.empty-state compact icon="layers" title="Belum ada tema kustom" text="Tenant dapat menyimpan tema sendiri melalui API tenant." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>

        <div class="col-lg-5">
            <x-admin.card title="Katalog Fitur" icon="toggle-left" subtitle="Fitur yang dapat dimatikan per tenant.">
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($features as $feature)
                        <x-admin.badge
                            :text="$feature->label()"
                            :color="$feature->isCore() ? 'primary' : 'secondary'"
                            pill
                        />
                    @endforeach
                </div>
                <p class="small text-secondary mt-3 mb-0">
                    Fitur inti ({{ collect($features)->filter(fn ($feature): bool => $feature->isCore())->map(fn ($feature): string => $feature->value)->implode(', ') }}) tidak dapat dimatikan.
                </p>
            </x-admin.card>
        </div>
    </div>
@endsection
