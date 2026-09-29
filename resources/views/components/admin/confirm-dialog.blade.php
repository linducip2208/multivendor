<dialog class="admin-confirm-dialog" data-confirm-dialog aria-labelledby="admin-confirm-title">
    <div class="admin-confirm-dialog__panel">
        <div class="admin-confirm-dialog__icon" aria-hidden="true">
            <x-admin.icon name="alert-triangle" :size="28" />
        </div>
        <h2 class="admin-confirm-dialog__title" id="admin-confirm-title">Konfirmasi tindakan</h2>
        <p class="admin-confirm-dialog__message" data-confirm-message>Anda yakin ingin melanjutkan tindakan ini?</p>
        <div class="admin-confirm-dialog__actions">
            <button type="button" class="btn btn-outline-secondary" data-confirm-cancel>Batal</button>
            <button type="button" class="btn btn-danger" data-confirm-accept>Ya, lanjutkan</button>
        </div>
    </div>
</dialog>
