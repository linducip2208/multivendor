@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Tiket dukungan')
@section('subtitle', $open.' tiket terbuka')

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Tiket'],
])

@section('actions')
    <a href="{{ route('vendor.chat.inbox') }}" class="btn btn-outline-secondary">
        <x-admin.icon name="message-circle" :size="16" class="me-1" />
        <span>Chat</span>
    </a>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <x-admin.filters
                :action="route('vendor.tickets.index')"
                :filters="[
                    ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'value' => $selected, 'options' => ['' => 'Semua status'] + collect($statuses)->map(fn ($row, $key) => $key.' ('.$row['total'].')')->all()],
                ]"
            />

            <x-admin.card :padding="false">
                <x-admin.table dense>
                    <x-slot:table>
                        \App\Support\TableBuilder::make()
                            ->columns([
                                'reference' => ['label' => 'Referensi', 'width' => '170px'],
                                'subject' => ['label' => 'Perihal'],
                                'category' => ['label' => 'Kategori'],
                                'priority' => ['label' => 'Prioritas'],
                                'status' => ['label' => 'Status'],
                                'updated' => ['label' => 'Diperbarui', 'align' => 'end'],
                            ])
                            ->rows(
                                $tickets->map(fn ($ticket) => [
                                    'reference' => '<a href="'.route('vendor.tickets.show', $ticket->id).'" class="font-monospace fw-medium">'.e($ticket->reference).'</a>',
                                    'subject' => '<span class="d-block text-truncate fw-medium">'.e($ticket->subject).'</span><span class="text-secondary small text-truncate d-block">'.e(\Illuminate\Support\Str::limit($ticket->description, 70)).'</span>',
                                    'category' => '<span class="text-secondary small">'.e($categories[$ticket->type] ?? $ticket->type).'</span>',
                                    'priority' => $__status($ticket->priority, [
                                        'low' => ['Rendah', 'secondary'],
                                        'normal' => ['Normal', 'info'],
                                        'high' => ['Tinggi', 'warning'],
                                        'urgent' => ['Mendesak', 'danger'],
                                    ]),
                                    'status' => $__status($ticket->status),
                                    'updated' => '<span class="text-secondary small">'.e(\Carbon\Carbon::parse($ticket->updated_at ?? $ticket->created_at)->diffForHumans(short: true)).'</span>',
                                ])->all()
                            )
                            ->empty('Belum ada tiket dukungan.')
                    </x-slot:table>
                </x-admin.table>
            </x-admin.card>

            <x-admin.pagination :paginator="$tickets" class="mt-3" />
        </div>

        <div class="col-12 col-xl-4">
            <x-admin.card title="Buat tiket" icon="life-buoy">
                <form method="POST" action="{{ route('vendor.tickets.store') }}">
                    @csrf

                    <x-admin.form-field name="subject" label="Perihal" required placeholder="Ringkasan masalah" />
                    <x-admin.form-field name="category" label="Kategori" type="select" required :options="$categories" />
                    <x-admin.form-field name="priority" label="Prioritas" type="select" required :options="$priorities" />
                    <x-admin.form-field name="order_id" label="Nomor pesanan (opsional)" type="number" :min="1" help="Isi bila tiket berkaitan dengan pesanan tertentu." />
                    <x-admin.form-field name="message" label="Detail" type="textarea" :rows="6" required help="Sertakan langkah reproduksi dan tangkapan layar bila ada." />

                    <button type="submit" class="btn btn-primary w-100">
                        <x-admin.icon name="mail" :size="16" class="me-1" />
                        <span>Kirim tiket</span>
                    </button>
                </form>
            </x-admin.card>

            <x-admin.card title="Butuh bantuan cepat?" icon="message-circle" class="mt-3">
                <p class="text-secondary small mb-3">
                    Untuk pertanyaan singkat tentang pesanan, chat langsung dengan pelanggan lebih cepat daripada tiket.
                </p>
                <a href="{{ route('vendor.chat.inbox') }}" class="btn btn-outline-primary w-100">
                    <x-admin.icon name="message-circle" :size="16" class="me-1" />
                    <span>Buka inbox</span>
                </a>
            </x-admin.card>
        </div>
    </div>
@endsection
