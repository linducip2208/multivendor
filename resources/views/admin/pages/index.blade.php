@extends('layouts.admin')

@section('title', 'Halaman Statis')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Content', ['label' => 'Halaman']]" />
@endsection

@section('content')
    <x-admin.page-header title="Halaman Statis" subtitle="Konten halaman publik yang dikelola administrator." />

    <form method="POST" action="{{ route('admin.pages.update') }}">
        @csrf
        @method('PUT')
        <div class="row g-3">
            @foreach ($pages as $page)
                <div class="col-12 col-xl-6">
                    <x-admin.card :title="$page['label']" icon="file-text" :subtitle="$page['configured'] ? 'Sudah ada isi' : 'Kosong'">
                        <label class="form-label small mb-1" for="page-{{ $page['key'] }}">Isi Halaman (HTML)</label>
                        <textarea
                            class="form-control"
                            id="page-{{ $page['key'] }}"
                            name="pages[{{ $page['key'] }}]"
                            rows="12"
                            maxlength="100000"
                            placeholder="&lt;p&gt;Isi halaman…&lt;/p&gt;"
                        >{{ $page['content'] }}</textarea>
                    </x-admin.card>
                </div>
            @endforeach
        </div>

        <div class="d-flex justify-content-end mt-3">
            <button type="submit" class="btn btn-primary">
                <x-admin.icon name="save" :size="14" /> Simpan Semua Halaman
            </button>
        </div>
    </form>

    @isset($versions)
        @if (count($versions) > 0)
            <x-admin.card title="Riwayat versi" icon="history" class="mt-3">
                <ul class="list-unstyled mb-0 small">
                    @foreach ($versions as $version)
                        <li class="py-1 {{ $loop->last ? '' : 'border-bottom' }}">
                            <span class="fw-medium">{{ $version['at'] }}</span>
                            <span class="text-secondary">· admin #{{ $version['actor_id'] ?? '—' }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="text-secondary small mb-0 mt-2">Pratinjau: gunakan tombol pratinjau homepage untuk melihat hasil sebelum tayang.</p>
            </x-admin.card>
        @endif
    @endisset
@endsection
