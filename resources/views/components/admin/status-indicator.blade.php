@props([
    'status' => null,
    'map' => null,
    'pill' => true,
])

@php
    $defaults = [
        'pending' => ['label' => 'Menunggu', 'color' => 'warning'],
        'waiting' => ['label' => 'Menunggu', 'color' => 'warning'],
        'unpaid' => ['label' => 'Belum Bayar', 'color' => 'warning'],
        'on_hold' => ['label' => 'Ditahan', 'color' => 'warning'],
        'low_stock' => ['label' => 'Stok Menipis', 'color' => 'warning'],
        'paid' => ['label' => 'Lunas', 'color' => 'success'],
        'active' => ['label' => 'Aktif', 'color' => 'success'],
        'approved' => ['label' => 'Disetujui', 'color' => 'success'],
        'published' => ['label' => 'Terbit', 'color' => 'success'],
        'delivered' => ['label' => 'Selesai', 'color' => 'success'],
        'completed' => ['label' => 'Selesai', 'color' => 'success'],
        'confirmed' => ['label' => 'Dikonfirmasi', 'color' => 'info'],
        'shipped' => ['label' => 'Dikirim', 'color' => 'info'],
        'in_transit' => ['label' => 'Dalam Perjalanan', 'color' => 'info'],
        'new' => ['label' => 'Baru', 'color' => 'info'],
        'processing' => ['label' => 'Diproses', 'color' => 'primary'],
        'packed' => ['label' => 'Dikemas', 'color' => 'primary'],
        'inactive' => ['label' => 'Nonaktif', 'color' => 'secondary'],
        'draft' => ['label' => 'Draf', 'color' => 'secondary'],
        'archived' => ['label' => 'Arsip', 'color' => 'secondary'],
        'expired' => ['label' => 'Kedaluwarsa', 'color' => 'secondary'],
        'rejected' => ['label' => 'Ditolak', 'color' => 'danger'],
        'failed' => ['label' => 'Gagal', 'color' => 'danger'],
        'cancelled' => ['label' => 'Dibatalkan', 'color' => 'danger'],
        'canceled' => ['label' => 'Dibatalkan', 'color' => 'danger'],
        'refunded' => ['label' => 'Dikembalikan', 'color' => 'danger'],
        'out_of_stock' => ['label' => 'Stok Habis', 'color' => 'danger'],
    ];

    $raw = $status;
    $key = strtolower(str_replace([' ', '-'], '_', (string) (is_object($raw) && method_exists($raw, 'value') ? $raw->value : $raw)));
    $table = array_replace($defaults, is_array($map) ? $map : []);
    $entry = $key === '' ? null : ($table[$key] ?? null);

    if ($entry === null) {
        $label = (string) (is_object($raw) && method_exists($raw, 'label') ? $raw->label : (is_object($raw) && isset($raw->name) ? $raw->name : $raw));
        $color = 'secondary';
    } elseif (is_array($entry)) {
        $label = (string) ($entry['label'] ?? \Illuminate\Support\Str::headline($key));
        $color = (string) ($entry['color'] ?? 'secondary');
    } else {
        $label = (string) $entry;
        $color = 'secondary';
    }

    $color = in_array($color, ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'], true) ? $color : 'secondary';
@endphp

@if ($raw !== null && $raw !== '')
    <x-admin.badge
        :text="$label"
        :color="$color"
        :pill="$pill"
        dot
        {{ $attributes->merge(['class' => 'admin-status-indicator']) }}
        data-status="{{ $key }}"
    />
@endif
