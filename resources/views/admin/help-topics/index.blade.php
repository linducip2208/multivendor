@extends('layouts.admin')

@section('title', 'Help Topics')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['System', ['label' => 'Help Topics']]" />
@endsection

@section('content')
    <x-admin.page-header title="Topik Bantuan" subtitle="Pertanyaan umum yang ditampilkan di halaman bantuan." />

    <form method="POST" action="{{ route('admin.help-topics.update') }}">
        @csrf
        @method('PUT')
        <x-admin.card title="Daftar Topik" icon="life-buoy">
            <div class="row g-3">
                @foreach ($topics as $topic)
                    <div class="col-12 col-lg-6">
                        <div class="border rounded-3 p-3 h-100">
                            <p class="fw-semibold small mb-2">Topik #{{ $topic['position'] }}</p>
                            <div class="mb-2">
                                <label class="form-label small mb-1" for="topic-{{ $topic['position'] }}-title">Judul</label>
                                <input
                                    type="text"
                                    class="form-control form-control-sm"
                                    id="topic-{{ $topic['position'] }}-title"
                                    name="topics[{{ $topic['position'] }}][title]"
                                    value="{{ $topic['title'] }}"
                                    maxlength="160"
                                >
                            </div>
                            <div>
                                <label class="form-label small mb-1" for="topic-{{ $topic['position'] }}-body">Isi Jawaban</label>
                                <textarea
                                    class="form-control form-control-sm"
                                    id="topic-{{ $topic['position'] }}-body"
                                    name="topics[{{ $topic['position'] }}][body]"
                                    rows="4"
                                    maxlength="5000"
                                >{{ $topic['body'] }}</textarea>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary">
                    <x-admin.icon name="save" :size="14" /> Simpan Topik
                </button>
            </div>
        </x-admin.card>
    </form>
@endsection
