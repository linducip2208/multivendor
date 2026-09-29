@extends('layouts.admin')

@section('title', 'Log Aplikasi')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Sistem', ['label' => 'Log']]" />
@endsection

@section('content')
    <x-admin.page-header title="Log Aplikasi" subtitle="Isi berkas log di storage/logs. Hanya berkas .log yang dapat dibaca." />

    <x-admin.alert type="info" :dismissible="false" title="Batasan berkas" icon="lock">
        Pembacaan dan penghapusan hanya berlaku untuk berkas <code>*.log</code> di <code>storage/logs</code>. Jalur di luar direktori tersebut ditolak.
    </x-admin.alert>

    <div class="row g-3">
        <div class="col-lg-3">
            <x-admin.card title="Berkas Log" icon="file-code" flush class="mb-3">
                <div class="list-group list-group-flush">
                    @forelse ($files as $file)
                        <a
                            href="{{ route('admin.system.logs', ['file' => $file['name']]) }}"
                            class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $selected === $file['name'] ? 'active' : '' }}"
                            @if ($selected === $file['name']) aria-current="true" @endif
                        >
                            <span class="text-truncate">{{ $file['name'] }}</span>
                            <span class="badge bg-secondary-lt text-secondary ms-2">{{ number_format($file['bytes'] / 1024, 1, ',', '.') }} KB</span>
                        </a>
                    @empty
                        <div class="list-group-item text-secondary small">Belum ada berkas log.</div>
                    @endforelse
                </div>
            </x-admin.card>

            @if ($selected !== '')
                <x-admin.card title="Aksi" icon="trash">
                    <form method="POST" action="{{ route('admin.system.logs.clear') }}">
                        @csrf
                        <input type="hidden" name="file" value="{{ $selected }}">
                        <button type="submit" class="btn btn-outline-danger w-100">
                            Hapus {{ $selected }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.system.logs.clear') }}" class="mt-2">
                        @csrf
                        <input type="hidden" name="file" value="*">
                        <button type="submit" class="btn btn-outline-danger w-100">Hapus Semua Log</button>
                    </form>
                </x-admin.card>
            @endif
        </div>

        <div class="col-lg-9">
            <x-admin.card
                :title="$selected !== '' ? $selected : 'Tidak ada berkas dipilih'"
                :subtitle="$content['name'] !== null ? number_format($content['bytes'] / 1024, 1, ',', '.').' KB · diubah '.$content['modified'] : null"
                icon="file-code"
            >
                <x-admin.alert type="warning" :dismissible="false" title="Log dapat berisi data sensitif" icon="alert-triangle">
                    Isi log ditampilkan apa adanya. Pastikan tidak ada kredensial yang ditulis ke dalam log pada instalasi ini.
                </x-admin.alert>
                <pre class="small mb-0" style="max-height: 620px; overflow-y: auto; white-space: pre-wrap; word-break: break-word;">@forelse ($content['lines'] as $line){{ $line }}
@empty
Tidak ada isi.
@endforelse</pre>
            </x-admin.card>
        </div>
    </div>
@endsection
