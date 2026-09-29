@props(['autohide' => 5000])

<div
    class="admin-toast-stack"
    data-toast-container
    aria-live="polite"
    aria-atomic="true"
    data-autohide="{{ (int) $autohide }}"
>
    <template data-toast-template>
        <div class="toast show admin-toast" role="status" aria-live="polite" aria-atomic="true">
            <div class="toast-header">
                <span class="toast-dot" aria-hidden="true"></span>
                <strong class="me-auto toast-title">Notifikasi</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Tutup"></button>
            </div>
            <div class="toast-body"></div>
        </div>
    </template>
</div>
