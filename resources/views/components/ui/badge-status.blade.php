@props([
    'status' => null,
    'label' => null,
    'type' => 'general',
])

{{--
    UI badge-status: Tabler standardization wrapper around x-admin.badge.
    Maps order / payment / stock / review statuses to Tabler colors.
    Usage: <x-ui.badge-status :status="$order->status" /> or :status + :label
--}}

@php
    $key = strtolower(trim((string) ($status ?? '')));

    $map = [
        // order
        'pending' => 'warning',
        'processing' => 'info',
        'shipped' => 'info',
        'completed' => 'success',
        'cancelled' => 'danger',
        'canceled' => 'danger',
        'rejected' => 'danger',
        'refunded' => 'secondary',
        // payment
        'paid' => 'success',
        'unpaid' => 'warning',
        'failed' => 'danger',
        'approved' => 'info',
        // stock
        'in_stock' => 'success',
        'in-stock' => 'success',
        'low_stock' => 'warning',
        'low-stock' => 'warning',
        'out_of_stock' => 'danger',
        'out-of-stock' => 'danger',
        // review / generic boolean-ish
        'active' => 'success',
        'aktif' => 'success',
        'published' => 'success',
        'visible' => 'success',
        'inactive' => 'secondary',
        'nonaktif' => 'secondary',
        'hidden' => 'secondary',
        'draft' => 'secondary',
    ];

    // Numeric 1/0 fallback commonly used for status/active flags.
    if ($key === '' && is_numeric($status)) {
        $key = ((int) $status === 1) ? 'active' : 'inactive';
    }
    if (in_array($key, ['1', 'true'], true)) {
        $key = 'active';
    }
    if (in_array($key, ['0', 'false'], true)) {
        $key = 'inactive';
    }

    $color = $map[$key] ?? 'secondary';
    $text = $label ?? ($status ?? '');
@endphp

<x-admin.badge :color="$color" :text="$text" {{ $attributes }} />
