{{-- Perluasan AI: 4 otomatisasi dengan fallback lokal (tanpa kredensial tetap jalan). --}}
<x-admin.card title="Otomatisasi AI Baru" icon="sparkles" class="mt-3" subtitle="Semua berjalan lokal bila provider AI belum dikonfigurasi.">
    <div class="row g-3">
        <div class="col-md-6">
            <div class="d-flex gap-2 align-items-start">
                <x-admin.icon name="pencil" :size="16" class="mt-1" />
                <div>
                    <p class="mb-0 fw-semibold small">Deskripsi Produk + Judul SEO</p>
                    <small class="text-secondary">Dari nama/spesifikasi. Coba via tombol “Jalankan Tugas” dengan tugas “Deskripsi Produk”.</small>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="d-flex gap-2 align-items-start">
                <x-admin.icon name="message-circle" :size="16" class="mt-1" />
                <div>
                    <p class="mb-0 fw-semibold small">Saran Balasan Chat</p>
                    <small class="text-secondary">Tiga opsi dari konteks percakapan + pesanan terakhir (tugas “Saran Balasan Chat”).</small>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="d-flex gap-2 align-items-start">
                <x-admin.icon name="star" :size="16" class="mt-1" />
                <div>
                    <p class="mb-0 fw-semibold small">Ringkasan Ulasan</p>
                    <small class="text-secondary">Pro/kontra + skor agregat dari teks ulasan (tugas “Ringkasan Ulasan”).</small>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="d-flex gap-2 align-items-start">
                <x-admin.icon name="search" :size="16" class="mt-1" />
                <div>
                    <p class="mb-0 fw-semibold small">Pencarian Cerdas</p>
                    <small class="text-secondary">Koreksi ejaan ID + ekspansi sinonim, murni lokal (tugas “Pencarian Cerdas”).</small>
                </div>
            </div>
        </div>
    </div>
    <x-admin.alert type="info" class="mt-3 mb-0" title="Mode fallback aktif bila AI mati">
        Tanpa kredensial provider, hasil dibuat dari template Bahasa Indonesia deterministik — tidak ada error ke pengguna.
    </x-admin.alert>
</x-admin.card>
