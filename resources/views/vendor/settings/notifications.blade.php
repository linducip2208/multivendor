@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Notifikasi')
@section('subtitle', 'Ke mana dan kapan toko Anda diberi tahu')

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Pengaturan', 'href' => route('vendor.settings.index')],
    ['label' => 'Notifikasi'],
])

@section('content')
    <x-admin.tabs class="mb-3" :tabs="[
        ['label' => 'Profil toko', 'href' => route('vendor.settings.index'), 'icon' => 'store'],
        ['label' => 'Pengiriman', 'href' => route('vendor.shipping.index'), 'icon' => 'truck'],
        ['label' => 'Notifikasi', 'href' => route('vendor.notifications.index'), 'active' => true, 'icon' => 'bell'],
        ['label' => 'Keamanan', 'href' => route('vendor.security.index'), 'icon' => 'shield'],
    ]" />

    <form method="POST" action="{{ route('vendor.notifications.update') }}">
        @csrf
        @method('PUT')

        <x-admin.card title="Matriks preferensi" icon="bell" :padding="false">
            <x-admin.table>
                <x-slot:table>
                        \App\Support\TableBuilder::make()
                            ->columns([
                                'category' => ['label' => 'Peristiwa', 'width' => '30%'],
                                'channels' => ['label' => 'Kanal', 'width' => '46%'],
                                'destination' => ['label' => 'Tujuan', 'align' => 'end'],
                            ])
                            ->rows(
                                collect($matrix)->map(function (array $entry, string $category): array {
                                    $toggles = '';

                                    foreach ($entry['channels'] as $channel => $cell) {
                                        $name = 'preferences['.$category.']['.$channel.']';
                                        $id = 'pref-'.preg_replace('/[^a-z0-9]+/i', '-', $category.'-'.$channel);

                                        $toggles .= '<div class="form-check form-check-inline m-0" title="'.e($cell['label']).'">'
                                            .'<input class="form-check-input" type="checkbox" id="'.$id.'" name="'.$name.'[enabled]" value="1" @checked($cell['enabled']) >'
                                            .'<label class="form-check-label small" for="'.$id.'">'.e($cell['label']).'</label></div>';
                                    }

                                    $destinations = '';

                                    foreach ($entry['channels'] as $channel => $cell) {
                                        if (in_array($channel, ['email', 'sms', 'whatsapp'], true)) {
                                            $name = 'preferences['.$category.']['.$channel.'][destination]';
                                            $id = 'dest-'.preg_replace('/[^a-z0-9]+/i', '-', $category.'-'.$channel);

                                            $destinations .= '<div class="input-group input-group-sm mb-1">'
                                                .'<span class="input-group-text">'.e(strtoupper($channel)).'</span>'
                                                .'<input class="form-control" type="text" id="'.$id.'" name="'.$name.'" value="'.e((string) $cell['destination']).'" placeholder="Kosongkan untuk memakai kontak toko">'
                                                .'</div>';
                                        }
                                    }

                                    return [
                                        'category' => '<span class="fw-medium">'.e($entry['label']).'</span><span class="d-block text-secondary small font-monospace">'.e($category).'</span>',
                                        'channels' => $toggles,
                                        'destination' => $destinations !== '' ? $destinations : '<span class="text-secondary small">—</span>',
                                    ];
                                })->all()
                            )
                            ->empty('Tidak ada kategori notifikasi.')
                    </x-slot:table>
                </x-slot:table>
            </x-admin.table>

            <x-slot:footer>
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <x-admin.icon name="check" :size="16" class="me-1" />
                        <span>Simpan preferensi</span>
                    </button>
                </div>
            </x-slot:footer>
        </x-admin.card>
    </form>

    <x-admin.alert type="info" class="mt-3">
        Notifikasi kritis (pembayaran diterima, pesanan dikirim) tetap dikirim ke email akun pemilik toko
        dan tidak dapat dimatikan.
    </x-admin.alert>
@endsection
