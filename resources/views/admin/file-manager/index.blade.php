@extends('layouts.admin')

@section('title', 'Manajemen Berkas')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Content', ['label' => 'Media']]" />
@endsection

@section('content')
    <x-admin.page-header title="Manajemen Berkas" subtitle="Unggah media di luar direktori upload, dengan validasi MIME dan nama acak." />

    <div class="row g-3">
        <div class="col-lg-4">
            <x-admin.card title="Unggah" icon="upload">
                <form method="POST" action="{{ route('admin.file-manager.upload') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="file-input">Pilih Berkas</label>
                        <input
                            class="form-control"
                            type="file"
                            id="file-input"
                            name="file"
                            required
                            accept="{{ implode(',', $limits['types']) }}"
                        >
                        <small class="text-secondary">Maksimum {{ number_format($limits['max_kb'], 0, ',', '.') }} KB.</small>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Unggah</button>
                </form>

                <x-admin.alert type="info" class="mt-3" :dismissible="false" title="Validasi berbasis byte" icon="shield">
                    Jenis berkas ditentukan dari isi berkas, bukan dari ekstensi. Gambar JPEG diproses ulang sehingga metadata EXIF ikut hilang. Nama berkas yang disimpan dibangkitkan acak.
                </x-admin.alert>
            </x-admin.card>
        </div>

        <div class="col-lg-8">
            <x-admin.card title="Berkas" icon="folder" flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Nama</th>
                                <th scope="col">Jenis</th>
                                <th scope="col" class="text-end">Ukuran</th>
                                <th scope="col">Diubah</th>
                                <th scope="col" class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($files as $file)
                                <tr>
                                    <td>
                                        <a href="{{ $file['url'] }}" target="_blank" rel="noopener noreferrer">{{ $file['name'] }}</a>
                                        <small class="d-block text-secondary text-break">{{ $file['path'] }}</small>
                                    </td>
                                    <td><code class="small">{{ $file['mime'] }}</code></td>
                                    <td class="text-end">{{ number_format($file['size_kb'], 0, ',', '.') }} KB</td>
                                    <td class="text-nowrap">{{ $file['modified_label'] }}</td>
                                    <td class="text-end">
                                        <x-admin.confirmation-form
                                            action="{{ route('admin.file-manager.destroy') }}"
                                            method="POST"
                                            label="Hapus"
                                            variant="outline-danger"
                                            icon="trash"
                                            size="btn-sm"
                                            message="Berkas akan dihapus permanen dari server. Lanjutkan?"
                                            class="d-inline-block"
                                        >
                                            <input type="hidden" name="path" value="{{ $file['path'] }}">
                                        </x-admin.confirmation-form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <x-admin.empty-state icon="folder" title="Belum ada berkas" text="Unggah berkas pertama Anda di panel sebelah." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>
    </div>
@endsection
