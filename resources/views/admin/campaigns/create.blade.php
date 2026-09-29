@extends('layouts.admin')

@section('title', 'Buat Kampanye')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Marketing', ['label' => 'Kampanye', 'href' => route('admin.campaigns.index')], ['label' => 'Baru']]" />
@endsection

@section('content')
    <x-admin.page-header title="Kampanye Baru" subtitle="Aturan targeting dievaluasi terhadap keranjang dan riwayat pelanggan nyata." />

    <form method="POST" action="{{ route('admin.campaigns.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-lg-8">
                <x-admin.card title="Informasi Dasar" icon="info" class="mb-3">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-admin.form-field name="name" label="Nama Kampanye" required :maxlength="160" placeholder="Contoh: Gajian Sale Elektronik" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="campaign-type">Jenis</label>
                            <select class="form-select" id="campaign-type" name="type" required>
                                @foreach ($types as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="campaign-status">Status</label>
                            <select class="form-select" id="campaign-status" name="status" required>
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <x-admin.form-field name="description" label="Deskripsi" type="textarea" :rows="3" :maxlength="2000" />
                        </div>
                        <div class="col-md-6">
                            <x-admin.form-field name="starts_at" label="Mulai" type="datetime-local" />
                        </div>
                        <div class="col-md-6">
                            <x-admin.form-field name="ends_at" label="Berakhir" type="datetime-local" />
                        </div>
                    </div>
                </x-admin.card>

                <x-admin.card title="Anggaran dan Diskon" icon="cash" class="mb-3">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <x-admin.form-field name="budget" label="Anggaran" type="number" :min="0" :step="1000" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="campaign-discount-type">Tipe Diskon</label>
                            <select class="form-select" id="campaign-discount-type" name="discount_type">
                                <option value="percentage">Persentase</option>
                                <option value="flat">Nominal Tetap</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <x-admin.form-field name="discount_value" label="Nilai Diskon" type="number" :min="0" :step="0.01" />
                        </div>
                        <div class="col-md-6">
                            <x-admin.form-field name="usage_limit" label="Kuota Keseluruhan" type="number" :min="1" help="Kosongkan untuk tak terbatas." />
                        </div>
                        <div class="col-md-6">
                            <x-admin.form-field name="per_user_limit" label="Kuota per Pelanggan" type="number" :min="1" />
                        </div>
                    </div>
                </x-admin.card>

                <x-admin.card title="Ruang Lingkup Produk" icon="package">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="campaign-products">Produk</label>
                            <select class="form-select" id="campaign-products" name="product_ids[]" multiple size="10" data-multi-select>
                                @foreach ($ruleCatalogue as $rule)
                                    @if (($rule['options'] ?? []) !== [] && ($rule['key'] === 'product_ids' || $rule['key'] === 'category_ids'))
                                        @foreach ($rule['options'] as $id => $label)
                                            <option value="{{ $id }}">{{ $label }}</option>
                                        @endforeach
                                    @endif
                                @endforeach
                            </select>
                            <small class="text-secondary">Tahan Ctrl/Cmd untuk memilih beberapa produk. Berlaku sebagai batasan ketat bila diisi.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="campaign-categories">Kategori</label>
                            <select class="form-select" id="campaign-categories" name="category_ids[]" multiple size="10" data-multi-select>
                                @foreach ($ruleCatalogue as $rule)
                                    @if (($rule['options'] ?? []) !== [] && $rule['key'] === 'category_ids')
                                        @foreach ($rule['options'] as $id => $label)
                                            <option value="{{ $id }}">{{ $label }}</option>
                                        @endforeach
                                    @endif
                                @endforeach
                            </select>
                            <small class="text-secondary">Kategori dapat memuat produk yang tidak dipilih di daftar produk.</small>
                        </div>
                    </div>
                </x-admin.card>
            </div>

            <div class="col-lg-4">
                <x-admin.card title="Aturan Targeting" icon="filter" class="mb-3">
                    <x-admin.alert type="info" :dismissible="false" title="Semua aturan bersifat AND">
                        Setiap aturan dievaluasi satu per satu untuk pelanggan dan keranjang. Kosongkan kolom yang tidak dipakai.
                    </x-admin.alert>
                    <div class="row g-3">
                        @foreach ($ruleCatalogue as $rule)
                            <div class="col-12">
                                <label class="form-label small mb-1" for="rule-{{ $rule['key'] }}">{{ $rule['label'] }}</label>
                                @if (($rule['options'] ?? []) !== [] && count($rule['options']) > 0)
                                    <select class="form-select form-select-sm" id="rule-{{ $rule['key'] }}" name="rules[{{ $rule['key'] }}][]" multiple size="4" data-multi-select>
                                        @foreach ($rule['options'] as $id => $label)
                                            <option value="{{ $id }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="form-control form-control-sm"
                                        id="rule-{{ $rule['key'] }}"
                                        name="rules[{{ $rule['key'] }}]"
                                        aria-label="{{ $rule['label'] }}"
                                    >
                                @endif
                                <small class="text-secondary">{{ $rule['help'] }}</small>
                            </div>
                        @endforeach
                    </div>
                </x-admin.card>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3">
            <a href="{{ route('admin.campaigns.index') }}" class="btn btn-outline-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Kampanye</button>
        </div>
    </form>
@endsection
