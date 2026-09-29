@extends('layouts.admin')

@section('title', 'Ubah Kampanye')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Pemasaran', ['label' => 'Kampanye', 'href' => route('admin.campaigns.index')], ['label' => \Illuminate\Support\Str::limit($campaign['name'], 40)]]" />
@endsection

@section('content')
    <x-admin.page-header :title="'Ubah '.$campaign['name']" :subtitle="'Status '.$campaign['status_label'].' · '.$campaign['rule_count'].' aturan aktif.'">
        <x-slot:actions>
            <a href="{{ route('admin.campaigns.show', $campaign['id']) }}" class="btn btn-outline-secondary btn-sm">Detail</a>
            <a href="{{ route('admin.campaigns.index') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.campaigns.update', $campaign['id']) }}">
        @csrf
        @method('PUT')
        <div class="row g-3">
            <div class="col-lg-8">
                <x-admin.card title="Informasi Dasar" icon="info" class="mb-3">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-admin.form-field name="name" label="Nama Kampanye" :value="$campaign['name']" required :maxlength="160" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="campaign-type">Jenis</label>
                            <select class="form-select" id="campaign-type" name="type" required>
                                @foreach ($types as $value => $label)
                                    <option value="{{ $value }}" @selected($campaign['type'] === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="campaign-status">Status</label>
                            <select class="form-select" id="campaign-status" name="status" required>
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}" @selected($campaign['status'] === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <x-admin.form-field name="description" label="Deskripsi" type="textarea" :rows="3" :maxlength="2000" />
                        </div>
                        <div class="col-md-6">
                            <x-admin.form-field name="starts_at" label="Mulai" type="datetime-local" :value="$campaign['starts_at'] !== '' ? str_replace(' ', 'T', $campaign['starts_at']) : null" />
                        </div>
                        <div class="col-md-6">
                            <x-admin.form-field name="ends_at" label="Berakhir" type="datetime-local" :value="$campaign['ends_at'] !== '' ? str_replace(' ', 'T', $campaign['ends_at']) : null" />
                        </div>
                    </div>
                </x-admin.card>

                <x-admin.card title="Anggaran dan Diskon" icon="cash" class="mb-3">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <x-admin.form-field name="budget" label="Anggaran" type="number" :value="$campaign['budget']" :min="0" :step="1000" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="campaign-discount-type">Tipe Diskon</label>
                            <select class="form-select" id="campaign-discount-type" name="discount_type">
                                <option value="percentage" @selected($model->discount_type === 'percentage')>Persentase</option>
                                <option value="flat" @selected($model->discount_type === 'flat')>Nominal Tetap</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <x-admin.form-field name="discount_value" label="Nilai Diskon" type="number" :value="$model->discount_value" :min="0" :step="0.01" />
                        </div>
                        <div class="col-md-6">
                            <x-admin.form-field name="usage_limit" label="Kuota Keseluruhan" type="number" :value="$model->usage_limit" :min="1" />
                        </div>
                        <div class="col-md-6">
                            <x-admin.form-field name="per_user_limit" label="Kuota per Pelanggan" type="number" :value="$model->per_user_limit" :min="1" />
                        </div>
                    </div>
                </x-admin.card>

                <x-admin.card title="Ruang Lingkup Produk" icon="package">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="campaign-products">Produk</label>
                            <select class="form-select" id="campaign-products" name="product_ids[]" multiple size="10" data-multi-select>
                                @foreach ($ruleCatalogue as $rule)
                                    @if (($rule['options'] ?? []) !== [] && $rule['key'] === 'product_ids')
                                        @foreach ($rule['options'] as $id => $label)
                                            <option value="{{ $id }}" @selected(in_array((int) $id, $selectedProducts, true))>{{ $label }}</option>
                                        @endforeach
                                    @endif
                                @endforeach
                            </select>
                            <small class="text-secondary">Tahan Ctrl/Cmd untuk memilih beberapa produk.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="campaign-categories">Kategori</label>
                            <select class="form-select" id="campaign-categories" name="category_ids[]" multiple size="10" data-multi-select>
                                @foreach ($ruleCatalogue as $rule)
                                    @if (($rule['options'] ?? []) !== [] && $rule['key'] === 'category_ids')
                                        @foreach ($rule['options'] as $id => $label)
                                            <option value="{{ $id }}" @selected(in_array((int) $id, $selectedCategories, true))>{{ $label }}</option>
                                        @endforeach
                                    @endif
                                @endforeach
                            </select>
                        </div>
                    </div>
                </x-admin.card>
            </div>

            <div class="col-lg-4">
                <x-admin.card title="Aturan Targeting" icon="filter" class="mb-3">
                    <div class="row g-3">
                        @foreach ($ruleCatalogue as $rule)
                            <div class="col-12">
                                <label class="form-label small mb-1" for="rule-{{ $rule['key'] }}">{{ $rule['label'] }}</label>
                                @if (($rule['options'] ?? []) !== [] && count($rule['options']) > 0)
                                    <select class="form-select form-select-sm" id="rule-{{ $rule['key'] }}" name="rules[{{ $rule['key'] }}][]" multiple size="4" data-multi-select>
                                        @foreach ($rule['options'] as $id => $label)
                                            <option value="{{ $id }}" @selected(in_array((int) $id, (array) ($selectedRules[$rule['key']] ?? []), true))>{{ $label }}</option>
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
                                        value="{{ $selectedRules[$rule['key']] ?? '' }}"
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
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </div>
    </form>
@endsection
