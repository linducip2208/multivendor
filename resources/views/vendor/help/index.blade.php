@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Bantuan')
@section('subtitle', 'Panduan singkat penjual dan kontak dukungan')

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Bantuan'],
])

@section('content')
    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <x-admin.card title="Pertanyaan umum" icon="help-circle" :padding="false">
                <div class="accordion accordion-flush" id="vendor-help">
                    @foreach ([
                        [
                            'q' => 'Bagaimana cara memproses pesanan yang masuk?',
                            'a' => 'Buka menu Pesanan, pilih pesanan berstatus lunas, lalu tekan Kirim. Isi kurir dan nomor resi. Status pesanan dan stok ikut diperbarui dalam satu transaksi.',
                        ],
                        [
                            'q' => 'Kapan saldo toko saya bertambah?',
                            'a' => 'Saldo bertambah ketika pesanan berstatus Diterima atau Selesai. Untuk pembayaran di tempat (COD), uang dipegang kurir lebih dulu dan baru direkonsiliasi ke platform.',
                        ],
                        [
                            'q' => 'Bagaimana cara menarik dana?',
                            'a' => 'Buka Keuangan, pilih tab Pencairan, lalu ajukan pencairan dengan nominal minimal Rp10.000. Permintaan menunggu persetujuan admin platform.',
                        ],
                        [
                            'q' => 'Mengapa produk saya perlu ditinjau ulang setelah diubah?',
                            'a' => 'Perubahan pada field komersial (harga, nama, deskripsi) memengaruhi tampilan etalase sehingga harus ditinjau admin sebelum tayang.',
                        ],
                        [
                            'q' => 'Bagaimana cara melakukan stock opname?',
                            'a' => 'Gunakan halaman Inventori. Setiap penyesuaian memerlukan alasan dan tercatat pada ledger pergerakan stok beserta saldo akhirnya.',
                        ],
                        [
                            'q' => 'Apa yang terjadi bila kuota paket habis?',
                            'a' => 'Toko tetap dapat beroperasi untuk pesanan yang sedang berjalan, namun Anda tidak dapat menambah produk atau anggota tim baru sampai paket ditingkatkan.',
                        ],
                        [
                            'q' => 'Bagaimana cara mendelegasikan akses ke tim?',
                            'a' => 'Tambahkan anggota tim di halaman Anggota Tim. Setiap anggota memakai akun sendiri sehingga aktivitas dapat diaudit.',
                        ],
                    ] as $index => $entry)
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="vendor-help-heading-{{ $index }}">
                                <button class="accordion-button {{ $index === 0 ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#vendor-help-body-{{ $index }}" aria-expanded="{{ $index === 0 ? 'true' : 'false' }}" aria-controls="vendor-help-body-{{ $index }}">
                                    {{ $entry['q'] }}
                                </button>
                            </h2>
                            <div id="vendor-help-body-{{ $index }}" class="accordion-collapse collapse {{ $index === 0 ? 'show' : '' }}" aria-labelledby="vendor-help-heading-{{ $index }}" data-bs-parent="#vendor-help">
                                <div class="accordion-body text-secondary">{{ $entry['a'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-admin.card>
        </div>

        <div class="col-12 col-xl-4">
            <x-admin.card title="Hubungi dukungan" icon="life-buoy">
                <p class="text-secondary small">
                    Buat tiket untuk masalah yang memerlukan investigative lebih lanjut.
                    Untuk pertanyaan singkat, chat lebih cepat.
                </p>

                <div class="d-grid gap-2">
                    <a href="{{ route('vendor.tickets.index') }}" class="btn btn-primary">
                        <x-admin.icon name="life-buoy" :size="16" class="me-1" />
                        <span>Buat tiket</span>
                    </a>
                    <a href="{{ route('vendor.chat.inbox') }}" class="btn btn-outline-secondary">
                        <x-admin.icon name="message-circle" :size="16" class="me-1" />
                        <span>Buka chat</span>
                    </a>
                </div>
            </x-admin.card>

            <x-admin.card title="Pintasan" icon="link" class="mt-3">
                <div class="d-grid gap-2">
                    <a href="{{ route('vendor.inventory.index') }}" class="btn btn-outline-secondary justify-content-start">
                        <x-admin.icon name="box" :size="16" class="me-2" />
                        <span>Kelola inventori</span>
                    </a>
                    <a href="{{ route('vendor.fulfillment.index') }}" class="btn btn-outline-secondary justify-content-start">
                        <x-admin.icon name="truck" :size="16" class="me-2" />
                        <span>Antrean pengiriman</span>
                    </a>
                    <a href="{{ route('vendor.analytics.index') }}" class="btn btn-outline-secondary justify-content-start">
                        <x-admin.icon name="chart-line" :size="16" class="me-2" />
                        <span>Buka analitik</span>
                    </a>
                    <a href="{{ route('vendor.subscription.index') }}" class="btn btn-outline-secondary justify-content-start">
                        <x-admin.icon name="layers" :size="16" class="me-2" />
                        <span>Kelola langganan</span>
                    </a>
                </div>
            </x-admin.card>
        </div>
    </div>
@endsection
