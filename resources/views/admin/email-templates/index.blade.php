@extends('layouts.admin')

@section('title', 'Email Templates')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['System', ['label' => 'Email']]" />
@endsection

@section('content')
    <x-admin.page-header title="Template Email" subtitle="Subjek dan isi email yang dikirim ke pelanggan." />

    <x-admin.alert type="info" :title="'Variabel yang tersedia'" icon="mail">
        {{ implode(' ', $variables) }} — variabel replaced otomatis saat email dikirim.
    </x-admin.alert>

    <div class="row g-3">
        @foreach ($templates as $template)
            <div class="col-12 col-xl-6">
                <x-admin.card :title="$template['label']" icon="file-text" :subtitle="$template['configured'] ? 'Sudah dikonfigurasi' : 'Belum dikonfigurasi'">
                    <form method="POST" action="{{ route('admin.email-templates.update') }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="key" value="{{ $template['key'] }}">

                        <div class="mb-2">
                            <label class="form-label small mb-1" for="subject-{{ $template['key'] }}">Subjek</label>
                            <input
                                type="text"
                                class="form-control form-control-sm"
                                id="subject-{{ $template['key'] }}"
                                name="subject"
                                value="{{ $template['subject'] }}"
                                maxlength="200"
                                required
                            >
                        </div>
                        <div class="mb-3">
                            <label class="form-label small mb-1" for="body-{{ $template['key'] }}">Isi (HTML)</label>
                            <textarea
                                class="form-control form-control-sm"
                                id="body-{{ $template['key'] }}"
                                name="body"
                                rows="7"
                                maxlength="20000"
                                required
                            >{{ $template['body'] }}</textarea>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
                        </div>
                    </form>
                </x-admin.card>
            </div>
        @endforeach
    </div>
@endsection
