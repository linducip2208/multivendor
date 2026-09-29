@extends('layouts.admin')

@section('title', 'Log Aplikasi')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Developers', ['label' => 'Logs']]" />
@endsection

@section('content')
    <x-admin.page-header title="Log Aplikasi" subtitle="Penelusuran isi berkas log untuk debugging." />

    <x-admin.alert type="warning" :title="'Hanya berkas .log yang tersedia'" icon="file-code">
        Halaman ini membaca berkas di direktori log. Gunakan penyaring level untuk memperkecil hasil. Data log dapat berisi informasi sensitif, jangan dibagikan tanpa ditinjau.
    </x-admin.alert>

    <x-admin.card class="mb-3" title="Filter" icon="filter">
        <x-admin.filters
            :action="route('admin.logs.index')"
            :filters="[['name' => 'level', 'label' => 'Level', 'type' => 'select', 'options' => array_merge(['' => 'Semua level'], array_combine($levels, $levels))]]
        />
    </x-admin.card>

    <div class="row g-3">
        <div class="col-lg-3">
            <x-admin.card title="Berkas" icon="file-code" flush>
                <div class="list-group list-group-flush">
                    @forelse ($all_files as $file)
                        <a
                            href="{{ route('admin.logs.index', array_filter(['file' => $file['name'], 'level' => request()->query('level')])) }}"
                            class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ ($selected['name'] ?? null) === $file['name'] ? 'active' : '' }}"
                            @if (($selected['name'] ?? null) === $file['name']) aria-current="true" @endif
                        >
                            <span class="text-truncate">{{ $file['name'] }}</span>
                            <span class="badge bg-secondary-lt text-secondary ms-2">{{ number_format($file['bytes'] / 1024, 1, ',', '.') }} KB</span>
                        </a>
                    @empty
                        <div class="list-group-item text-secondary small">Belum ada berkas log.</div>
                    @endforelse
                </div>
            </x-admin.card>
        </div>

        <div class="col-lg-9">
            <x-admin.card :title="$selected['name'] ?? 'Tidak ada berkas'" :subtitle="$selected !== null ? number_format($selected['bytes'] / 1024, 1, ',', '.').' KB · diubah '.$selected['modified'] : null" icon="file-code">
                <pre class="small mb-0" style="max-height: 620px; overflow-y: auto; white-space: pre-wrap; word-break: break-word;">@forelse ($lines as $line){{ $line }}
@empty
Tidak ada baris yang cocok.
@endforelse</pre>
            </x-admin.card>
        </div>
    </div>

    <div class="mt-3">
        <x-admin.pagination :paginator="\App\Support\AdminPaginator::fromArray($pagination, $pagination['total'], $pagination['per_page'], $pagination['current_page'])" size="sm" />
    </div>
@endsection
