@extends('layouts.vendor')
@section('title', 'Detail Produk')
@section('content')
<div class="mb-4"><a href="{{ route('vendor.products.index') }}" class="small"><x-admin.icon name="arrow-left" :size="16" class="me-1" />Kembali</a><h4 class="fw-bold mt-2">{{ $product->name }}</h4></div>
<div class="row g-4">
    <div class="col-md-8"><x-admin.card :padding="false"><div class="card-body p-4">
        @if($product->thumbnail)<div class="text-center mb-3"><img src="{{ url('img/'.$product->thumbnail) }}" class="rounded-4" style="max-width:100%;max-height:350px;object-fit:cover;"></div>@endif
        <div class="row g-3 small"><div class="col-md-6"><span class="text-muted">SKU:</span> {{ $product->sku ?? '-' }}</div><div class="col-md-6"><span class="text-muted">Kategori:</span> {{ $product->category?->name ?? '-' }}</div><div class="col-md-6"><span class="text-muted">Brand:</span> {{ $product->brand?->name ?? '-' }}</div><div class="col-md-6"><span class="text-muted">Tipe:</span> {{ $product->product_type }}</div><div class="col-md-6"><span class="text-muted">Satuan:</span> {{ $product->unit ?? 'pcs' }}</div><div class="col-md-6"><span class="text-muted">Jml. Min./Maks.:</span> {{ $product->min_qty }}/{{ $product->max_qty }}</div></div>
        <hr>
        <div class="d-flex gap-4 mb-3"><div><small class="text-muted">Harga</small><br><span class="fw-bold fs-5 text-success">Rp {{ number_format($product->price,0,',','.') }}</span></div><div><small class="text-muted">Diskon</small><br>Rp {{ number_format($product->special_price??0,0,',','.') }}</div><div><small class="text-muted">Stok</small><br>{{ $product->current_stock }}</div><div><small class="text-muted">Pajak</small><br>{{ $product->tax }}%</div></div>
        @if($product->description)<div class="mt-3"><small class="text-muted">Deskripsi</small><div class="lh-lg">{!! app(\App\Services\HtmlSanitizer::class)->sanitize($product->description) !!}</div></div>@endif
    </div></x-admin.card></div>
    <div class="col-md-4">
        <x-admin.card :padding="false" class="mb-3"><div class="card-body"><h6 class="fw-bold mb-3">Status</h6>
            @php $b=['pending'=>'warning','approved'=>'success','suspended'=>'danger']; @endphp
            <x-admin.badge :color="$b[$product->status] ?? 'secondary'" class="px-3 py-2" :text="ucfirst($product->status)" />
        </div></x-admin.card>
        @if(($product->variants?->count() ?? 0) > 0)
        <x-admin.card :padding="false"><div class="card-body"><h6 class="fw-bold mb-3">Varian ({{ $product->variants?->count() ?? 0 }})</h6>
            @foreach(($product->variants ?? []) as $v)<div class="border rounded-3 p-2 mb-2 small"><span class="fw-medium">{{ $v->variant }}</span> · Rp {{ number_format($v->price,0,',','.') }} · Stok: {{ $v->stock }}</div>@endforeach
        </div></x-admin.card>
        @endif
        @php
            $b2bRows = [];
            try { $b2bRows = app(\App\Services\B2b\B2bPricingService::class)->tierTableRows($product); } catch (\Throwable) {}
            $galeriDasarVendor = [];
            $koleksiVendor = collect();
            $panduanVendor = [];
            try { $galeriDasarVendor = $product->galeriDasar(); } catch (\Throwable) {}
            try { $koleksiVendor = $product->koleksiTematikAktif(); } catch (\Throwable) { $koleksiVendor = collect(); }
            try { $panduanVendor = $product->panduanKategori(); } catch (\Throwable) { $panduanVendor = []; }
        @endphp
        <x-admin.card :padding="false" class="mt-3"><div class="card-body"><h6 class="fw-bold mb-1">Harga Grosir (Tier)</h6>
            <p class="text-muted small mb-3">Tampil juga di halaman produk pembeli sebagai tabel tier. Kosong = harga ecer berlaku.</p>
            @if($b2bRows === [])
                <p class="text-muted small mb-0">Belum ada tier grosir untuk produk ini.</p>
            @else
                <div class="table-responsive"><table class="table table-sm table-bordered mb-0">
                    <thead><tr><th>Min. Qty</th><th class="text-end">Harga</th><th class="text-end">Hemat</th></tr></thead>
                    <tbody>
                    @foreach($b2bRows as $tier)
                        <tr><td><span class="fw-medium">{{ number_format($tier['min_qty'],0,',','.') }}+</span></td><td class="text-end fw-medium">Rp {{ number_format($tier['price'],0,',','.') }}</td><td class="text-end">@if($tier['hemat_pct'] !== null)<span class="badge bg-success-lt text-success rounded-pill">{{ number_format($tier['hemat_pct'],1,',','.') }}%</span>@else<span class="text-secondary">—</span>@endif</td></tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </div></x-admin.card>
        <div class="card mt-3">
            <div class="card-header"><h3 class="card-title">Gambar per Varian</h3></div>
            <div class="card-body">
                <p class="text-secondary small">Galeri halaman produk otomatis mengikuti varian yang dipilih pembeli. Varian tanpa gambar memakai foto utama produk.</p>
                @if(($product->variants?->count() ?? 0) > 0)
                    <div class="list-group list-group-flush">
                        @foreach(($product->variants ?? []) as $v)
                            @php
                                $urlVarian = [];
                                $adaGambar = false;
                                try { $urlVarian = $v->urlGaleri(); $adaGambar = $v->punyaGambarSendiri(); } catch (\Throwable) {}
                            @endphp
                            <div class="list-group-item px-0">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-medium">{{ $v->variant ?: 'Varian #'.$v->id }}</span>
                                    @if($adaGambar)
                                        <span class="badge bg-success-lt text-success">Gambar sendiri ({{ count($urlVarian) }})</span>
                                    @else
                                        <span class="badge bg-secondary-lt text-secondary">Fallback foto utama</span>
                                    @endif
                                </div>
                                @if($urlVarian !== [])
                                    <div class="d-flex gap-2 mt-2 flex-wrap">
                                        @foreach($urlVarian as $g)
                                            <img src="{{ $g }}" alt="" loading="lazy" class="rounded-3" style="width:56px;height:56px;object-fit:cover;">
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-secondary small mb-0">Belum ada varian. Galeri memakai foto utama produk.</p>
                @endif
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header"><h3 class="card-title">Lisensi Digital</h3></div>
            <div class="card-body">
                @if(($product->product_type ?? '') === 'digital')
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="text-secondary small">Berkas unduhan:</span>
                        @if(! empty($product->digital_file))
                            <span class="badge bg-success-lt text-success">Terunggah</span>
                        @else
                            <span class="badge bg-warning-lt text-warning">Belum ada berkas</span>
                        @endif
                    </div>
                    <p class="text-secondary small mb-0">Setiap pembelian otomatis mendapat kunci lisensi unik dengan batas unduh (bawaan 5x) dan riwayat unduhan. Pencabutan tersedia bila terjadi penyalahgunaan.</p>
                @else
                    <p class="text-secondary small mb-0">Produk fisik — lisensi digital tidak berlaku.</p>
                @endif
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header"><h3 class="card-title">Koleksi &amp; Panduan</h3></div>
            <div class="card-body">
                <div class="text-secondary small mb-1">Koleksi tematik yang sedang tayang:</div>
                @if($koleksiVendor->isNotEmpty())
                    <div class="d-flex gap-2 flex-wrap mb-3">
                        @foreach($koleksiVendor as $k)
                            <span class="badge bg-azure-lt text-azure">{{ $k->name }}</span>
                        @endforeach
                    </div>
                @else
                    <p class="text-secondary small">Belum masuk koleksi kurasi apa pun (mis. “Back to School”).</p>
                @endif
                <div class="text-secondary small mb-1">Panduan di halaman produk:</div>
                @if(! empty($panduanVendor['baris'] ?? []))
                    <p class="small mb-0"><span class="fw-medium">{{ $panduanVendor['judul'] ?? 'Panduan' }}</span> — {{ count($panduanVendor['baris']) }} baris tampil otomatis dari atribut produk.</p>
                @else
                    <p class="text-secondary small mb-0">Belum ada panduan (tambahkan atribut ukuran/nutrisi agar panduan tampil otomatis).</p>
                @endif
            </div>
        </div>
        <div class="mt-2"><a href="{{ route('vendor.products.edit', $product) }}" class="btn btn-outline-primary w-100"><x-admin.icon name="edit" :size="16" class="me-1" />Ubah Produk</a></div>
    </div>
</div>
@endsection
