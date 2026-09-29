@extends('layouts.admin')

@section('title', 'Kontak')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['System', ['label' => 'Kontak']]" />
@endsection

@section('content')
    <x-admin.page-header title="Kontak Toko" subtitle="Informasi kontak yang ditampilkan di halaman footer dan halaman kontak." />

    <form method="POST" action="{{ route('admin.contacts.update') }}">
        @csrf
        @method('PUT')
        <x-admin.card title="Detail Kontak" icon="map-pin">
            <div class="row g-3">
                <div class="col-12 col-lg-6">
                    <x-admin.form-field name="contact_address" label="Alamat" type="textarea" :rows="3" :value="$settings['contact_address']" :maxlength="500" />
                </div>
                <div class="col-12 col-lg-6">
                    <x-admin.form-field name="contact_hours" label="Jam Operasional" :value="$settings['contact_hours']" :maxlength="120" placeholder="Senin–Jumat 09.00–17.00 WIB" />
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <x-admin.form-field name="contact_email" label="Email" type="email" :value="$settings['contact_email']" :maxlength="160" />
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <x-admin.form-field name="contact_phone" label="Telepon" :value="$settings['contact_phone']" :maxlength="40" />
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <x-admin.form-field name="contact_whatsapp" label="WhatsApp" :value="$settings['contact_whatsapp']" :maxlength="40" />
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <x-admin.form-field name="contact_facebook" label="Facebook" :value="$settings['contact_facebook']" :maxlength="120" />
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <x-admin.form-field name="contact_instagram" label="Instagram" :value="$settings['contact_instagram']" :maxlength="120" />
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <x-admin.form-field name="contact_tiktok" label="TikTok" :value="$settings['contact_tiktok']" :maxlength="120" />
                </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary">
                    <x-admin.icon name="save" :size="14" /> Simpan Kontak
                </button>
            </div>
        </x-admin.card>
    </form>
@endsection
