@extends('layouts.admin')

@section('title', 'API Keys')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Developers', ['label' => 'API Keys']]" />
@endsection

@section('content')
    <x-admin.page-header title="API Keys" subtitle="Kredensial untuk akses API publik platform." />

    <x-admin.alert type="danger" title="Nilai penuh hanya ditampilkan sekali" icon="alert-triangle">
        Setelah kunci dibuat, nilai aslinya tidak dapat dibaca lagi — hanya hash-nya yang tersimpan. Simpan di tempat aman sebelum menutup halaman ini.
    </x-admin.alert>

    @if (session('api_key_plaintext'))
        <x-admin.card class="mb-3" title="Kunci Baru" icon="key" subtitle="{{ session('api_key_name') }}">
            <x-admin.alert type="success" :dismissible="false" title="Salin sekarang" icon="check" />
            <div class="input-group">
                <input type="text" class="form-control font-monospace" id="api-key-plaintext" value="{{ session('api_key_plaintext') }}" readonly aria-label="Kunci API">
                <button class="btn btn-outline-secondary" type="button" data-copy-target="#api-key-plaintext">Salin</button>
            </div>
            <small class="text-secondary">Halaman ini akan menampilkan ulang nilai acak bila dimuat ulang, dan nilai yang sebenarnya tidak pernah disimpan di server.</small>
        </x-admin.card>
    @endif

    <x-admin.card class="mb-3" title="Buat Kunci" icon="plus">
        <form method="POST" action="{{ route('admin.api-keys.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-4">
                    <x-admin.form-field name="name" label="Nama Kunci" required :maxlength="120" placeholder="Contoh: Integrasi ERP" />
                </div>
                <div class="col-md-4">
                    <x-admin.form-field name="expires_in_days" label="Masa Berlaku (hari)" type="number" :min="1" :max="3650" help="Kosongkan untuk tidak kedaluwarsa." />
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="api-key-scopes">Cakupan</label>
                    <select class="form-select" id="api-key-scopes" name="scopes[]" multiple size="4" data-multi-select>
                        @foreach ($scopes as $scope => $label)
                            <option value="{{ $scope }}" @selected($scope === 'catalog:read')>{{ $label }} ({{ $scope }})</option>
                        @endforeach
                    </select>
                    <small class="text-secondary">Tahan Ctrl/Cmd untuk memilih lebih dari satu.</small>
                </div>
            </div>
            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary">Buat Kunci</button>
            </div>
        </form>
    </x-admin.card>

    <x-admin.card title="Kunci Terdaftar" icon="key" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Nama</th>
                        <th scope="col">Awalan</th>
                        <th scope="col">Cakupan</th>
                        <th scope="col">Terakhir Dipakai</th>
                        <th scope="col" class="text-center">Kedaluwarsa</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row['name'] }}</td>
                            <td><code class="small">{{ $row['prefix'] }}…</code></td>
                            <td>
                                @forelse ($row['scopes'] as $scope)
                                    <x-admin.badge :text="$scopes[$scope] ?? $scope" color="secondary" pill />
                                @empty
                                    <span class="text-secondary small">Tidak ada</span>
                                @endforelse
                            </td>
                            <td class="text-nowrap">{{ $row['last_used_at'] !== '' ? $row['last_used_at'] : 'belum pernah' }}</td>
                            <td class="text-nowrap">{{ $row['expires_at'] !== '' ? $row['expires_at'] : 'tidak ada' }}</td>
                            <td class="text-center">
                                <x-admin.badge :text="$row['active'] ? 'Aktif' : 'Tidak aktif'" :color="$row['active'] ? 'success' : 'secondary'" pill />
                            </td>
                            <td class="text-end">
                                <x-admin.confirmation-form
                                    :action="route('admin.api-keys.destroy', $row['id'])"
                                    message="Kunci dicabut dan tidak dapat dipakai lagi. Lanjutkan?"
                                    label="Cabut"
                                    variant="outline-danger"
                                    icon="trash"
                                    size="btn-sm"
                                />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-admin.empty-state icon="key" title="Belum ada kunci" text="Buat kunci pertama untuk mulai mengintegrasikan." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-copy-target]').forEach(function (button) {
    button.addEventListener('click', function () {
        var field = document.querySelector(button.dataset.copyTarget);
        if (!field) {
            return;
        }
        field.select();
        field.setSelectionRange(0, 999999);
        if (navigator.clipboard) {
            navigator.clipboard.writeText(field.value);
        }
    });
});
</script>
@endpush
