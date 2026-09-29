@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Asisten AI')
@section('subtitle', $provider ? 'Penyedia aktif: '.$provider : 'Belum ada penyedia AI aktif')

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Asisten AI'],
])

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl">
            <x-admin.stat label="Permintaan 30 hari" :value="$calls" icon="bot" color="primary" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Estimasi biaya" :value="'$'.number_format($cost, 4, ',', '.')" icon="wallet" color="info" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Kuota transaksi" :value="$quota > 0 ? Currency::number($quota) : 'Tak terbatas'" icon="target" color="success" />
        </div>
        <div class="col-12 col-xl">
            <x-admin.stat
                label="Penyedia"
                :value="$provider ?? 'Belum diatur'"
                icon="server"
                color="warning"
            />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            @if ($result)
                <x-admin.card :title="'Hasil: '.$result['label']" icon="sparkles" class="mb-3">
                    <p id="ai-output" class="mb-0" style="white-space: pre-line;">{{ $result['content'] }}</p>
                    <x-slot:footer>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-copy-target="#ai-output">
                            <x-admin.icon name="copy" :size="14" class="me-1" />
                            <span>Salin</span>
                        </button>
                    </x-slot:footer>
                </x-admin.card>
            @endif

            <x-admin.card title="Buat konten" icon="sparkles">
                <form method="POST" action="{{ route('vendor.ai.generate') }}">
                    @csrf

                    <x-admin.form-field
                        name="feature"
                        label="Jenis konten"
                        type="select"
                        required
                        :value="old('feature', $feature)"
                        :options="$features"
                    />

                    <x-admin.form-field
                        name="product_id"
                        label="Produk (untuk deskripsi produk)"
                        type="number"
                        :min="1"
                        :value="old('product_id')"
                        help="Isi ID produk dari halaman produk. Kosongkan jika tidak relevan."
                    />

                    <x-admin.form-field
                        name="order_id"
                        label="Nomor pesanan (untuk balasan pelanggan)"
                        type="number"
                        :min="1"
                        :value="old('order_id')"
                    />

                    <x-admin.form-field
                        name="message"
                        label="Pesan pelanggan"
                        type="textarea"
                        :rows="4"
                        :value="old('message')"
                        help="Tempel pesan asli pelanggan agar jawaban sesuai konteks."
                    />

                    <div class="row g-2">
                        <div class="col-12 col-md-6">
                            <x-admin.form-field name="tone" label="Gaya bahasa" type="select" :value="old('tone', 'profesional')" :options="[
                                'profesional' => 'Profesional',
                                'ramah' => 'Ramah',
                                'sopan' => 'Sopan',
                                'formal' => 'Formal',
                            ]" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-admin.form-field name="length" label="Panjang" type="select" :value="old('length', 'menengah')" :options="[
                                'pendek' => 'Pendek',
                                'menengah' => 'Menengah',
                                'panjang' => 'Panjang',
                            ]" />
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <x-admin.icon name="sparkles" :size="16" class="me-1" />
                            <span>Buat</span>
                        </button>
                    </div>
                </form>
            </x-admin.card>
        </div>

        <div class="col-12 col-xl-4">
            @includeIf('vendor.ai._expansion')

            <x-admin.card title="Pemakaian fitur" icon="activity">
                <x-admin.table dense>
                    <x-slot:table>
                        \App\Support\TableBuilder::make()
                            ->columns([
                                'feature' => ['label' => 'Fitur'],
                                'calls' => ['label' => 'Panggilan', 'align' => 'end'],
                                'cost' => ['label' => 'Biaya', 'align' => 'end'],
                            ])
                            ->rows(
                                collect($usage)->map(fn (array $row, string $feature) => [
                                    'feature' => '<span class="fw-medium">'.e($features[$feature] ?? $feature).'</span>',
                                    'calls' => e(Currency::number($row['calls'])),
                                    'cost' => '<span class="text-secondary small">'.e(number_format($row['cost'], 4, ',', '.')).'</span>',
                                ])->all()
                            )
                            ->empty('Belum ada pemakaian AI pada 30 hari terakhir.')
                    </x-slot:table>
                </x-admin.table>
            </x-admin.card>

            <x-admin.alert type="info" class="mt-3" title="Data aman">
                Setiap permintaan hanya menerima data toko Anda sendiri sebagai konteks.
                Riwayat pesanan pelanggan lain tidak pernah dikirim ke penyedia AI.
            </x-admin.alert>
        </div>
    </div>
@endsection
