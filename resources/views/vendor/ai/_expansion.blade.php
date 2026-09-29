{{-- Perluasan AI vendor: fallback lokal bila provider belum dikonfigurasi. --}}
<x-admin.card title="Fitur AI Baru" icon="sparkles" class="mb-3">
    <ul class="mb-0 small text-secondary ps-3">
        <li><span class="fw-semibold text-body">Ringkasan ulasan</span> — pro/kontra + skor agregat per produk (pilih “Ringkasan ulasan”, isi ID produk).</li>
        <li><span class="fw-semibold text-body">Saran balasan chat</span> — 3 opsi dari pesan + pesanan terakhir (pilih “Balasan pelanggan”).</li>
        <li><span class="fw-semibold text-body">Deskripsi produk + judul SEO</span> — dari data produk (pilih “Deskripsi produk”).</li>
    </ul>
    <x-admin.alert type="info" class="mt-3 mb-0" title="Tetap jalan tanpa provider AI">
        Bila belum ada penyedia AI aktif, hasil dibuat otomatis dari template Bahasa Indonesia — tidak ada error.
    </x-admin.alert>
</x-admin.card>
