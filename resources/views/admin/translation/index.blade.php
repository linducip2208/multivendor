@extends('layouts.admin')

@section('title', 'Translation Database')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Sistem', ['label' => 'Translation']]" />
@endsection

@section('content')
    <x-admin.page-header title="Database Terjemahan" subtitle="Kunci teks yang dipakai di seluruh aplikasi." />

    <x-admin.card class="mb-3" title="Tambah Terjemahan" icon="plus">
        <form method="POST" action="{{ route('admin.translation.update') }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-12 col-md-3">
                    <x-admin.form-field
                        name="key"
                        label="Kunci"
                        required
                        :maxlength="160"
                        placeholder="welcome_message"
                        help="Hanya huruf, angka, titik, garis."
                    />
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label" for="translation-group">Grup</label>
                    <select class="form-select" id="translation-group" name="group" required>
                        @foreach ($groups as $group)
                            <option value="{{ $group['value'] }}">{{ $group['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                @foreach ($locales as $locale)
                    <div class="col-12 col-md-3">
                        <x-admin.form-field
                            :name="'values.'.$locale"
                            :label="'Nilai ('.$locale.')'"
                            required
                            :maxlength="1000"
                        />
                    </div>
                @endforeach
            </div>
            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary">Simpan Terjemahan</button>
            </div>
        </form>
    </x-admin.card>

    <x-admin.card class="mb-3" title="Filter" icon="filter">
        <x-admin.filters
            :action="route('admin.translation.index')"
            :filters="[['name' => 'search', 'label' => 'Cari', 'placeholder' => 'Kunci atau nilai']]"
        />
    </x-admin.card>

    <x-admin.card title="Daftar Terjemahan" icon="language" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Kunci</th>
                        <th scope="col">Grup</th>
                        <th scope="col">Locale</th>
                        <th scope="col">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td><code class="small">{{ $row['key'] }}</code></td>
                            <td>{{ $row['group'] }}</td>
                            <td><x-admin.badge :text="$row['locale']" color="info" pill /></td>
                            <td>{{ $row['value'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <x-admin.empty-state icon="language" title="Belum ada terjemahan" text="Tambahkan kunci pertama di atas." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <div class="mt-3">
        <x-admin.pagination :paginator="\App\Support\AdminPaginator::fromArray($pagination, $pagination['total'], $pagination['per_page'], $pagination['current_page'])" size="sm" />
    </div>
@endsection
