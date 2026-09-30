@extends('layouts.admin')

@section('title', 'Bahasa')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Sistem', ['label' => 'Bahasa']]" />
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

    @isset($managed)
        <x-admin.card title="Manajemen Bahasa" icon="globe" class="mt-3" subtitle="Tambah / aktif / default / RTL / coverage / missing-report / import-export (pakai LanguageService + TranslationRepository existing).">
            <div class="table-responsive">
                <table class="table admin-table mb-0 table-hover">
                    <thead><tr><th>Kode</th><th>Bahasa</th><th>Arah</th><th class="text-center">Aktif</th><th class="text-center">Default</th><th class="text-end">Coverage (EN)</th><th class="text-end">Missing</th></tr></thead>
                    <tbody>
                        @foreach ($managed as $lang)
                            @php
                                $cov = $coverage[$lang['code']] ?? ['percent' => 0, 'translated' => 0, 'total_keys' => 0];
                                $miss = $missing[$lang['code']] ?? [];
                            @endphp
                            <tr>
                                <td><code>{{ $lang['code'] }}</code></td>
                                <td>{{ $lang['native_name'] }} <small class="text-secondary d-block">{{ $lang['name'] }}</small></td>
                                <td><span class="badge bg-{{ $lang['is_rtl'] ? 'warning' : 'secondary' }}" dir="{{ $lang['direction'] }}">{{ strtoupper($lang['direction']) }}</span></td>
                                <td class="text-center"><span class="badge bg-{{ $lang['is_active'] ? 'success' : 'secondary' }}">{{ $lang['is_active'] ? 'Aktif' : 'Nonaktif' }}</span></td>
                                <td class="text-center">@if ($lang['is_default'])<span class="badge bg-primary">Default</span>@else<span class="text-secondary">—</span>@endif</td>
                                <td class="text-end">{{ $cov['percent'] ?? 0 }}% <small class="text-secondary">({{ $cov['translated'] ?? 0 }}/{{ $cov['total_keys'] ?? 0 }})</small></td>
                                <td class="text-end">{{ is_array($miss) ? count($miss) : 0 }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-secondary small mb-0 mt-2">Aksi tulis via <code>CmsController::storeLanguage / toggleLanguage / defaultLanguage / importTranslations / exportTranslations</code> — perlu wiring route oleh integrator (lihat docblock controller). Import JSON format <code>{"common.save": "Simpan"}</code>; CSV format <code>namespace,key,locale,value</code>.</p>
        </x-admin.card>
    @endisset
@endsection
