@extends('layouts.admin')

@section('title', 'Manajemen Berkas')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Content', ['label' => 'Media']]" />
@endsection

@section('content')
    <x-admin.page-header title="Manajemen Berkas" subtitle="Media library terpusat. Berkas di storage/app/public disajikan via /img/… (route img.serve)." />

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

                <x-admin.alert type="warning" class="mt-3" :dismissible="false" title="Batas media library" icon="info">
                    Jalur picker baru (JSON): JPG, PNG, WebP, GIF, SVG, PDF — maks 5 MB. Executable dan double-extension (mis. <code>foto.php.jpg</code>) ditolak.
                </x-admin.alert>
            </x-admin.card>

            <x-admin.card title="Buat Folder" icon="folder-plus" class="mt-3">
                {{-- Endpoint JSON baru; wiring route: POST admin/file-manager/folder -> FileManagerController@makeFolder --}}
                <form method="POST" action="{{ url('/admin/file-manager/folder') }}" id="media-folder-form">
                    @csrf
                    <label class="form-label" for="folder-name">Nama folder (di dalam uploads/)</label>
                    <input class="form-control" id="folder-name" name="name" maxlength="60" required pattern="[A-Za-z0-9 _-]+" placeholder="mis. banner-promo">
                    <small class="text-secondary d-block mt-1">Huruf, angka, spasi, strip, underscore. Maks 60 karakter.</small>
                    <button type="submit" class="btn btn-outline-primary w-100 mt-2">Buat Folder</button>
                </form>
            </x-admin.card>
        </div>

        <div class="col-lg-8">
            <x-admin.card title="Pustaka Media" icon="folder" subtitle="Cari, saring, pratinjau, salin URL /img/…, atau hapus.">
                <div class="row g-2 mb-3">
                    <div class="col-md-7">
                        <input type="search" class="form-control" id="media-search" placeholder="Cari nama berkas…" autocomplete="off">
                    </div>
                    <div class="col-md-5">
                        <select class="form-select" id="media-filter">
                            <option value="all">Semua tipe</option>
                            <option value="image">Gambar</option>
                            <option value="document">Dokumen (PDF)</option>
                        </select>
                    </div>
                </div>
                <p class="small text-secondary mb-2" id="media-count">Menampilkan {{ count($files) }} berkas.</p>

                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover" id="media-table">
                        <thead>
                            <tr>
                                <th scope="col">Pratinjau</th>
                                <th scope="col">Nama</th>
                                <th scope="col">Jenis</th>
                                <th scope="col" class="text-end">Ukuran</th>
                                <th scope="col">Diubah</th>
                                <th scope="col" class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($files as $file)
                                @php
                                    $imgUrl = url('img/'.ltrim(str_replace('\\', '/', $file['path']), '/'));
                                    $isImage = str_starts_with((string) $file['mime'], 'image/');
                                    $kind = $isImage ? 'image' : (((string) $file['mime'] === 'application/pdf') ? 'document' : 'other');
                                @endphp
                                <tr data-media-row data-name="{{ strtolower($file['name'].' '.$file['path']) }}" data-kind="{{ $kind }}">
                                    <td style="width:72px;">
                                        @if ($isImage)
                                            <img src="{{ $imgUrl }}" alt="" loading="lazy" class="rounded border" style="width:56px;height:56px;object-fit:cover;">
                                        @else
                                            <span class="badge bg-secondary">PDF</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ $imgUrl }}" target="_blank" rel="noopener noreferrer">{{ $file['name'] }}</a>
                                        <small class="d-block text-secondary text-break">{{ $file['path'] }}</small>
                                        <small class="d-block text-secondary text-break"><code>/img/{{ ltrim($file['path'], '/') }}</code></small>
                                    </td>
                                    <td><code class="small">{{ $file['mime'] }}</code></td>
                                    <td class="text-end">{{ number_format($file['size_kb'], 0, ',', '.') }} KB</td>
                                    <td class="text-nowrap">{{ $file['modified_label'] }}</td>
                                    <td class="text-end text-nowrap">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary"
                                            data-media-preview="{{ $imgUrl }}"
                                            data-media-name="{{ $file['name'] }}"
                                            data-media-image="{{ $isImage ? '1' : '0' }}"
                                        >Pratinjau</button>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary"
                                            data-media-copy="{{ $imgUrl }}"
                                        >Salin URL</button>
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
                                    <td colspan="6">
                                        <x-admin.empty-state icon="folder" title="Belum ada berkas" text="Unggah berkas pertama Anda di panel sebelah." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="small text-secondary mt-2 mb-0 d-none" id="media-empty">Tidak ada berkas yang cocok dengan pencarian.</p>
            </x-admin.card>
        </div>
    </div>

    <div class="modal fade" id="media-preview-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="media-preview-title">Pratinjau</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body text-center" id="media-preview-body"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" id="media-preview-copy">Salin URL</button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        function applyFilter() {
            var q = (document.getElementById('media-search').value || '').toLowerCase().trim();
            var kind = document.getElementById('media-filter').value;
            var rows = document.querySelectorAll('[data-media-row]');
            var shown = 0;
            rows.forEach(function (row) {
                var okQ = !q || (row.getAttribute('data-name') || '').indexOf(q) !== -1;
                var okK = kind === 'all' || row.getAttribute('data-kind') === kind;
                var show = okQ && okK;
                row.style.display = show ? '' : 'none';
                if (show) shown += 1;
            });
            document.getElementById('media-count').textContent = 'Menampilkan ' + shown + ' berkas.';
            document.getElementById('media-empty').classList.toggle('d-none', shown !== 0);
        }

        function copyText(text, btn) {
            function done() {
                if (!btn) return;
                var label = btn.textContent;
                btn.textContent = 'Disalin!';
                setTimeout(function () { btn.textContent = label; }, 1200);
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done, function () { fallback(); });
            } else { fallback(); }
            function fallback() {
                var ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); done(); } catch (e) {}
                document.body.removeChild(ta);
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            var search = document.getElementById('media-search');
            var filter = document.getElementById('media-filter');
            if (search) search.addEventListener('input', applyFilter);
            if (filter) filter.addEventListener('change', applyFilter);

            document.addEventListener('click', function (ev) {
                var copy = ev.target.closest('[data-media-copy]');
                if (copy) { copyText(copy.getAttribute('data-media-copy'), copy); return; }
                var prev = ev.target.closest('[data-media-preview]');
                if (prev) {
                    var url = prev.getAttribute('data-media-preview');
                    var name = prev.getAttribute('data-media-name');
                    var isImg = prev.getAttribute('data-media-image') === '1';
                    document.getElementById('media-preview-title').textContent = name || 'Pratinjau';
                    document.getElementById('media-preview-body').innerHTML = isImg
                        ? '<img src="' + url.replace(/"/g, '') + '" alt="" style="max-width:100%;max-height:60vh;" class="rounded border">'
                        : '<a href="' + url.replace(/"/g, '') + '" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary">Buka dokumen</a><div class="small text-secondary mt-2">' + url.replace(/</g, '&lt;') + '</div>';
                    var copyBtn = document.getElementById('media-preview-copy');
                    if (copyBtn) copyBtn.setAttribute('data-media-copy', url);
                    if (window.bootstrap && window.bootstrap.Modal) {
                        window.bootstrap.Modal.getOrCreateInstance(document.getElementById('media-preview-modal')).show();
                    }
                }
            });

            var folderForm = document.getElementById('media-folder-form');
            if (folderForm) folderForm.addEventListener('submit', function (ev) {
                var input = document.getElementById('folder-name');
                var v = (input.value || '').trim();
                if (!/^[A-Za-z0-9 _-]+$/.test(v)) {
                    ev.preventDefault();
                    alert('Nama folder tidak valid. Gunakan huruf, angka, spasi, strip, atau underscore.');
                }
            });
        });
    })();
    </script>
@endsection
