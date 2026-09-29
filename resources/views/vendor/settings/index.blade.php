@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Pengaturan toko')
@section('subtitle', $shop->name)

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Pengaturan'],
])

@section('content')
    <x-admin.tabs class="mb-3" :tabs="[
        ['label' => 'Profil toko', 'href' => route('vendor.settings.index'), 'active' => true, 'icon' => 'store'],
        ['label' => 'Pengiriman', 'href' => route('vendor.shipping.index'), 'icon' => 'truck'],
        ['label' => 'Notifikasi', 'href' => route('vendor.notifications.index'), 'icon' => 'bell'],
        ['label' => 'Keamanan', 'href' => route('vendor.security.index'), 'icon' => 'shield'],
    ]" />

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <x-admin.card title="Identitas toko" icon="store">
                <form method="POST" action="{{ route('vendor.settings.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-12 col-md-8">
                            <x-admin.form-field name="name" label="Nama toko" required :value="$shop->name" />
                        </div>
                        <div class="col-12 col-md-4">
                            <x-admin.form-field name="postal_code" label="Kode pos" :value="$shop->postal_code" />
                        </div>
                    </div>

                    <x-admin.form-field name="description" label="Deskripsi toko" type="textarea" :rows="4" :value="$shop->description" help="Tampil di halaman toko dan pada hasil pencarian." />

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <x-admin.form-field name="email" label="Email toko" type="email" :value="$shop->email" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-admin.form-field name="phone" label="Telepon" type="tel" :value="$shop->phone" />
                        </div>
                    </div>

                    <x-admin.form-field name="address" label="Alamat" type="textarea" :rows="2" :value="$shop->address" />

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <x-admin.form-field name="city" label="Kota" :value="$shop->city" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-admin.form-field name="province" label="Provinsi" :value="$shop->province" />
                        </div>
                    </div>

                    <hr class="my-3" />
                    <h4 class="card-title mb-3">Rekening pencairan</h4>
                    <x-admin.alert type="warning" :dismissible="false">
                        Nomor rekening disimpan terenkripsi di server dan hanya ditampilkan empat digit terakhir.
                    </x-admin.alert>

                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <x-admin.form-field name="bank_name" label="Nama bank" :value="$shop->bank_name" />
                        </div>
                        <div class="col-12 col-md-4">
                            <x-admin.form-field name="bank_account_name" label="Atas nama" :value="$shop->bank_account_name" />
                        </div>
                        <div class="col-12 col-md-4">
                            <x-admin.form-field
                                name="bank_account_number"
                                label="Nomor rekening"
                                :value="$maskedAccount"
                                help="Tersimpan: {{ $maskedAccount ?: 'belum diisi' }}"
                                placeholder="{{ $maskedAccount }}"
                            />
                        </div>
                    </div>

                    <hr class="my-3" />
                    <h4 class="card-title mb-3">Mode Libur</h4>
                    <x-admin.form-field name="vacation_mode" label="Aktifkan mode libur" type="checkbox" :value="$shop->vacation_mode ? 1 : 0" />
                    <x-admin.form-field name="vacation_message" label="Pesan yang tampil" type="textarea" :rows="2" :value="$shop->vacation_message" />

                    <hr class="my-3" />
                    <h4 class="card-title mb-3">SEO</h4>
                    <x-admin.form-field name="meta_title" label="Judul meta" :value="$shop->meta_title" />
                    <x-admin.form-field name="meta_description" label="Deskripsi meta" type="textarea" :rows="2" :value="$shop->meta_description" />

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('vendor.settings.index') }}" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary">
                            <x-admin.icon name="check" :size="16" class="me-1" />
                            <span>Simpan</span>
                        </button>
                    </div>
                </form>
            </x-admin.card>
        </div>

        <div class="col-12 col-xl-4">
            <x-admin.card title="Ringkasan" icon="info">
                <dl class="row mb-0 small">
                    <dt class="col-6 text-secondary fw-normal">Status</dt>
                    <dd class="col-7">{{ $__status($shop->status) }}</dd>
                    <dt class="col-6 text-secondary fw-normal">Komisi</dt>
                    <dd class="col-7">{{ $shop->commission_type === 'percentage' ? rtrim(rtrim((string) $shop->commission_value, '0'), '.') . '%' : Currency::format($shop->commission_value) }}</dd>
                    <dt class="col-6 text-secondary fw-normal">Rekening</dt>
                    <dd class="col-7 font-monospace">{{ $maskedAccount ?: '—' }}</dd>
                    <dt class="col-6 text-secondary fw-normal">Dibuat</dt>
                    <dd class="col-7">{{ $shop->created_at?->format('d M Y') }}</dd>
                </dl>
            </x-admin.card>

            <x-admin.card title="Tautan cepat" icon="link" class="mt-3">
                <div class="d-grid gap-2">
                    <a href="{{ route('vendor.shipping.index') }}" class="btn btn-outline-secondary justify-content-start">
                        <x-admin.icon name="truck" :size="16" class="me-2" />
                        <span>Tarif pengiriman</span>
                    </a>
                    <a href="{{ route('vendor.notifications.index') }}" class="btn btn-outline-secondary justify-content-start">
                        <x-admin.icon name="bell" :size="16" class="me-2" />
                        <span>Preferensi notifikasi</span>
                    </a>
                    <a href="{{ route('vendor.security.index') }}" class="btn btn-outline-secondary justify-content-start">
                        <x-admin.icon name="shield" :size="16" class="me-2" />
                        <span>Keamanan akun</span>
                    </a>
                    <a href="{{ route('vendor.staff.index') }}" class="btn btn-outline-secondary justify-content-start">
                        <x-admin.icon name="users" :size="16" class="me-2" />
                        <span>Anggota tim</span>
                    </a>
                </div>
            </x-admin.card>
        </div>
    </div>
@endsection
