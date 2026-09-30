@props([
    'target' => null,
    'preview' => null,
    'endpoint' => '/admin/file-manager/library',
    'type' => 'image',
    'title' => 'Pilih Media',
    'buttonLabel' => 'Pilih dari Media',
])

@php
    $pickerTarget = $target ?: 'media-url-'.substr(md5((string) $title.(string) $type), 0, 6);
    $pickerPreview = $preview ?: $pickerTarget.'-preview';
    $pickerModalId = 'media-picker-'.preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $pickerTarget);
    $pickerType = in_array($type, ['all', 'image', 'document'], true) ? $type : 'image';
@endphp

<button
    type="button"
    class="btn btn-outline-primary"
    data-media-picker-open="{{ $pickerModalId }}"
    data-media-picker-target="{{ $pickerTarget }}"
>
    <x-admin.icon name="image" :size="14" class="me-1" /> {{ $buttonLabel }}
</button>

<div class="modal fade" id="{{ $pickerModalId }}" tabindex="-1" aria-labelledby="{{ $pickerModalId }}-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="{{ $pickerModalId }}-title">
                    <x-admin.icon name="image" :size="18" class="me-1" /> {{ $title }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-7">
                        <input
                            type="search"
                            class="form-control"
                            placeholder="Cari nama berkas…"
                            data-media-picker-search="{{ $pickerModalId }}"
                            autocomplete="off"
                        >
                    </div>
                    <div class="col-md-5">
                        <select class="form-select" data-media-picker-filter="{{ $pickerModalId }}">
                            <option value="image" {{ $pickerType === 'image' ? 'selected' : '' }}>Gambar</option>
                            <option value="document" {{ $pickerType === 'document' ? 'selected' : '' }}>Dokumen (PDF)</option>
                            <option value="all" {{ $pickerType === 'all' ? 'selected' : '' }}>Semua</option>
                        </select>
                    </div>
                </div>
                <div class="small text-secondary mb-2" data-media-picker-status="{{ $pickerModalId }}">Memuat media…</div>
                <div class="row g-2" data-media-picker-grid="{{ $pickerModalId }}"></div>
            </div>
            <div class="modal-footer">
                <span class="small text-secondary me-auto">Berkas disajikan via <code>/img/…</code> (route img.serve).</span>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var MODAL_ID = @json($pickerModalId);
    var TARGET_ID = @json($pickerTarget);
    var PREVIEW_ID = @json($pickerPreview);
    var ENDPOINT = @json($endpoint);
    var DEFAULT_TYPE = @json($pickerType);

    window.MediaPicker = window.MediaPicker || { cache: {}, openedFor: {} };

    function modalEl() { return document.getElementById(MODAL_ID); }
    function gridEl() { return document.querySelector('[data-media-picker-grid="' + MODAL_ID + '"]'); }
    function statusEl() { return document.querySelector('[data-media-picker-status="' + MODAL_ID + '"]'); }
    function searchEl() { return document.querySelector('[data-media-picker-search="' + MODAL_ID + '"]'); }
    function filterEl() { return document.querySelector('[data-media-picker-filter="' + MODAL_ID + '"]'); }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function render(items) {
        var grid = gridEl();
        if (! grid) return;
        if (! items.length) {
            grid.innerHTML = '<div class="col-12"><div class="alert alert-secondary mb-0">Tidak ada media yang cocok.</div></div>';
            return;
        }
        grid.innerHTML = items.map(function (it) {
            var thumb = it.thumb
                ? '<img src="' + esc(it.thumb) + '" alt="" loading="lazy" style="width:100%;height:96px;object-fit:cover;border-radius:6px;background:#f1f3f5;">'
                : '<div class="d-flex align-items-center justify-content-center bg-light rounded" style="height:96px;font-size:11px;color:#868e96;">PDF</div>';
            return '<div class="col-6 col-md-3">'
                + '<button type="button" class="card h-100 w-100 text-start p-2" data-media-pick="' + esc(it.url) + '">'
                + thumb
                + '<span class="small fw-medium d-block mt-1 text-truncate" title="' + esc(it.name) + '">' + esc(it.name) + '</span>'
                + '<span class="text-secondary" style="font-size:11px;">' + esc(it.size_kb) + ' KB · ' + esc(it.modified_label) + '</span>'
                + '</button></div>';
        }).join('');
    }

    function load() {
        var status = statusEl();
        var q = searchEl() ? searchEl().value : '';
        var type = filterEl() ? filterEl().value : DEFAULT_TYPE;
        var key = ENDPOINT + '|q=' + q + '|t=' + type;
        if (status) status.textContent = 'Memuat media…';
        if (window.MediaPicker.cache[key]) {
            render(window.MediaPicker.cache[key].data || []);
            if (status) status.textContent = 'Menampilkan ' + (window.MediaPicker.cache[key].data || []).length + ' berkas.';
            return;
        }
        fetch(ENDPOINT + '?q=' + encodeURIComponent(q) + '&type=' + encodeURIComponent(type), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        }).then(function (r) {
            if (! r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        }).then(function (json) {
            var items = json.data || [];
            window.MediaPicker.cache[key] = json;
            render(items);
            if (status) status.textContent = 'Menampilkan ' + items.length + ' berkas.';
        }).catch(function () {
            if (status) status.textContent = 'Gagal memuat media. Buka halaman Media untuk menelusuri manual.';
            var grid = gridEl();
            if (grid) grid.innerHTML = '<div class="col-12"><div class="alert alert-warning mb-0">Endpoint JSON media belum di-wiring. Lihat catatan implementasi untuk route yang perlu ditambahkan.</div></div>';
        });
    }

    function choose(url) {
        var input = document.getElementById(window.MediaPicker.openedFor[MODAL_ID] || TARGET_ID);
        if (input) {
            input.value = url;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
        var preview = document.getElementById(PREVIEW_ID);
        if (preview && preview.tagName === 'IMG') {
            preview.src = url;
            preview.classList.remove('d-none');
        }
        var modal = modalEl();
        if (modal && window.bootstrap && window.bootstrap.Modal) {
            var inst = window.bootstrap.Modal.getInstance(modal);
            if (inst) inst.hide();
        }
    }

    if (! window.MediaPicker.boundGlobal) {
        window.MediaPicker.boundGlobal = true;
        document.addEventListener('click', function (ev) {
            var opener = ev.target.closest('[data-media-picker-open]');
            if (opener) {
                window.MediaPicker.openedFor[opener.getAttribute('data-media-picker-open')] = opener.getAttribute('data-media-picker-target');
            }
            var pick = ev.target.closest('[data-media-pick]');
            if (pick) {
                ev.preventDefault();
                var m = pick.closest('.modal');
                var id = m ? m.id : null;
                var saved = id && window.MediaPicker.openedFor[id] ? window.MediaPicker.openedFor[id] : null;
                var input = saved ? document.getElementById(saved) : null;
                var url = pick.getAttribute('data-media-pick');
                if (input) {
                    input.value = url;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
                var fallbackPreview = id ? document.getElementById(id.replace(/^media-picker-/, '') + '-preview') : null;
                if (fallbackPreview && fallbackPreview.tagName === 'IMG') {
                    fallbackPreview.src = url;
                    fallbackPreview.classList.remove('d-none');
                }
                if (m && window.bootstrap && window.bootstrap.Modal) {
                    var inst = window.bootstrap.Modal.getInstance(m);
                    if (inst) inst.hide();
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var modal = modalEl();
        if (! modal || modal.dataset.mediaPickerBound === '1') return;
        modal.dataset.mediaPickerBound = '1';
        modal.addEventListener('show.bs.modal', load);
        var grid = gridEl();
        if (grid) grid.addEventListener('click', function (ev) {
            var btn = ev.target.closest('[data-media-pick]');
            if (btn) { ev.preventDefault(); choose(btn.getAttribute('data-media-pick')); }
        });
        var timer = null;
        if (searchEl()) searchEl().addEventListener('input', function () {
            window.MediaPicker.cache = {};
            clearTimeout(timer);
            timer = setTimeout(load, 250);
        });
        if (filterEl()) filterEl().addEventListener('change', function () {
            window.MediaPicker.cache = {};
            load();
        });
    });
})();
</script>
