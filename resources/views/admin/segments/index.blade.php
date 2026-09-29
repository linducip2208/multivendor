@extends('layouts.admin')

@section('title', 'Segmen Pelanggan')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Customers', ['label' => 'Segmen']]" />
@endsection

@section('content')
    <x-admin.page-header title="Segmen Pelanggan" subtitle="Pengelompokan berdasarkan fakta transaksi, bukan penilaian terhadap orang.">
        <x-slot:actions>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#segment-modal" aria-haspopup="dialog">
                <x-admin.icon name="plus" :size="14" /> Buat Segmen
            </button>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.alert type="info" :dismissible="false" title="Bagaimana segmen dihitung" icon="layers">
        Setiap segmen berbasis aturan dihitung ulang dari data pesanan nyata: jumlah pesanan, total belanja, jarak transaksi terakhir, rasio pemakaian kupon, dan konsentrasi kategori. Tidak ada atribut pribadi, kondisi kesehatan, atau niat yang disimpulkan.
    </x-admin.alert>

    <x-admin.card class="mb-3" title="Daftar Segmen" icon="layers" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Nama</th>
                        <th scope="col">Deskripsi</th>
                        <th scope="col" class="text-center">Tipe</th>
                        <th scope="col" class="text-end">Anggota</th>
                        <th scope="col">Aturan</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row['name'] }}</td>
                            <td class="text-secondary">{{ \Illuminate\Support\Str::limit($row['description'], 90) }}</td>
                            <td class="text-center">
                                <x-admin.badge
                                    :text="$types[$row['type']] ?? $row['type']"
                                    :color="$row['type'] === 'rule' ? 'primary' : 'secondary'"
                                    pill
                                />
                            </td>
                            <td class="text-end">
                                <x-admin.badge :text="number_format($row['member_count'], 0, ',', '.')" color="info" pill />
                            </td>
                            <td class="small text-secondary">{{ $row['rule_summary'] }}</td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm" role="group" aria-label="Aksi segmen {{ $row['name'] }}">
                                    @if ($row['is_dynamic'])
                                        <form method="POST" action="{{ route('admin.segments.sync', $row['id']) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-primary" title="Hitung ulang dari data pesanan">
                                                <x-admin.icon name="refresh" :size="14" /> Hitung Ulang
                                            </button>
                                        </form>
                                    @endif
                                    <x-admin.confirmation-form
                                        :action="route('admin.segments.destroy', $row['id'])"
                                        message="Segmen beserta seluruh keanggotaannya akan dihapus. Lanjutkan?"
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
                            <td colspan="6">
                                <x-admin.empty-state
                                    icon="layers"
                                    title="Belum ada segmen"
                                    text="Buat segmen pertama dari salah satu pola bawaan di bawah ini."
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <x-admin.card title="Pola Perilaku Bawaan" subtitle="Siap pakai, dapat disesuaikan setelah pembuatan." icon="target">
        <div class="row g-3">
            @foreach ($presets as $key => $preset)
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="border rounded-3 p-3 h-100">
                        <p class="fw-semibold mb-1">{{ $preset['label'] }}</p>
                        <p class="small text-secondary mb-2">{{ $preset['description'] }}</p>
                        <ul class="small mb-0 ps-3">
                            @foreach ($catalogue as $rule)
                                @if (array_key_exists($rule['key'], $preset['rules']))
                                    <li>{{ $rule['label'] }}: {{ $preset['rules'][$rule['key']] }}</li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endforeach
        </div>
    </x-admin.card>

    <x-admin.modal id="segment-modal" title="Buat Segmen" icon="plus" size="lg">
        <form method="POST" action="{{ route('admin.segments.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-12">
                    <x-admin.form-field name="name" label="Nama Segmen" required :maxlength="120" placeholder="Contoh: Pembeli Sering Beli Electronics" />
                </div>
                <div class="col-12">
                    <x-admin.form-field name="description" label="Deskripsi" type="textarea" :rows="2" :maxlength="255" />
                </div>
                <div class="col-6">
                    <label class="form-label" for="segment-type">Tipe</label>
                    <select class="form-select" id="segment-type" name="type" required>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label" for="segment-preset">Pola Bawaan</label>
                    <select class="form-select" id="segment-preset" name="preset">
                        <option value="">Tanpa pola</option>
                        @foreach ($presets as $value => $preset)
                            <option value="{{ $value }}">{{ $preset['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <p class="form-label mb-1">Aturan Tambahan</p>
                    <div class="row g-2">
                        @foreach ($catalogue as $rule)
                            <div class="col-12 col-md-6">
                                <label class="form-label small mb-1" for="rule-{{ $rule['key'] }}">{{ $rule['label'] }}</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="form-control form-control-sm"
                                    id="rule-{{ $rule['key'] }}"
                                    name="rules[{{ $rule['key'] }}]"
                                    aria-label="{{ $rule['label'] }}"
                                >
                                <small class="text-secondary">{{ $rule['type'] }}</small>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Segmen</button>
            </div>
        </form>
    </x-admin.modal>
@endsection
