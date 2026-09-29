@extends('layouts.admin')

@section('title', $conversation['subject'])

@section('breadcrumb')
    <x-admin.breadcrumb :items="['CRM', ['label' => 'Percakapan', 'href' => route('admin.conversations.index')], ['label' => \Illuminate\Support\Str::limit($conversation['subject'], 40)]]" />
@endsection

@section('content')
    <x-admin.page-header :title="$conversation['subject']" :subtitle="''.$conversation['uuid'].' · dibuat '.$conversation['created_at']">
        <x-slot:actions>
            <x-admin.badge :text="$conversation['status_label']" :color="match($conversation['status']) { 'open' => 'info', 'pending' => 'warning', 'closed' => 'success', default => 'secondary' }" pill />
            <a href="{{ route('admin.conversations.index') }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3">
        <div class="col-lg-8">
            <x-admin.card title="Riwayat Pesan" icon="message-circle" class="mb-3">
                <div class="d-flex flex-column gap-3">
                    @forelse ($messages as $message)
                        <div @class([
                            'd-flex',
                            'justify-content-end' => $message['mine'] && ! $message['internal'],
                        ])>
                            <div @class([
                                'border rounded-3 p-3',
                                'w-100' => $message['internal'],
                                'bg-warning-lt' => $message['internal'],
                                'bg-primary-lt' => $message['mine'] && ! $message['internal'],
                            ]) @if ($message['internal']) role="note" aria-label="Catatan internal" @endif>
                                <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                                    <span class="fw-semibold small">{{ $message['author'] }}</span>
                                    <div class="d-flex align-items-center gap-1">
                                        @if ($message['internal'])
                                            <x-admin.badge text="Internal" color="warning" pill />
                                        @endif
                                        @if ($message['flagged'])
                                            <x-admin.badge text="Ditandai" color="danger" pill />
                                        @endif
                                        <small class="text-secondary">{{ $message['at'] }}</small>
                                    </div>
                                </div>
                                <p class="mb-0 small" style="white-space: pre-wrap;">{{ $message['body'] }}</p>
                            </div>
                        </div>
                    @empty
                        <x-admin.empty-state icon="message-circle" title="Belum ada pesan" text="Percakapan ini belum berisi pesan apa pun." />
                    @endforelse
                </div>
            </x-admin.card>

            <x-admin.card title="Balas" icon="send">
                <form method="POST" action="{{ route('admin.conversations.reply', $conversation['id']) }}">
                    @csrf
                    <x-admin.alert type="info" :dismissible="false" title="Catatan internal tidak terlihat pelanggan" icon="lock">
                        Centang "Catatan internal" untuk mencatat hal yang hanya diketahui tim. Tanpa centang, pesan dikirim ke pelanggan dan status percakapan menjadi Menunggu.
                    </x-admin.alert>
                    <x-admin.form-field name="body" label="Pesan" type="textarea" :rows="4" required :maxlength="5000" placeholder="Tulis balasan atau catatan internal" />
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="internal_note" value="1" id="internal-note">
                            <label class="form-check-label" for="internal-note">Catatan internal</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="close" value="1" id="close-conversation">
                            <label class="form-check-label" for="close-conversation">Tutup percakapan setelah membalas</label>
                        </div>
                        <button type="submit" class="btn btn-primary">Kirim</button>
                    </div>
                </form>
            </x-admin.card>
        </div>

        <div class="col-lg-4">
            <x-admin.card title="Detail" icon="info" class="mb-3">
                <dl class="row small mb-0">
                    <dt class="col-5 text-secondary">Nomor</dt>
                    <dd class="col-7 text-end"><code>{{ $conversation['uuid'] }}</code></dd>
                    <dt class="col-5 text-secondary">Tipe</dt>
                    <dd class="col-7 text-end">{{ \Illuminate\Support\Str::headline($conversation['type']) }}</dd>
                    <dt class="col-5 text-secondary">Prioritas</dt>
                    <dd class="col-7 text-end">{{ \Illuminate\Support\Str::headline($conversation['priority']) }}</dd>
                    <dt class="col-5 text-secondary">Toko</dt>
                    <dd class="col-7 text-end">{{ $conversation['shop'] }}</dd>
                    <dt class="col-5 text-secondary">Pesanan</dt>
                    <dd class="col-7 text-end">{{ $conversation['order'] }}</dd>
                    <dt class="col-5 text-secondary">Dibuat</dt>
                    <dd class="col-7 text-end">{{ $conversation['created_at'] }}</dd>
                </dl>
            </x-admin.card>

            <x-admin.card title="Peserta" icon="users" flush>
                <ul class="list-group list-group-flush">
                    @foreach ($participants as $participant)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <span class="d-block fw-semibold">{{ $participant['name'] }}</span>
                                <small class="text-secondary">{{ $participant['email'] }}</small>
                            </div>
                            <div class="text-end">
                                <x-admin.badge :text="\Illuminate\Support\Str::headline($participant['role'])" color="secondary" pill />
                                @if ($participant['unread'] > 0)
                                    <x-admin.badge :text="number_format($participant['unread'], 0, ',', '.').' belum dibaca'" color="warning" pill />
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-admin.card>
        </div>
    </div>
@endsection
