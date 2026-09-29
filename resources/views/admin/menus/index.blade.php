@extends('layouts.admin')

@section('title', 'Menu')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Content', ['label' => 'Menu']]" />
@endsection

@section('content')
    <x-admin.page-header title="Menu Navigasi" subtitle="Daftar tautan yang ditampilkan di storefront." />

    <div class="row g-3">
        @foreach ($menus as $menu)
            <div class="col-12 col-xl-4">
                <x-admin.card :title="$menu['label']" icon="list" :subtitle="count($menu['items']).' item'" class="h-100">
                    <form method="POST" action="{{ route('admin.menus.update', ['key' => $menu['key']]) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="key" value="{{ $menu['key'] }}">

                        <div class="table-responsive">
                            <table class="table admin-table mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">Label</th>
                                        <th scope="col">URL</th>
                                        <th scope="col" style="width: 110px">Target</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $rows = $menu['items'] ?: [['label' => '', 'url' => '', 'target' => '_self']]; @endphp
                                    @foreach ($rows as $index => $item)
                                        <tr>
                                            <td>
                                                <input
                                                    type="text"
                                                    class="form-control form-control-sm"
                                                    name="items[{{ $index }}][label]"
                                                    value="{{ $item['label'] }}"
                                                    maxlength="80"
                                                    required
                                                    aria-label="Label item {{ $index + 1 }}"
                                                >
                                            </td>
                                            <td>
                                                <input
                                                    type="text"
                                                    class="form-control form-control-sm"
                                                    name="items[{{ $index }}][url]"
                                                    value="{{ $item['url'] }}"
                                                    maxlength="500"
                                                    required
                                                    aria-label="URL item {{ $index + 1 }}"
                                                >
                                            </td>
                                            <td>
                                                <select class="form-select form-select-sm" name="items[{{ $index }}][target]" aria-label="Target item {{ $index + 1 }}">
                                                    <option value="_self" @selected($item['target'] === '_self')>Sama</option>
                                                    <option value="_blank" @selected($item['target'] === '_blank')>Baru</option>
                                                </select>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <p class="small text-secondary mt-2 mb-3">
                            Baris kosong di akhir akan diabaikan. Kosongkan semua baris untuk menghapus menu ini.
                        </p>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-sm btn-primary">Simpan {{ $menu['label'] }}</button>
                        </div>
                    </form>
                </x-admin.card>
            </div>
        @endforeach
    </div>
@endsection
