@extends('layouts.admin')

@section('title', 'Pembaruan Software')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Sistem', ['label' => 'Pembaruan']]" />
@endsection

@section('content')
    <x-admin.page-header title="Pembaruan Software" subtitle="Versi terpasang dan hasil pemeriksaan terakhir." />

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <x-admin.card title="Versi Terpasang" icon="package" class="mb-3">
                <dl class="row small mb-0">
                    <dt class="col-6 text-secondary">Versi aplikasi</dt>
                    <dd class="col-6 text-end fw-semibold">{{ $currentVersion }}</dd>
                    <dt class="col-6 text-secondary">Laravel</dt>
                    <dd class="col-6 text-end">{{ $laravel }}</dd>
                    <dt class="col-6 text-secondary">PHP</dt>
                    <dd class="col-6 text-end">{{ $php }}</dd>
                    <dt class="col-6 text-secondary">Pemeriksaan terakhir</dt>
                    <dd class="col-6 text-end">{{ $lastUpdate }}</dd>
                </dl>
            </x-admin.card>

            <x-admin.alert type="info" :dismissible="false" title="Cara memperbarui" icon="info">
                Jalankan <code>composer update</code> lalu <code>php artisan migrate --force</code> di server. Halaman ini hanya mencatat waktu pemeriksaan, bukan mengunduh apa pun.
            </x-admin.alert>
        </div>

        <div class="col-12 col-lg-5">
            <x-admin.card title="Periksa Pembaruan" icon="refresh" class="h-100">
                <p class="small text-secondary">Mencatat waktu pemeriksaan terakhir pada pengaturan sistem.</p>
                <form method="POST" action="{{ route('admin.system.software-update') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary w-100">Periksa Sekarang</button>
                </form>
            </x-admin.card>
        </div>
    </div>
@endsection
