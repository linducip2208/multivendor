@extends('layouts.admin')

@section('title', 'Plugin & Tema / Plugins & Themes')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Developers', ['label' => 'Plugins']]" />
@endsection

@section('content')
    <x-admin.page-header title="Plugin & Tema" subtitle="Plugins & themes — manifest, status aktif, dan tema aktif. / Manifest list, enable state, and active theme." />

    <x-admin.card title="Plugin Terdaftar / Registered Plugins" icon="package" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Plugin</th>
                        <th scope="col">Gateway</th>
                        <th scope="col" class="text-center">Aktif / Active</th>
                        <th scope="col">Capabilities</th>
                        <th scope="col">Settings</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($plugins as $plugin)
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $plugin->name }}</span>
                                <small class="d-block text-secondary"><code>{{ $plugin->code }}</code> · v{{ $plugin->version }} · {{ $plugin->provider }}</small>
                            </td>
                            <td><code class="small">{{ $plugin->gateway }}</code></td>
                            <td class="text-center">
                                @if (in_array($plugin->code, $enabled, true))
                                    <x-admin.badge text="ENABLED" color="success" pill />
                                @else
                                    <x-admin.badge text="DISABLED" color="secondary" pill />
                                @endif
                            </td>
                            <td class="small">{{ implode(', ', $plugin->countries) }} · {{ implode(', ', $plugin->currencies) }} · {{ implode(', ', $plugin->methods) }}</td>
                            <td class="small">{{ $plugin->settings !== [] ? implode(', ', $plugin->settings) : '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-admin.empty-state icon="package" title="Belum ada plugin" text="Tambahkan app/Plugins/*/plugin.json." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <x-admin.card class="mt-3" title="Tema Aktif / Active Theme" icon="palette">
        <p class="mb-2">Tema aktif / Active theme: <code>{{ $activeTheme }}</code></p>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-sm">
                <thead>
                    <tr>
                        <th scope="col">Kode / Code</th>
                        <th scope="col">Nama / Name</th>
                        <th scope="col">Versi / Version</th>
                        <th scope="col" class="text-center">Aktif</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($themes as $theme)
                        <tr>
                            <td><code>{{ $theme['code'] }}</code></td>
                            <td>{{ $theme['name'] }}</td>
                            <td>{{ $theme['version'] }}</td>
                            <td class="text-center">
                                <x-admin.badge :text="$theme['active'] ? 'AKTIF / ACTIVE' : '-'" :color="$theme['active'] ? 'success' : 'secondary'" pill />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="small text-secondary mt-2 mb-0">
            Cara buat tema / How to make a theme: lihat <code>PLUGIN_SYSTEM.md</code> —
            buat folder <code>resources/views/themes/&lt;nama&gt;/theme.json</code>, set
            <code>theme.active</code>, override view via namespace <code>theme::</code>.
        </p>
    </x-admin.card>
@endsection
