@props([
    'action' => '#',
    'method' => 'DELETE',
    'message' => 'Tindakan ini tidak dapat dibatalkan. Lanjutkan?',
    'label' => 'Hapus',
    'variant' => 'danger',
    'icon' => 'trash',
    'size' => 'btn-sm',
    'class' => '',
])

@php
    $method = strtoupper((string) $method);
    $method = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) ? $method : 'DELETE';
    $variant = in_array($variant, ['danger', 'warning', 'primary', 'secondary', 'outline-danger', 'outline-warning', 'outline-primary', 'outline-secondary', 'link'], true)
        ? $variant
        : 'danger';
    $icon = $icon ?: ($method === 'DELETE' ? 'trash' : 'refresh');
@endphp

<form
    method="POST"
    action="{{ $action }}"
    class="d-inline admin-confirm-form"
    onclick="return confirm(@js($message))"
    data-confirm="{{ $message }}"
    data-confirm-inline="1"
>
    @csrf
    @if ($method !== 'POST') @method($method) @endif
    <button
        type="submit"
        {{ $attributes->merge(['class' => 'btn '.$variant.' '.$size.' '.trim($class)]) }}
    >
        <x-admin.icon :name="$icon" :size="16" />
        <span>{{ $label }}</span>
    </button>
</form>
