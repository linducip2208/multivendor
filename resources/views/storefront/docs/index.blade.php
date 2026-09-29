@extends('layouts.storefront')

@section('content')
    @php
        $guides = [
            [
                'icon' => 'search',
                'title' => 'Mencari produk',
                'text' => 'Gunakan kolom pencarian di bagian atas halaman atau gunakan filter kategori, merek, toko, dan rentang harga. Filter bisa disalin lewat tautan sehingga pengaturan yang sama bisa dibuka kembali di perangkat lain.',
            ],
            [
                'icon' => 'cart',
                'title' => 'Menyusun keranjang',
                'text' => 'Tambahkan produk satu per satu atau dari daftar. Keranjang dikelompokkan otomatis per toko, jadi ongkos kirim dan status-packed setiap penjualan dihitung terpisah.',
            ],
            [
                'icon' => 'wallet',
                'title' => 'Checkout dan pembayaran',
                'text' => 'Pilih alamat pengiriman, tentukan layanan pengiriman untuk setiap toko, lalu pilih metode pembayaran yang tersedia. Kode kupon dapat diisi pada tahap ini.',
            ],
            [
                'icon' => 'package',
                'title' => 'Lacak pesanan',
                'text' => 'Nomor pesanan dapat dilacak kapan saja melalui halaman lacak pesanan. Setiap perubahan status akan tercatat beserta waktu dan catatan dari penjual.',
            ],
            [
                'icon' => 'refresh',
                'title' => 'Retur dan refund',
                'text' => 'Pengajuan retur dapat dibuat dari halaman detail pesanan selama produk masih berada dalam masa retur. Penjual akan meninjau dan memberi tahu hasilnya lewat halaman yang sama.',
            ],
            [
                'icon' => 'headset',
                'title' => 'Butuh bantuan',
                'text' => 'Buka tiket dukungan dari menu akun bila pesanan bermasalah. Sertakan nomor pesanan agar tim dapat memeriksa lebih cepat.',
            ],
        ];
    @endphp

    <div class="sf-container">
        <x-storefront.breadcrumb :items="$breadcrumbItems" />
    </div>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-docs-title">
        <div class="sf-container" style="max-width:860px">
            <h1 class="sf-section-head__title" id="sf-docs-title">Panduan Belanja</h1>
            <p class="sf-muted" style="max-width:62ch">
                Semua yang perlu Anda ketahui sebelum, saat, dan sesudah menyelesaikan pesanan di platform ini.
            </p>
        </div>
    </section>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-docs-steps">
        <div class="sf-container" style="max-width:860px">
            <h2 class="sf-section-head__title" id="sf-docs-steps" style="font-size:1.3rem">Langkah demi langkah</h2>

            <div class="sf-grid" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr));margin-top:16px">
                @foreach ($guides as $index => $guide)
                    <article class="sf-panel">
                        <span class="sf-row" style="gap:12px;align-items:flex-start">
                            <span class="sf-trust__icon" aria-hidden="true">
                                <x-storefront.icon :name="$guide['icon']" :size="19" />
                            </span>
                            <span style="min-width:0">
                                <span class="sf-row" style="gap:8px">
                                    <span class="sf-step__num" aria-hidden="true">{{ $index + 1 }}</span>
                                    <span class="sf-bold" style="color:var(--sf-text)">{{ $guide['title'] }}</span>
                                </span>
                                <span class="sf-small sf-muted" style="display:block;margin-top:6px">{{ $guide['text'] }}</span>
                            </span>
                        </span>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-docs-faq">
        <div class="sf-container" style="max-width:860px">
            <h2 class="sf-section-head__title" id="sf-docs-faq" style="font-size:1.3rem">Pertanyaan umum</h2>

            <div data-sf-tabs style="margin-top:16px">
                <div class="sf-tabs" role="tablist" aria-label="Kategori pertanyaan umum">
                    <button type="button" class="sf-tab" role="tab" id="docs-tab-order" aria-controls="docs-panel-order" aria-selected="true">Pesanan</button>
                    <button type="button" class="sf-tab" role="tab" id="docs-tab-pay" aria-controls="docs-panel-pay" aria-selected="false" tabindex="-1">Pembayaran</button>
                    <button type="button" class="sf-tab" role="tab" id="docs-tab-return" aria-controls="docs-panel-return" aria-selected="false" tabindex="-1">Retur</button>
                    <button type="button" class="sf-tab" role="tab" id="docs-tab-account" aria-controls="docs-panel-account" aria-selected="false" tabindex="-1">Akun</button>
                </div>

                <div class="sf-tabpanel" role="tabpanel" id="docs-panel-order" aria-labelledby="docs-tab-order" tabindex="0">
                    <div class="sf-prose sf-small">
                        <h3>Kenapa pesanan saya terbagi menjadi beberapa nomor?</h3>
                        <p>
                            Setiap toko pada marketplace diproses sebagai penjualan terpisah agar status dan
                            pengiriman tetap jelas. Seluruh nomor tersebut menjadi satu grup pembayaran dengan
                            tagihan gabungan di checkout.
                        </p>
                        <h3>Berapa lama pesanan diproses?</h3>
                        <p>
                            Proses pengemasan dimulai setelah pembayaran terkonfirmasi. Estimasi tiba dihitung
                            berdasarkan estimasi kurir yang dipakai pada tahap checkout.
                        </p>
                    </div>
                </div>

                <div class="sf-tabpanel" role="tabpanel" id="docs-panel-pay" aria-labelledby="docs-tab-pay" tabindex="0" hidden>
                    <div class="sf-prose sf-small">
                        <h3>Metode pembayaran apa saja yang tersedia?</h3>
                        <p>
                            Metode pembayaran yang tampil pada halaman checkout adalah gateway yang sedang aktif
                            di platform ini. Bila tidak ada metode yang tampil, administrator belum mengaktifkan
                            payment gateway.
                        </p>
                        <h3>Apakah aman membayar di sini?</h3>
                        <p>
                            Seluruh transaksi diproses melalui payment gateway resmi. Detail kartu atau data
                            pembayaran tidak pernah disimpan oleh platform ini.
                        </p>
                    </div>
                </div>

                <div class="sf-tabpanel" role="tabpanel" id="docs-panel-return" aria-labelledby="docs-tab-return" tabindex="0" hidden>
                    <div class="sf-prose sf-small">
                        <h3>Bagaimana cara mengajukan retur?</h3>
                        <p>
                            Buka halaman detail pesanan, pilih item yang akan diretur, lalu isi alasan pada
                            formulir pengajuan. Penjual meninjau permintaan tersebut dan hasilnya tampil di
                            halaman yang sama.
                        </p>
                        <p>
                            Ketentuan lengkap dapat dibaca pada
                            <a href="{{ route('page.return') }}">kebijakan retur</a>.
                        </p>
                    </div>
                </div>

                <div class="sf-tabpanel" role="tabpanel" id="docs-panel-account" aria-labelledby="docs-tab-account" tabindex="0" hidden>
                    <div class="sf-prose sf-small">
                        <h3>Bagaimana cara mengubah data akun?</h3>
                        <p>
                            Data nama dan nomor telepon dapat diperbarui pada halaman profil. Perubahan kata sandi
                            dilakukan pada halaman keamanan akun.
                        </p>
                        <h3>Bagaimana cara menyimpan alamat?</h3>
                        <p>
                            Alamat disimpan pada halaman alamat di area akun dan dapat dipilih kembali saat
                            checkout. Setiap akun dapat memiliki lebih dari satu alamat.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="sf-section sf-section--subtle" aria-labelledby="sf-docs-help">
        <div class="sf-container" style="max-width:860px">
            <div class="sf-panel sf-row sf-row--between sf-row--wrap" style="gap:16px">
                <div style="min-width:0;flex:1 1 260px">
                    <h2 class="sf-mb-0" id="sf-docs-help" style="font-size:1.15rem">Masih butuh bantuan?</h2>
                    <p class="sf-small sf-muted sf-mb-0">
                        Tim dukungan dan penjual dapat membantu pertanyaan seputar pesanan, pembayaran, dan retur.
                    </p>
                </div>
                <div class="sf-row sf-row--wrap" style="gap:8px">
                    <a href="{{ route('track-order') }}" class="sf-btn sf-btn--outline sf-btn--sm">
                        <x-storefront.icon name="package" :size="15" /> Lacak pesanan
                    </a>
                    <a href="{{ route('tickets.create') }}" class="sf-btn sf-btn--primary sf-btn--sm">
                        <x-storefront.icon name="headset" :size="15" /> Buka tiket
                    </a>
                </div>
            </div>

            @if (app()->environment(['local', 'testing']))
                <x-storefront.alert type="info" title="Lingkungan pengembangan" style="margin-top:16px">
                    Halaman ini sedang dilihat pada lingkungan pengembangan. Jangan memasukkan kredensial nyata
                    di sini.
                </x-storefront.alert>
            @endif
        </div>
    </section>
@endsection
