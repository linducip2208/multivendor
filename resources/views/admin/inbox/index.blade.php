@extends('layouts.admin')

@section('title', 'Inbox')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['CRM', ['label' => 'Inbox']]" />
@endsection

@section('content')
    <x-admin.page-header title="Inbox" subtitle="Pintu masuk ke kotak percakapan, tiket dukungan, dan notifikasi." />

    <div class="row g-3">
        <div class="col-12 col-md-4">
            <x-admin.card title="Percakapan" icon="message-circle" class="h-100">
                <p class="small text-secondary">Percakapan pelanggan dari storefront dan dari panel vendor.</p>
                <a href="{{ route('admin.conversations.index') }}" class="btn btn-outline-primary w-100">Buka Kotak Masuk</a>
            </x-admin.card>
        </div>
        <div class="col-12 col-md-4">
            <x-admin.card title="Tiket Dukungan" icon="life-buoy" class="h-100">
                <p class="small text-secondary">Permintaan bantuan pelanggan yang masuk sebagai tiket.</p>
                @if (\Route::has('admin.support-tickets.index'))
                    <a href="{{ route('admin.support-tickets.index') }}" class="btn btn-outline-primary w-100">Buka Tiket</a>
                @else
                    <button type="button" class="btn btn-outline-secondary w-100" disabled>Belum tersedia</button>
                @endif
            </x-admin.card>
        </div>
        <div class="col-12 col-md-4">
            <x-admin.card title="Notifikasi" icon="bell" class="h-100">
                <p class="small text-secondary">Pusat notifikasi dan pemberitahuan pelanggan.</p>
                <a href="{{ route('admin.notifications') }}" class="btn btn-outline-primary w-100">Buka Notifikasi</a>
            </x-admin.card>
        </div>
    </div>
@endsection
