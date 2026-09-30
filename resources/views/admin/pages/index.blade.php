@extends('layouts.admin')

@section('title', 'Halaman Statis')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Content', ['label' => 'Halaman']]" />
@endsection

@section('content')
    <x-admin.page-header title="Halaman Statis" subtitle="Konten halaman publik + page builder blok. Blok disimpan sebagai JSON di setting page_blocks_{key}." />

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <x-admin.card title="Media Halaman" icon="image" subtitle="Pilih gambar dari media library, lalu tempel URL /img/… ke kolom gambar blok (hero/galeri)." class="mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-9">
                <label class="form-label small mb-1" for="page-media-url">URL Gambar (/img/…)</label>
                <input class="form-control" id="page-media-url" readonly placeholder="Klik “Pilih dari Media”, URL akan terisi di sini…">
            </div>
            <div class="col-md-3">
                <x-admin.media-picker target="page-media-url" title="Pilih Gambar Halaman" />
            </div>
        </div>
        <div class="mt-2"><img id="page-media-url-preview" alt="" class="rounded border d-none" style="max-height:120px;"></div>
    </x-admin.card>

    <form method="POST" action="{{ route('admin.pages.update') }}" id="cms-pages-form">
        @csrf
        @method('PUT')
        <div class="row g-3">
            @foreach ($pages as $page)
                <div class="col-12" id="page-wrap-{{ $page['key'] }}">
                    <x-admin.card :title="$page['label']" icon="file-text" :subtitle="$page['configured'] ? 'Sudah ada isi' : 'Kosong'">
                        <label class="form-label small mb-1" for="page-{{ $page['key'] }}">Isi Halaman (HTML lama — dipakai bila blok kosong)</label>
                        <textarea
                            class="form-control"
                            id="page-{{ $page['key'] }}"
                            name="pages[{{ $page['key'] }}]"
                            rows="6"
                            maxlength="100000"
                            placeholder="&lt;p&gt;Isi halaman…&lt;/p&gt;"
                        >{{ $page['content'] }}</textarea>

                        <hr class="my-3">

                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2" data-blocks-editor="{{ $page['key'] }}">
                            <strong class="me-1">Blok Pembangun Halaman</strong>
                            <span class="badge bg-secondary" data-blocks-count>0 blok</span>
                            <span class="ms-auto d-flex flex-wrap gap-1" role="group" aria-label="Tambah blok">
                                @foreach (['text' => 'Teks', 'hero' => 'Hero', 'products' => 'Produk', 'gallery' => 'Galeri', 'faq' => 'FAQ', 'cta' => 'CTA'] as $type => $label)
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-add-block="{{ $type }}">+ {{ $label }}</button>
                                @endforeach
                            </span>
                        </div>

                        <input type="hidden" name="blocks[{{ $page['key'] }}]" data-blocks-input value="{{ e(json_encode($page['blocks'] ?? [], JSON_UNESCAPED_UNICODE)) }}">

                        <div class="vstack gap-2" data-blocks-list data-key="{{ $page['key'] }}"></div>
                        <p class="text-secondary small mt-1 mb-0">Susun ulang: tombol Naik/Turun atau seret kartu blok (drag &amp; drop HTML5).</p>

                        <details class="mt-2">
                            <summary class="small fw-medium">Pratinjau blok (klik untuk buka)</summary>
                            <div class="border rounded p-2 mt-1 bg-light" data-blocks-preview></div>
                        </details>
                    </x-admin.card>
                </div>
            @endforeach
        </div>

        <div class="d-flex justify-content-end mt-3">
            <button type="submit" class="btn btn-primary">
                <x-admin.icon name="save" :size="14" /> Simpan Semua Halaman + Blok
            </button>
        </div>
    </form>

    <x-admin.card title="Riwayat versi (10 terakhir)" icon="history" class="mt-3">
        @if (count($versions ?? []) > 0)
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th>Waktu</th>
                            <th>Aktor</th>
                            <th>Cuplikan</th>
                            <th class="text-end" style="width:130px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($versions as $i => $version)
                            @php
                                $pagesSnap = is_array($version['pages'] ?? null) ? $version['pages'] : [];
                                $first = '';
                                foreach ($pagesSnap as $v) {
                                    $plain = trim(strip_tags(is_array($v) ? json_encode($v) : (string) $v));
                                    if ($plain !== '') { $first = $plain; break; }
                                }
                                $blockCount = is_array($version['blocks'] ?? null) ? array_sum(array_map(fn ($b) => is_array($b) ? count($b) : 0, $version['blocks'])) : 0;
                            @endphp
                            <tr>
                                <td class="text-secondary">{{ $i }}</td>
                                <td class="fw-medium">{{ $version['at'] ?? '—' }}</td>
                                <td><span class="badge bg-secondary">admin #{{ $version['actor_id'] ?? '—' }}</span></td>
                                <td class="small text-secondary">{{ \Illuminate\Support\Str::limit($first, 120) ?: '—' }} @if($blockCount > 0)<span class="badge bg-info ms-1">{{ $blockCount }} blok</span>@endif</td>
                                <td class="text-end">
                                    @if (\Illuminate\Support\Facades\Route::has('admin.pages.restore'))
                                        <form method="POST" action="{{ route('admin.pages.restore') }}" onsubmit="return confirm('Pulihkan versi {{ $version['at'] ?? '' }}? Isi saat ini akan ditimpa dan snapshot baru dibuat.')">
                                            @csrf
                                            <input type="hidden" name="index" value="{{ $i }}">
                                            <button type="submit" class="btn btn-sm btn-outline-warning">Pulihkan</button>
                                        </form>
                                    @else
                                        <span class="badge bg-warning text-dark" title="Butuh wiring route POST admin.pages.restore">index {{ $i }} · perlu wiring route</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-secondary small mb-0 mt-2">Memulihkan versi menimpa seluruh halaman + blok, mencatat audit <code>cms.pages_restored</code>, dan membuat snapshot baru.</p>
        @else
            <p class="text-secondary small mb-0">Belum ada riwayat. Simpan halaman sekali untuk membuat snapshot pertama.</p>
        @endif
    </x-admin.card>

    <script>
    (function () {
        'use strict';
        function esc(s) {
            return String(s ?? '').replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }
        function blankBlock(type) {
            switch (type) {
                case 'hero': return { type: 'hero', title: '', subtitle: '', image: '', button_label: '', button_url: '' };
                case 'products': return { type: 'products', title: '', category_id: null, product_ids: [], limit: 4 };
                case 'gallery': return { type: 'gallery', title: '', images: [] };
                case 'faq': return { type: 'faq', title: 'Pertanyaan Umum', items: [{ q: '', a: '' }] };
                case 'cta': return { type: 'cta', title: '', subtitle: '', button_label: '', button_url: '' };
                default: return { type: 'text', html: '<p>Tulis teks di sini…</p>' };
            }
        }
        function field(label, inner) {
            return '<label class="form-label small mb-1 d-block">' + esc(label) + inner + '</label>';
        }
        function inputVal(name, val, ph) {
            return '<input type="text" class="form-control form-control-sm" data-f="' + esc(name) + '" value="' + esc(val ?? '') + '" placeholder="' + esc(ph || '') + '">';
        }
        function blockFields(block) {
            var h = '';
            if (block.type === 'text') {
                h += field('HTML teks (tag aman: p, a, img, ul, h1-h6)', '<textarea class="form-control form-control-sm" rows="4" data-f="html">' + esc(block.html || '') + '</textarea>');
            } else if (block.type === 'hero') {
                h += field('Judul', inputVal('title', block.title, 'Judul hero'));
                h += field('Subjudul', inputVal('subtitle', block.subtitle, 'Subjudul singkat'));
                h += field('URL gambar (https://, /path, atau #)', inputVal('image', block.image, 'https://…'));
                h += '<div class="row g-2"><div class="col">' + field('Teks tombol', inputVal('button_label', block.button_label, 'Lihat Produk')) + '</div><div class="col">' + field('URL tombol', inputVal('button_url', block.button_url, '/products')) + '</div></div>';
            } else if (block.type === 'products') {
                h += field('Judul blok', inputVal('title', block.title, 'Produk Pilihan'));
                h += '<div class="row g-2"><div class="col">' + field('ID kategori (opsional)', '<input type="number" min="0" class="form-control form-control-sm" data-f="category_id" value="' + esc(block.category_id ?? '') + '">') + '</div><div class="col">' + field('Jumlah tampil (1–12)', '<input type="number" min="1" max="12" class="form-control form-control-sm" data-f="limit" value="' + esc(block.limit ?? 4) + '">') + '</div></div>';
                h += field('ID produk koma-pisah (opsional, cth: 12, 34)', inputVal('product_ids', Array.isArray(block.product_ids) ? block.product_ids.join(', ') : (block.product_ids || ''), '12, 34'));
            } else if (block.type === 'gallery') {
                h += field('Judul galeri', inputVal('title', block.title, 'Galeri'));
                var imgs = Array.isArray(block.images) ? block.images.join('\n') : '';
                h += field('URL gambar (satu per baris, maks 12)', '<textarea class="form-control form-control-sm" rows="3" data-f="images">' + esc(imgs) + '</textarea>');
            } else if (block.type === 'faq') {
                h += field('Judul FAQ', inputVal('title', block.title, 'Pertanyaan Umum'));
                (block.items || []).forEach(function (item, idx) {
                    h += '<div class="border rounded p-2 mb-1" data-faq-item="' + idx + '">'
                        + field('Pertanyaan #' + (idx + 1), inputVal('q_' + idx, item.q, 'Bagaimana cara…'))
                        + field('Jawaban', '<textarea class="form-control form-control-sm" rows="2" data-f="a_' + idx + '">' + esc(item.a || '') + '</textarea>')
                        + '</div>';
                });
                h += '<button type="button" class="btn btn-sm btn-outline-secondary" data-faq-add>Tambah Tanya-Jawab</button>';
            } else if (block.type === 'cta') {
                h += field('Judul banner', inputVal('title', block.title, 'Promo Spesial!'));
                h += field('Subjudul', inputVal('subtitle', block.subtitle, 'Diskon s.d. 50% minggu ini'));
                h += '<div class="row g-2"><div class="col">' + field('Teks tombol', inputVal('button_label', block.button_label, 'Belanja Sekarang')) + '</div><div class="col">' + field('URL tombol', inputVal('button_url', block.button_url, '/products')) + '</div></div>';
            }
            return h;
        }
        function syncFromDom(wrap) {
            var blocks = [];
            wrap.querySelectorAll('[data-block]').forEach(function (card) {
                var type = card.getAttribute('data-block');
                var b = { type: type };
                card.querySelectorAll('[data-f]').forEach(function (el) {
                    var k = el.getAttribute('data-f');
                    if (k === 'images') {
                        b.images = el.value.split('\n').map(function (s) { return s.trim(); }).filter(Boolean).slice(0, 12);
                    } else if (k === 'category_id') {
                        b.category_id = el.value === '' ? null : parseInt(el.value, 10);
                    } else if (k === 'limit') {
                        b.limit = Math.min(12, Math.max(1, parseInt(el.value, 10) || 4));
                    } else if (k === 'product_ids') {
                        b.product_ids = el.value.split(',').map(function (s) { return parseInt(s.trim(), 10); }).filter(function (n) { return n > 0; });
                    } else if (k.indexOf('q_') === 0 || k.indexOf('a_') === 0) {
                        // handled below
                    } else {
                        b[k] = el.value;
                    }
                });
                if (type === 'faq') {
                    var items = [];
                    card.querySelectorAll('[data-faq-item]').forEach(function (row) {
                        var qi = row.querySelector('[data-f^="q_"]');
                        var ai = row.querySelector('[data-f^="a_"]');
                        if (qi && qi.value.trim() !== '') items.push({ q: qi.value.trim(), a: ai ? ai.value.trim() : '' });
                    });
                    b.items = items.slice(0, 20);
                    b.title = (card.querySelector('[data-f="title"]') || {}).value || '';
                }
                blocks.push(b);
            });
            var input = wrap.parentElement.querySelector('[data-blocks-input]') || document.querySelector('input[data-blocks-input][name="blocks[' + wrap.getAttribute('data-key') + ']"]');
            if (input) input.value = JSON.stringify(blocks);
            var count = wrap.parentElement.querySelector('[data-blocks-count]');
            if (count) count.textContent = blocks.length + ' blok';
            renderPreview(wrap, blocks);
            return blocks;
        }
        function renderPreview(wrap, blocks) {
            var card = wrap.closest('.card, .x-admin-card') || wrap.parentElement;
            var prev = card ? card.querySelector('[data-blocks-preview]') : null;
            if (!prev) return;
            if (!blocks.length) { prev.innerHTML = '<span class="text-secondary small">Belum ada blok — isi HTML lama yang akan tampil.</span>'; return; }
            prev.innerHTML = blocks.map(function (b, i) {
                var sum = b.title || b.button_label || (b.html ? b.html.replace(/<[^>]+>/g, ' ').trim().slice(0, 60) : '') || (b.items ? b.items.length + ' tanya-jawab' : '') || (b.images ? b.images.length + ' gambar' : '');
                return '<span class="badge bg-primary me-1 mb-1">#' + (i + 1) + ' ' + esc(b.type) + (sum ? ': ' + esc(String(sum).slice(0, 50)) : '') + '</span>';
            }).join('');
        }
        document.querySelectorAll('[data-blocks-list]').forEach(function (list) {
            var key = list.getAttribute('data-key');
            var input = document.querySelector('input[data-blocks-input][name="blocks[' + key + ']"]');
            var blocks = [];
            try { blocks = JSON.parse(input ? input.value : '[]') || []; } catch (e) { blocks = []; }
            function paint() {
                list.innerHTML = '';
                blocks.forEach(function (b, idx) {
                    var card = document.createElement('div');
                    card.className = 'card card-body py-2';
                    card.setAttribute('data-block', b.type);
                    card.setAttribute('draggable', 'true');
                    card.innerHTML = '<div class="d-flex align-items-center gap-2 mb-2">'
                        + '<span class="badge bg-primary">' + esc(b.type) + '</span>'
                        + '<span class="text-secondary small">#' + (idx + 1) + '</span>'
                        + '<span class="ms-auto btn-group btn-group-sm" role="group">'
                        + '<button type="button" class="btn btn-outline-secondary" data-move="-1" title="Naik">↑</button>'
                        + '<button type="button" class="btn btn-outline-secondary" data-move="1" title="Turun">↓</button>'
                        + '<button type="button" class="btn btn-outline-danger" data-del>Buang</button>'
                        + '</span></div>'
                        + '<div>' + blockFields(b) + '</div>';
                    // input events
                    card.addEventListener('input', function () { blocks = syncFromDom(list); });
                    card.querySelector('[data-del]').addEventListener('click', function () { blocks.splice(idx, 1); paint(); blocks = syncFromDom(list); });
                    card.querySelectorAll('[data-move]').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            var j = idx + parseInt(btn.getAttribute('data-move'), 10);
                            if (j < 0 || j >= blocks.length) return;
                            var t = blocks[idx]; blocks[idx] = blocks[j]; blocks[j] = t;
                            paint(); blocks = syncFromDom(list);
                        });
                    });
                    var faqAdd = card.querySelector('[data-faq-add]');
                    if (faqAdd) faqAdd.addEventListener('click', function () { b.items = (b.items || []).concat([{ q: '', a: '' }]); paint(); });
                    // drag & drop HTML5
                    card.addEventListener('dragstart', function (e) { e.dataTransfer.setData('text/plain', String(idx)); card.classList.add('opacity-50'); });
                    card.addEventListener('dragend', function () { card.classList.remove('opacity-50'); });
                    card.addEventListener('dragover', function (e) { e.preventDefault(); });
                    card.addEventListener('drop', function (e) {
                        e.preventDefault();
                        var from = parseInt(e.dataTransfer.getData('text/plain'), 10);
                        if (isNaN(from) || from === idx) return;
                        var moved = blocks.splice(from, 1)[0];
                        blocks.splice(idx, 0, moved);
                        paint(); blocks = syncFromDom(list);
                    });
                    list.appendChild(card);
                });
                blocks = syncFromDom(list);
            }
            var editorBar = document.querySelector('[data-blocks-editor="' + key + '"]');
            if (editorBar) {
                editorBar.querySelectorAll('[data-add-block]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (blocks.length >= 30) { alert('Maksimal 30 blok per halaman.'); return; }
                        blocks.push(blankBlock(btn.getAttribute('data-add-block')));
                        paint();
                    });
                });
            }
            paint();
        });
    })();
    </script>
@endsection
