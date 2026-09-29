@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Promo & kupon')
@section('subtitle', 'Kode diskon dan kampanye milik toko Anda')

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Promo'],
])

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl">
            <x-admin.stat label="Total kupon" :value="$stats['coupons']" icon="ticket" color="primary" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Kupon aktif" :value="$stats['active_coupons']" icon="check-circle" color="success" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Total kampanye" :value="$stats['campaigns']" icon="megaphone" color="info" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Kampanye aktif" :value="$stats['active_campaigns']" icon="zap" color="warning" />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <x-admin.card :padding="false">
                <x-slot:title>{{ $type === 'coupon' ? 'Kode kupon' : 'Kampanye' }}</x-slot:title>

                <x-slot:actions>
                    <x-admin.tabs :tabs="[
                        ['label' => 'Kupon', 'href' => route('vendor.promotions.index', ['type' => 'coupon']), 'active' => $type === 'coupon', 'count' => $stats['coupons']],
                        ['label' => 'Kampanye', 'href' => route('vendor.promotions.index', ['type' => 'campaign']), 'active' => $type === 'campaign', 'count' => $stats['campaigns']],
                    ]" />
                </x-slot:actions>

                @if ($type === 'coupon')
                    <x-admin.table dense>
                        <x-slot:table>
                            \App\Support\TableBuilder::make()
                                ->columns([
                                    'code' => ['label' => 'Kode'],
                                    'type' => ['label' => 'Jenis'],
                                    'value' => ['label' => 'Nilai', 'align' => 'end'],
                                    'period' => ['label' => 'Periode'],
                                    'usage' => ['label' => 'Pemakaian', 'align' => 'end'],
                                    'status' => ['label' => 'Status'],
                                    'actions' => ['label' => '', 'align' => 'end', 'width' => '90px'],
                                ])
                                ->rows(
                                    $coupons->map(fn ($coupon) => [
                                        'code' => '<span class="badge bg-primary-lt text-primary font-monospace">'.e($coupon->code).'</span>',
                                        'type' => e($types[$coupon->coupon_type] ?? $coupon->coupon_type),
                                        'value' => e($coupon->coupon_type === 'percentage' ? rtrim(rtrim((string) $coupon->discount_value, '0'), '.') . '%' : Currency::format($coupon->discount_value)),
                                        'period' => '<span class="text-secondary small">'.e($coupon->start_date ? \Carbon\Carbon::parse($coupon->start_date)->format('d/m/Y') : '—').' s/d '.e($coupon->end_date ? \Carbon\Carbon::parse($coupon->end_date)->format('d/m/Y') : '—').'</span>',
                                        'usage' => e(Currency::number($coupon->used_count ?? 0)).($coupon->usage_limit ? '/'.e(Currency::number($coupon->usage_limit)) : ''),
                                        'status' => $__status($coupon->status ? 'active' : 'inactive'),
                                        'actions' => '<form method="POST" action="'.route('vendor.promotions.destroy').'" data-confirm="Hapus kupon '.e($coupon->code).'?">'
                                            .csrf()
                                            .'<input type="hidden" name="kind" value="coupon"><input type="hidden" name="id" value="'.(int) $coupon->id.'">'
                                            .'<button type="submit" class="btn btn-sm btn-ghost-danger" aria-label="Hapus"><i class="fa-solid fa-trash"></i></button></form>',
                                    ])->all()
                                )
                                ->empty('Belum ada kupon. Buat kupon pertama Anda di panel sebelah kanan.')
                        </x-slot:table>
                    </x-admin.table>
                @else
                    <x-admin.table dense>
                        <x-slot:table>
                            \App\Support\TableBuilder::make()
                                ->columns([
                                    'name' => ['label' => 'Kampanye'],
                                    'type' => ['label' => 'Jenis'],
                                    'period' => ['label' => 'Periode'],
                                    'clicks' => ['label' => 'Klik', 'align' => 'end'],
                                    'revenue' => ['label' => 'Pendapatan', 'align' => 'end'],
                                    'status' => ['label' => 'Status'],
                                    'actions' => ['label' => '', 'align' => 'end', 'width' => '90px'],
                                ])
                                ->rows(
                                    $campaigns->map(fn ($campaign) => [
                                        'name' => '<span class="fw-medium d-block text-truncate">'.e($campaign->name).'</span>'.($campaign->code ? '<span class="text-secondary small font-monospace">'.e($campaign->code).'</span>' : ''),
                                        'type' => e($campaignTypes[$campaign->type] ?? $campaign->type),
                                        'period' => '<span class="text-secondary small">'.e($campaign->starts_at ? \Carbon\Carbon::parse($campaign->starts_at)->format('d/m/Y') : '—').' s/d '.e($campaign->ends_at ? \Carbon\Carbon::parse($campaign->ends_at)->format('d/m/Y') : '—').'</span>',
                                        'clicks' => e(Currency::number($campaign->clicks ?? 0)),
                                        'revenue' => '<span class="fw-medium">'.e(Currency::format($campaign->revenue ?? 0)).'</span>',
                                        'status' => $__status($campaign->status),
                                        'actions' => '<form method="POST" action="'.route('vendor.promotions.destroy').'" data-confirm="Hapus kampanye '.e($campaign->name).'?">'
                                            .csrf().'@method("DELETE")'
                                            .'<input type="hidden" name="kind" value="campaign"><input type="hidden" name="id" value="'.(int) $campaign->id.'">'
                                            .'<button type="submit" class="btn btn-sm btn-ghost-danger" aria-label="Hapus"><i class="fa-solid fa-trash"></i></button></form>',
                                    ])->all()
                                )
                                ->empty('Belum ada kampanye promotion.')
                        </x-slot:table>
                    </x-admin.table>
                @endif
            </x-admin.card>
        </div>

        <div class="col-12 col-xl-4">
            <x-admin.card title="Buat kupon" icon="ticket">
                <form method="POST" action="{{ route('vendor.promotions.store') }}">
                    @csrf
                    <input type="hidden" name="kind" value="coupon">

                    <x-admin.form-field name="code" label="Kode" required placeholder="HEMAT20" help="Kode hanya boleh huruf, angka, tanda hubung, dan garis bawah." />
                    <x-admin.form-field name="title" label="Nama promo" placeholder="Diskon akhir tahun" />
                    <x-admin.form-field name="coupon_type" label="Jenis" type="select" required :options="$types" />
                    <x-admin.form-field name="discount_value" label="Nilai diskon" type="number" :min="0" :step="0.01" required help="Persentase 0-100, atau nominal untuk diskon tetap." />
                    <x-admin.form-field name="min_purchase" label="Minimum belanja" type="number" :min="0" :step="0.01" />
                    <x-admin.form-field name="max_discount" label="Maksimum diskon" type="number" :min="0" :step="0.01" help="Kosongkan untuk diskon tanpa batas." />
                    <div class="row g-2">
                        <div class="col-6">
                            <x-admin.form-field name="start_date" label="Mulai" type="date" />
                        </div>
                        <div class="col-6">
                            <x-admin.form-field name="end_date" label="Berakhir" type="date" />
                        </div>
                    </div>
                    <x-admin.form-field name="usage_limit" label="Batas pemakaian" type="number" :min="1" help="Kosongkan untuk tidak terbatas." />
                    <x-admin.form-field name="usage_per_customer" label="Batas per pelanggan" type="number" :min="1" :value="1" />
                    <x-admin.form-field name="status" label="Aktif" type="checkbox" :value="1" />

                    <button type="submit" class="btn btn-primary w-100">
                        <x-admin.icon name="plus" :size="16" class="me-1" />
                        <span>Simpan kupon</span>
                    </button>
                </form>
            </x-admin.card>

            <x-admin.card title="Buat kampanye" icon="megaphone" class="mt-3">
                <form method="POST" action="{{ route('vendor.promotions.store') }}">
                    @csrf
                    <input type="hidden" name="kind" value="campaign">

                    <x-admin.form-field name="name" label="Nama kampanye" required />
                    <x-admin.form-field name="code" label="Kode (opsional)" placeholder="PROMO25" />
                    <x-admin.form-field name="type" label="Jenis" type="select" required :options="$campaignTypes" />
                    <x-admin.form-field name="description" label="Deskripsi" type="textarea" :rows="3" />
                    <div class="row g-2">
                        <div class="col-6">
                            <x-admin.form-field name="discount_type" label="Tipe diskon" type="select" :options="['percentage' => 'Persentase', 'fixed' => 'Nominal']" />
                        </div>
                        <div class="col-6">
                            <x-admin.form-field name="discount_value" label="Nilai" type="number" :min="0" :step="0.01" />
                        </div>
                    </div>
                    <x-admin.form-field name="budget" label="Anggaran" type="number" :min="0" :step="0.01" />
                    <div class="row g-2">
                        <div class="col-6">
                            <x-admin.form-field name="starts_at" label="Mulai" type="date" />
                        </div>
                        <div class="col-6">
                            <x-admin.form-field name="ends_at" label="Berakhir" type="date" />
                        </div>
                    </div>
                    <x-admin.form-field name="status" label="Status" type="select" required :options="[
                        'draft' => 'Draf',
                        'scheduled' => 'Terjadwal',
                        'active' => 'Aktif',
                        'paused' => 'Dijeda',
                        'ended' => 'Selesai',
                    ]" />

                    <button type="submit" class="btn btn-outline-primary w-100">
                        <x-admin.icon name="megaphone" :size="16" class="me-1" />
                        <span>Simpan kampanye</span>
                    </button>
                </form>
            </x-admin.card>
        </div>
    </div>
@endsection
