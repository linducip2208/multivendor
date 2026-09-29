@extends('layouts.admin')

@section('title', 'Bahasa')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['System', ['label' => 'Bahasa']]" />
@endsection

@section('content')
    <x-admin.page-header title="Bahasa" subtitle="Label antarmuka untuk setiap locale yang didukung." />

    <form method="POST" action="{{ route('admin.language.update') }}">
        @csrf
        @method('PUT')

        <x-admin.card class="mb-3" title="Locale Default" icon="globe">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="default-locale">Locale Default</label>
                    <select class="form-select" id="default-locale" name="default_locale" required>
                        @foreach ($locales as $code => $label)
                            <option value="{{ $code }}" @selected($default_locale === $code)>{{ $label }} ({{ $code }})</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-admin.card>

        <x-admin.card title="Label Antarmuka" icon="language" flush>
            <div class="table-responsive">
                <table class="table admin-table mb-0 table-hover">
                    <thead>
                        <tr>
                            <th scope="col">Kunci</th>
                            @foreach ($locales as $code => $label)
                                <th scope="col">{{ $label }} ({{ $code }})</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row['key'] }}</td>
                                @foreach ($locales as $code => $_)
                                    <td>
                                        <input
                                            type="text"
                                            class="form-control form-control-sm"
                                            name="locales.{{ $code }}[{{ $row['key'] }}]"
                                            value="{{ $code === 'id' ? $row['id'] : $row['en'] }}"
                                            maxlength="200"
                                            required
                                            aria-label="{{ $row['key'] }} ({{ $code }})"
                                        >
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-footer d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">
                    <x-admin.icon name="save" :size="14" /> Simpan Terjemahan
                </button>
            </div>
        </x-admin.card>
    </form>
@endsection
