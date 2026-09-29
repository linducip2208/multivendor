@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Tarif pengiriman')
@section('subtitle', 'Pilih kurir dan atur biaya ongkir toko Anda')

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Pengaturan', 'href' => route('vendor.settings.index')],
    ['label' => 'Pengiriman'],
])

@section('content')
    <x-admin.tabs class="mb-3" :tabs="[
        ['label' => 'Profil toko', 'href' => route('vendor.settings.index'), 'icon' => 'store'],
        ['label' => 'Pengiriman', 'href' => route('vendor.shipping.index'), 'active' => true, 'icon' => 'truck'],
        ['label' => 'Notifikasi', 'href' => route('vendor.notifications.index'), 'icon' => 'bell'],
        ['label' => 'Keamanan', 'href' => route('vendor.security.index'), 'icon' => 'shield'],
    ]" />

    <form method="POST" action="{{ route('vendor.shipping.update') }}">
        @csrf
        @method('PUT')

        <x-admin.card title="Kurir tersedia" icon="truck" :padding="false">
            <x-admin.table>
                <x-slot:table>
                    \App\Support\TableBuilder::make()
                        ->columns([
                            'method' => ['label' => 'Kurir'],
                            'enabled' => ['label' => 'Aktif', 'width' => '110px'],
                            'cost' => ['label' => 'Biaya', 'align' => 'end'],
                            'duration' => ['label' => 'Estimasi', 'align' => 'end'],
                        ])
                        ->rows(
                            $methods->map(fn ($method) => [
                                'method' => '<label class="form-check d-flex align-items-center gap-2 mb-0 cursor-pointer" for="rate-'.(int) $method['id'].'">'
                                    .'<span class="fw-medium d-block">'.e($method['name']).'</span>'
                                    .'<span class="text-secondary small font-monospace">'.e($method['code']).'</span></label>',
                                'enabled' => '<input class="form-check-input" type="checkbox" id="rate-'.(int) $method['id'].'" name="rates['.(int) $method['id'].'][enabled]" value="1" @checked($method[\'enabled\']) >',
                                'cost' => '<div class="input-group input-group-sm" style="max-width: 190px; margin-left: auto;">'
                                    .'<span class="input-group-text">'.$currency['symbol'].'</span>'
                                    .'<input class="form-control" type="number" step="0.01" min="0" name="rates['.(int) $method['id'].'][cost]" value="'.e($method[\'cost\']->toDecimal()).'" aria-label="Biaya '.$currency['symbol'].' untuk '.$method['name'].'">'
                                    .'</div>',
                                'duration' => e($method['estimated_days'] > 0 ? $method['estimated_days'].' hari' : '—'),
                            ])->all()
                        )
                        ->empty('Belum ada kurir yang dikonfigurasi oleh admin platform.')
                </x-slot:table>
            </x-admin.table>

            <x-slot:footer>
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <x-admin.icon name="check" :size="16" class="me-1" />
                        <span>Simpan tarif</span>
                    </button>
                </div>
            </x-slot:footer>
        </x-admin.card>
    </form>

    <x-admin.alert type="info" class="mt-3">
        Kupon gratis ongkir tetap berlaku dan tidak bergantung pada tarif di halaman ini.
    </x-admin.alert>
@endsection
