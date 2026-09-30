@extends('layouts.admin')

@section('title', 'Menu')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Content', ['label' => 'Menu']]" />
@endsection

@section('content')
    <x-admin.page-header title="Menu Navigasi" subtitle="Susun tautan storefront secara visual: tambah, geser, inden, dan atur target buka." />

    <div class="alert alert-info d-flex gap-2 align-items-start" role="note">
        <span aria-hidden="true">💡</span>
        <div class="small mb-0">Seret kartu untuk mengurutkan. <strong>Inden</strong> menjadikan item sebagai anak (dropdown 1 level) dari item di atasnya. URL valid berupa path relatif (<code>/tentang-kami</code>, <code>#promo</code>) atau absolut <code>http(s)://</code>. Maksimal 50 item per menu.</div>
    </div>

    <div class="row g-3">
        @foreach ($menus as $menu)
            <div class="col-12 col-xl-4">
                <x-admin.card :title="$menu['label']" icon="list" :subtitle="count($menu['items']).' induk'" class="h-100">
                    <form method="POST" action="{{ route('admin.menus.update', ['key' => $menu['key']]) }}" data-menu-form="{{ $menu['key'] }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="key" value="{{ $menu['key'] }}">

                        <ul class="list-unstyled d-grid gap-2 mb-2 menu-builder" data-builder="{{ $menu['key'] }}" aria-label="Builder {{ $menu['label'] }}"></ul>

                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-add-root="{{ $menu['key'] }}">+ Tambah Item</button>
                        </div>

                        <p class="small text-secondary mb-3">Menu kosong akan memakai tautan bawaan storefront secara otomatis.</p>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-sm btn-primary">Simpan {{ $menu['label'] }}</button>
                        </div>
                    </form>
                </x-admin.card>
            </div>
        @endforeach
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const initial = @json(array_column($menus, 'items', 'key'));
    const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    function itemNode(data, isChild) {
        const li = document.createElement('li');
        li.className = 'card card-sm menu-node' + (isChild ? ' menu-node--child' : '');
        li.draggable = true;
        li.innerHTML =
            '<div class="card-body p-2">' +
            '<div class="d-flex align-items-center gap-1 mb-2">' +
            '<span class="menu-grip" title="Seret untuk memindah" aria-hidden="true">⠿</span>' +
            '<span class="badge ' + (isChild ? 'bg-orange-lt' : 'bg-blue-lt') + '">' + (isChild ? 'Anak' : 'Induk') + '</span>' +
            '<span class="ms-auto d-flex gap-1">' +
            (isChild
                ? '<button type="button" class="btn btn-sm btn-ghost-secondary" data-act="outdent" title="Jadikan induk">⇤</button>'
                : '<button type="button" class="btn btn-sm btn-ghost-secondary" data-act="indent" title="Jadikan anak dari item di atas">⇥</button>') +
            '<button type="button" class="btn btn-sm btn-ghost-danger" data-act="remove" title="Hapus">✕</button>' +
            '</span></div>' +
            '<div class="row g-1">' +
            '<div class="col-12"><input type="text" class="form-control form-control-sm" data-f="label" maxlength="80" required placeholder="Label" value="' + esc(data.label) + '" aria-label="Label"></div>' +
            '<div class="col-8"><input type="text" class="form-control form-control-sm" data-f="url" maxlength="500" required placeholder="/tentang-kami atau https://…" value="' + esc(data.url) + '" aria-label="URL"></div>' +
            '<div class="col-4"><select class="form-select form-select-sm" data-f="target" aria-label="Target"><option value="_self"' + (data.target !== '_blank' ? ' selected' : '') + '>Tab sama</option><option value="_blank"' + (data.target === '_blank' ? ' selected' : '') + '>Tab baru</option></select></div>' +
            '</div>' +
            (isChild ? '' : '<ul class="list-unstyled d-grid gap-2 mt-2 menu-children" aria-label="Anak"></ul>') +
            '</div>';
        return li;
    }

    function rootList(key) {
        return document.querySelector('[data-builder="' + key + '"]');
    }

    function addRoot(key, data) {
        const li = itemNode(data || { label: '', url: '', target: '_self' }, false);
        rootList(key).appendChild(li);
        return li;
    }

    function childrenUl(li) {
        return li.querySelector(':scope > .card-body > .menu-children');
    }

    function moveNode(li, act) {
        if (act === 'remove') {
            if (li.parentElement.children.length === 1 && li.parentElement.hasAttribute('data-builder')) return;
            li.remove();
            return;
        }
        if (act === 'indent') {
            const prev = li.previousElementSibling;
            if (!prev) return;
            childrenUl(prev).appendChild(li);
            refreshBadges(li, true);
            return;
        }
        if (act === 'outdent') {
            const root = li.closest('[data-builder]');
            const parentLi = li.parentElement.closest('.menu-node');
            if (!parentLi || !root) return;
            parentLi.after(li);
            refreshBadges(li, false);
        }
    }

    function refreshBadges(li, isChild) {
        const badge = li.querySelector(':scope > .card-body .badge');
        if (badge) {
            badge.textContent = isChild ? 'Anak' : 'Induk';
            badge.className = 'badge ' + (isChild ? 'bg-orange-lt' : 'bg-blue-lt');
        }
        // Tukar tombol inden/outdent
        const btn = li.querySelector(':scope > .card-body [data-act="indent"], :scope > .card-body [data-act="outdent"]');
        if (btn) {
            btn.dataset.act = isChild ? 'outdent' : 'indent';
            btn.textContent = isChild ? '⇤' : '⇥';
            btn.title = isChild ? 'Jadikan induk' : 'Jadikan anak dari item di atas';
        }
        const kids = childrenUl(li);
        if (kids && isChild && kids.children.length) {
            // Anak tidak boleh punya cucu: keluarkan ke induknya
            const grand = li.parentElement.closest('.menu-node');
            while (kids.firstChild) {
                const c = kids.firstChild;
                (grand ? grand.after(c) : li.after(c));
                refreshBadges(c, false);
            }
        }
        if (kids && isChild) kids.remove();
        if (!isChild && !childrenUl(li)) {
            const ul = document.createElement('ul');
            ul.className = 'list-unstyled d-grid gap-2 mt-2 menu-children';
            ul.setAttribute('aria-label', 'Anak');
            li.querySelector(':scope > .card-body').appendChild(ul);
        }
    }

    // --- Drag & drop HTML5 ---
    let dragLi = null;
    document.addEventListener('dragstart', (e) => {
        const li = e.target.closest('.menu-node');
        if (!li) return;
        dragLi = li;
        e.dataTransfer.effectAllowed = 'move';
        try { e.dataTransfer.setData('text/plain', 'menu'); } catch (_) {}
        li.classList.add('opacity-50');
    });
    document.addEventListener('dragend', () => {
        if (dragLi) dragLi.classList.remove('opacity-50');
        dragLi = null;
        document.querySelectorAll('.menu-drop-hint').forEach((el) => el.classList.remove('menu-drop-hint'));
    });
    document.addEventListener('dragover', (e) => {
        if (!dragLi) return;
        const li = e.target.closest('.menu-node');
        const ul = e.target.closest('.menu-children, [data-builder]');
        if (!li && !ul) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        if (li && li !== dragLi) li.classList.add('menu-drop-hint');
    });
    document.addEventListener('dragleave', (e) => {
        const li = e.target.closest && e.target.closest('.menu-node');
        if (li) li.classList.remove('menu-drop-hint');
    });
    document.addEventListener('drop', (e) => {
        if (!dragLi) return;
        const li = e.target.closest('.menu-node');
        const kids = e.target.closest('.menu-children');
        if (kids && !kids.closest('.menu-node')?.contains(dragLi)) {
            // Dijatuhkan ke area anak milik induk lain -> jadikan anak (1 level)
            const owner = kids.closest('.menu-node');
            if (owner && owner !== dragLi && !dragLi.contains(owner)) {
                e.preventDefault();
                kids.appendChild(dragLi);
                refreshBadges(dragLi, true);
            }
            return;
        }
        if (li && li !== dragLi && !dragLi.contains(li)) {
            e.preventDefault();
            const rect = li.getBoundingClientRect();
            const after = (e.clientY - rect.top) > rect.height / 2;
            const sameLevel = dragLi.parentElement === li.parentElement;
            const targetParent = li.parentElement;
            // Cegah induk dijatuhkan ke dalam anaknya sendiri / cucu
            if (targetParent.closest('.menu-node') === dragLi) return;
            // Cegah anak pindah ke level anak milik induk lain yang berbeda tanpa normalisasi
            if (!sameLevel) {
                const isTargetChildLevel = targetParent.classList.contains('menu-children');
                refreshBadges(dragLi, isTargetChildLevel);
            }
            after ? li.after(dragLi) : li.before(dragLi);
        }
    });

    // --- Aksi tombol ---
    document.addEventListener('click', (e) => {
        const add = e.target.closest('[data-add-root]');
        if (add) {
            addRoot(add.dataset.addRoot, { label: '', url: '', target: '_self' });
            return;
        }
        const btn = e.target.closest('[data-act]');
        if (!btn) return;
        const li = btn.closest('.menu-node');
        if (li) moveNode(li, btn.dataset.act);
    });

    // --- Serialisasi nama input sebelum submit ---
    document.addEventListener('submit', (e) => {
        const form = e.target.closest('[data-menu-form]');
        if (!form) return;
        const root = form.querySelector('[data-builder]');
        [...root.children].forEach((li, i) => {
            li.querySelectorAll(':scope > .card-body [data-f]').forEach((input) => {
                input.name = 'items[' + i + '][' + input.dataset.f + ']';
            });
            [...(childrenUl(li)?.children || [])].forEach((child, j) => {
                child.querySelectorAll('[data-f]').forEach((input) => {
                    input.name = 'items[' + i + '][children][' + j + '][' + input.dataset.f + ']';
                });
            });
        });
    });

    // --- Hydrate ---
    Object.entries(initial || {}).forEach(([key, items]) => {
        const list = rootList(key);
        if (!list) return;
        (items && items.length ? items : [{ label: '', url: '', target: '_self' }]).forEach((it) => {
            const li = addRoot(key, it);
            const kids = Array.isArray(it.children) ? it.children : [];
            kids.forEach((c) => childrenUl(li).appendChild(itemNode(c, true)));
        });
    });
})();
</script>
<style>
.menu-grip { cursor: grab; color: var(--tblr-secondary); font-size: 16px; user-select: none; }
.menu-node.menu-drop-hint > .card-body { outline: 2px dashed var(--tblr-primary); outline-offset: 2px; }
.menu-node--child { border-inline-start: 3px solid var(--tblr-orange); }
.menu-children:empty { min-height: 6px; }
.menu-children { padding-inline-start: 14px; border-inline-start: 2px dashed var(--tblr-border-color); }
</style>
@endpush
