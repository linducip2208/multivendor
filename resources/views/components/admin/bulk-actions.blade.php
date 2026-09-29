@props([
    'action' => '#',
    'method' => 'POST',
    'selected' => 0,
    'label' => 'Aksi',
    'formId' => 'admin-bulk-form',
    'submitLabel' => 'Terapkan',
])

@php
    $method = strtoupper((string) $method);
    $method = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) ? $method : 'POST';
@endphp

<form
    id="{{ $formId }}"
    method="POST"
    action="{{ $action }}"
    class="admin-bulk-actions d-flex flex-wrap align-items-center gap-2"
    data-bulk-form
    data-confirm="Pastikan data yang dipilih sudah benar."
>
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <div class="form-check mb-0">
        <input
            class="form-check-input"
            type="checkbox"
            data-bulk-master
            aria-label="Pilih semua"
        >
        <label class="form-check-label small text-secondary" for="">
            {{ $label }}
        </label>
    </div>

    <span class="badge bg-secondary-lt text-secondary" data-bulk-count>{{ (int) $selected }}</span>

    {{ $slot }}

    <button type="submit" class="btn btn-primary btn-sm ms-auto" data-bulk-submit>
        <x-admin.icon name="check" :size="16" />
        <span>{{ $submitLabel }}</span>
    </button>
</form>
