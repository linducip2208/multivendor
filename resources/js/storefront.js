/*
|--------------------------------------------------------------------------
| Storefront behaviour
|--------------------------------------------------------------------------
|
| Vanilla ES module. No framework, no jQuery, no Bootstrap JS. Everything is
| progressive: each feature is a self-contained module that no-ops when its
| markup is absent, so pages that do not use a feature pay nothing for it.
|
*/

const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/* ------------------------------------------------------------------ *
 * Tiny DOM helpers
 * ------------------------------------------------------------------ */

const $ = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

const escapeHtml = (value) =>
    String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

/* ------------------------------------------------------------------ *
 * Toasts
 * ------------------------------------------------------------------ */

function toastHost() {
    let host = $('.sf-toasts');
    if (!host) {
        host = document.createElement('div');
        host.className = 'sf-toasts';
        host.setAttribute('role', 'status');
        host.setAttribute('aria-live', 'polite');
        document.body.appendChild(host);
    }
    return host;
}

export function toast(message, type = 'info', timeout = 4000) {
    const el = document.createElement('div');
    el.className = `sf-toast sf-toast--${type}`;
    el.innerHTML = `<span>${escapeHtml(message)}</span>`;
    toastHost().appendChild(el);
    setTimeout(() => el.remove(), timeout);
}

window.sfToast = toast;

/* ------------------------------------------------------------------ *
 * Search autocomplete
 * ------------------------------------------------------------------ */

function initSearch() {
    const root = $$('[data-sf-search]');
    if (!root.length) return;

    root.forEach((box) => {
        const input = $('[data-sf-search-input]', box);
        const panel = $('[data-sf-search-panel]', box);
        const clear = $('[data-sf-search-clear]', box);
        if (!input || !panel) return;

        let timer = null;
        let controller = null;
        let cursor = -1;
        let lastQuery = '';

        const setOpen = (open) => {
            panel.hidden = !open;
            if (open) box.classList.add('is-open');
            else box.classList.remove('is-open');
        };

        const render = (payload) => {
            const products = payload?.products ?? [];
            const categories = payload?.categories ?? [];
            const shops = payload?.shops ?? [];
            const terms = payload?.terms ?? [];

            if (!products.length && !categories.length && !shops.length && !terms.length) {
                panel.innerHTML = `<div class="sf-suggest__empty">Tidak ada hasil untuk &ldquo;${escapeHtml(input.value)}&rdquo;</div>`;
                setOpen(true);
                return;
            }

            let html = '';

            if (terms.length) {
                html += `<div class="sf-suggest__group-label">Saran pencarian</div>`;
                terms.forEach((t) => {
                    html += `<a class="sf-suggest__item" href="${escapeHtml(t.url)}"><span class="sf-suggest__name">${escapeHtml(t.label)}</span></a>`;
                });
            }

            if (categories.length) {
                html += `<div class="sf-suggest__group-label">Kategori</div>`;
                categories.forEach((c) => {
                    html += `<a class="sf-suggest__item" href="${escapeHtml(c.url)}"><span class="sf-suggest__name">${escapeHtml(c.name)}</span><span class="sf-suggest__meta">${escapeHtml(c.product_count ?? 0)} produk</span></a>`;
                });
            }

            if (shops.length) {
                html += `<div class="sf-suggest__group-label">Toko</div>`;
                shops.forEach((s) => {
                    html += `<a class="sf-suggest__item" href="${escapeHtml(s.url)}"><span class="sf-suggest__name">${escapeHtml(s.name)}</span><span class="sf-suggest__meta">Toko</span></a>`;
                });
            }

            if (products.length) {
                html += `<div class="sf-suggest__group-label">Produk</div>`;
                products.forEach((p) => {
                    const img = p.thumbnail
                        ? `<img src="${escapeHtml(p.thumbnail)}" alt="" loading="lazy" width="40" height="40">`
                        : `<span class="sf-suggest__item" style="width:40px;height:40px;border-radius:6px;background:var(--sf-bg-muted)"></span>`;
                    html += `<a class="sf-suggest__item" href="${escapeHtml(p.url)}">${img}<span class="sf-suggest__name">${escapeHtml(p.name)}${p.shop_name ? `<br><span class="sf-suggest__meta">${escapeHtml(p.shop_name)}</span>` : ''}</span><span class="sf-suggest__price">${escapeHtml(p.price_label)}</span></a>`;
                });
            }

            panel.innerHTML = html;
            setOpen(true);
        };

        const run = async () => {
            const q = input.value.trim();
            if (q.length < 2) {
                setOpen(false);
                return;
            }
            if (q === lastQuery) return;
            lastQuery = q;

            controller?.abort();
            controller = new AbortController();

            try {
                const res = await fetch(`/search/suggest?q=${encodeURIComponent(q)}&limit=6`, {
                    signal: controller.signal,
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok) throw new Error('suggest failed');
                render(await res.json());
            } catch (err) {
                if (err.name !== 'AbortError') setOpen(false);
            }
        };

        input.addEventListener('input', () => {
            box.classList.toggle('is-filled', input.value.length > 0);
            clearTimeout(timer);
            timer = setTimeout(run, 220);
        });

        input.addEventListener('focus', () => {
            if (input.value.trim().length >= 2) run();
        });

        input.addEventListener('keydown', (e) => {
            const items = $$('.sf-suggest__item', panel);
            if (!items.length) return;

            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                cursor = (cursor + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
                items.forEach((it, i) => it.setAttribute('aria-selected', i === cursor ? 'true' : 'false'));
                items[cursor]?.scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter' && cursor > -1) {
                e.preventDefault();
                items[cursor].click();
            } else if (e.key === 'Escape') {
                setOpen(false);
                input.blur();
            }
        });

        clear?.addEventListener('click', () => {
            input.value = '';
            input.focus();
            box.classList.remove('is-filled');
            setOpen(false);
        });

        document.addEventListener('click', (e) => {
            if (!box.contains(e.target)) setOpen(false);
        });
    });
}

/* ------------------------------------------------------------------ *
 * Mobile drawer (header nav + mobile filter sheet)
 * ------------------------------------------------------------------ */

function initDrawers() {
    $$('[data-sf-drawer]').forEach((drawer) => {
        const panel = $('[data-sf-drawer-panel]', drawer);
        const scrim = $('[data-sf-drawer-scrim]', drawer);

        const open = () => {
            drawer.setAttribute('aria-hidden', 'false');
            document.body.classList.add('is-locked');
            panel?.setAttribute('tabindex', '-1');
            panel?.focus({ preventScroll: true });
        };
        const close = () => {
            drawer.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('is-locked');
        };

        $$(`[data-sf-drawer-open="${drawer.dataset.sfDrawer}"]`).forEach((btn) =>
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                open();
            })
        );
        $$(`[data-sf-drawer-close="${drawer.dataset.sfDrawer}"]`).forEach((btn) => btn.addEventListener('click', close));
        scrim?.addEventListener('click', close);

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && drawer.getAttribute('aria-hidden') === 'false') close();
        });
    });
}

/* ------------------------------------------------------------------ *
 * Quantity steppers
 * ------------------------------------------------------------------ */

function initQuantity() {
    $$('[data-sf-qty]').forEach((box) => {
        const input = $('input', box);
        if (!input) return;
        const min = parseInt(input.min || '1', 10);
        const max = input.max && input.max !== '' ? parseInt(input.max, 10) : Infinity;

        const sync = () => {
            let v = parseInt(input.value || String(min), 10);
            if (Number.isNaN(v)) v = min;
            v = Math.min(Math.max(v, min), max);
            input.value = v;
            const dec = $('[data-qty-dec]', box);
            const inc = $('[data-qty-inc]', box);
            if (dec) dec.disabled = v <= min;
            if (inc) inc.disabled = v >= max;
            input.dispatchEvent(new CustomEvent('sf:qty', { bubbles: true, detail: { value: v } }));
        };

        $('[data-qty-dec]', box)?.addEventListener('click', () => {
            input.stepDown();
            sync();
        });
        $('[data-qty-inc]', box)?.addEventListener('click', () => {
            input.stepUp();
            sync();
        });
        input.addEventListener('change', sync);
        sync();
    });
}

/* ------------------------------------------------------------------ *
 * Product gallery + lightbox
 * ------------------------------------------------------------------ */

function initGallery() {
    const main = $('[data-sf-gallery-main]');
    if (!main) return;
    const thumbs = $$('[data-sf-gallery-thumb]');
    const lightbox = $('[data-sf-lightbox]');
    const lightboxImg = $('img', lightbox ?? document.createElement('div'));

    const select = (src, current) => {
        main.querySelector('img')?.setAttribute('src', src);
        thumbs.forEach((t) => t.setAttribute('aria-current', t === current ? 'true' : 'false'));
    };

    thumbs.forEach((thumb) => {
        thumb.addEventListener('click', () => select(thumb.dataset.src, thumb));
        thumb.addEventListener('keydown', (e) => {
            if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
            e.preventDefault();
            const idx = thumbs.indexOf(thumb);
            const next = thumbs[(idx + (e.key === 'ArrowRight' ? 1 : thumbs.length - 1)) % thumbs.length];
            next.focus();
            select(next.dataset.src, next);
        });
    });

    const openLightbox = () => {
        if (!lightbox) return;
        if (lightboxImg) lightboxImg.src = main.querySelector('img')?.src ?? '';
        lightbox.hidden = false;
        document.body.classList.add('is-locked');
        lightbox.querySelector('[data-lightbox-close]')?.focus();
    };
    const closeLightbox = () => {
        if (!lightbox) return;
        lightbox.hidden = true;
        document.body.classList.remove('is-locked');
    };

    main.addEventListener('click', openLightbox);
    main.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            openLightbox();
        }
    });
    lightbox?.addEventListener('click', (e) => {
        if (e.target === lightbox || e.target.closest('[data-lightbox-close]')) closeLightbox();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeLightbox();
    });
}

/* ------------------------------------------------------------------ *
 * Tabs
 * ------------------------------------------------------------------ */

function initTabs() {
    $$('[data-sf-tabs]').forEach((group) => {
        const tabs = $$('[role="tab"]', group);
        const select = (tab) => {
            tabs.forEach((t) => {
                const on = t === tab;
                t.setAttribute('aria-selected', on ? 'true' : 'false');
                t.setAttribute('tabindex', on ? '0' : '-1');
                const panel = document.getElementById(t.getAttribute('aria-controls'));
                if (panel) panel.hidden = !on;
            });
        };

        tabs.forEach((tab, i) => {
            tab.addEventListener('click', () => select(tab));
            tab.addEventListener('keydown', (e) => {
                const map = { ArrowRight: 1, ArrowLeft: -1, Home: 'first', End: 'last' };
                if (!(e.key in map)) return;
                e.preventDefault();
                const target =
                    map[e.key] === 'first' ? tabs[0]
                    : map[e.key] === 'last' ? tabs[tabs.length - 1]
                    : tabs[(i + map[e.key] + tabs.length) % tabs.length];
                target.focus();
                select(target);
            });
        });
    });
}

/* ------------------------------------------------------------------ *
 * Variant selection
 * ------------------------------------------------------------------ */

function initVariants() {
    const root = $('[data-sf-variants]');
    if (!root) return;

    let payload = {};
    try {
        payload = JSON.parse(root.dataset.sfVariants || '{}');
    } catch {
        payload = {};
    }
    if (payload === null || typeof payload !== 'object') payload = {};
    const qtyInput = $('[data-sf-qty-input]');
    const priceEl = $('[data-sf-variant-price]');
    const stockEl = $('[data-sf-variant-stock]');
    const addBtn = $('[data-sf-add-cart]');
    const selected = {};

    const sync = () => {
        const match = (payload.variants ?? []).find((v) =>
            (v.attributes ?? []).every((a) => selected[a.name] === a.value)
        );

        if (priceEl) priceEl.textContent = match?.price_label ?? payload.base_price_label ?? '';
        if (stockEl) {
            const stock = match ? match.stock : (payload.base_stock ?? 0);
            stockEl.textContent = match
                ? (stock > 0 ? `Stok: ${stock}` : 'Stok habis')
                : payload.base_stock_label ?? '';
            stockEl.className = `sf-stock ${stock > 0 ? (stock <= 5 ? 'sf-stock--low' : 'sf-stock--in') : 'sf-stock--out'}`;
        }
        if (qtyInput) {
            const max = match ? match.stock : (payload.base_max_qty ?? 99);
            qtyInput.max = String(max);
            if (parseInt(qtyInput.value, 10) > max) qtyInput.value = String(Math.max(1, max));
        }
        if (addBtn) {
            addBtn.disabled = !match || match.stock < 1;
            if (match) addBtn.dataset.variantId = String(match.id);
        }
        if (window.history.replaceState) {
            const url = new URL(window.location.href);
            const keys = Object.keys(selected);
            if (keys.length) url.searchParams.set('v', keys.map((k) => `${k}:${selected[k]}`).join(','));
            else url.searchParams.delete('v');
            window.history.replaceState({}, '', url);
        }
    };

    $$('[data-sf-variant-group]', root).forEach((groupEl) => {
        const name = groupEl.dataset.sfVariantGroup;
        $$('[data-sf-variant-opt]', groupEl).forEach((btn) => {
            btn.addEventListener('click', () => {
                const value = btn.dataset.sfVariantOpt;
                selected[name] = selected[name] === value ? undefined : value;
                if (selected[name] === undefined) delete selected[name];
                $$(`[data-sf-variant-opt]`, groupEl).forEach((b) =>
                    b.setAttribute('aria-pressed', b === btn && selected[name] === value ? 'true' : 'false')
                );
                sync();
            });
        });
    });

    // Pre-select from ?v=Name:Value,Name2:Value2
    const preset = new URLSearchParams(window.location.search).get('v');
    if (preset) {
        preset.split(',').forEach((pair) => {
            const idx = pair.indexOf(':');
            if (idx > 0) selected[pair.slice(0, idx)] = pair.slice(idx + 1);
        });
    }

    sync();
}

/* ------------------------------------------------------------------ *
 * Wishlist toggle (works signed-out by prompting login)
 * ------------------------------------------------------------------ */

function initWishlist() {
    $$('[data-sf-wishlist]').forEach((btn) => {
        if (btn.dataset.sfWishlistBooted === '1') return;
        btn.dataset.sfWishlistBooted = '1';
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            if (btn.dataset.signedOut === '1') {
                toast('Masuk terlebih dahulu untuk menyimpan favorit.', 'info');
                return;
            }
            const url = btn.dataset.sfWishlist;
            if (!url) {
                toast('Tautan favorit tidak tersedia.', 'error');
                return;
            }
            btn.disabled = true;
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok) {
                    toast(json.message ?? 'Gagal memperbarui favorit.', 'error');
                    return;
                }
                if (json.active) {
                    btn.setAttribute('aria-pressed', 'true');
                    toast(json.message ?? 'Ditambahkan ke favorit.', 'success');
                } else {
                    btn.setAttribute('aria-pressed', 'false');
                    toast(json.message ?? 'Dihapus dari favorit.', 'info');
                }
            } catch {
                toast('Gagal memperbarui favorit.', 'error');
            } finally {
                btn.disabled = false;
            }
        });
    });
}

/* ------------------------------------------------------------------ *
 * Add to cart
 * ------------------------------------------------------------------ */

function initAddToCart() {
    $$('[data-sf-add-cart-form]').forEach((form) => {
        if (form.dataset.sfCartBooted === '1') return;
        form.dataset.sfCartBooted = '1';
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (form.dataset.signedOut === '1') {
                toast('Masuk terlebih dahulu untuk menambahkan produk.', 'info');
                return;
            }

            const btn = $('[type="submit"]', form);
            const original = btn?.innerHTML;
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Menambahkan…';
            }

            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const json = await res.json().catch(() => ({}));

                if (!res.ok) {
                    toast(json.message ?? 'Gagal menambahkan produk.', 'error');
                    return;
                }

                toast(json.message ?? 'Produk ditambahkan ke keranjang.', 'success');
                if (json.cart_count !== undefined) {
                    $$('[data-cart-count]').forEach((el) => {
                        el.textContent = json.cart_count;
                        el.hidden = Number(json.cart_count) === 0;
                    });
                }
                if (form.dataset.redirect === '1') {
                    window.location.href = form.dataset.redirectUrl ?? '/cart';
                }
            } catch {
                toast('Gagal menambahkan produk.', 'error');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = original;
                }
            }
        });
    });
}

/* ------------------------------------------------------------------ *
 * Countdown timers
 * ------------------------------------------------------------------ */

function initCountdowns() {
    const tick = () => {
        $$('[data-sf-countdown]').forEach((el) => {
            const end = new Date(el.dataset.sfCountdown).getTime();
            if (Number.isNaN(end)) return;
            let diff = Math.max(0, Math.floor((end - Date.now()) / 1000));
            if (diff === 0) {
                el.innerHTML = '<span class="sf-countdown__label">Berakhir</span>';
                return;
            }
            const d = Math.floor(diff / 86400);
            const h = Math.floor((diff % 86400) / 3600);
            const m = Math.floor((diff % 3600) / 60);
            const s = diff % 60;
            el.innerHTML = [
                d > 0 ? `<span class="sf-countdown__box"><span class="sf-countdown__value">${d}</span><span class="sf-countdown__label">Hari</span></span>` : '',
                `<span class="sf-countdown__box"><span class="sf-countdown__value">${String(h).padStart(2, '0')}</span><span class="sf-countdown__label">Jam</span></span>`,
                `<span class="sf-countdown__box"><span class="sf-countdown__value">${String(m).padStart(2, '0')}</span><span class="sf-countdown__label">Menit</span></span>`,
                `<span class="sf-countdown__box"><span class="sf-countdown__value">${String(s).padStart(2, '0')}</span><span class="sf-countdown__label">Detik</span></span>`,
            ].join('');
        });
    };
    tick();
    setInterval(tick, 1000);
}

/* ------------------------------------------------------------------ *
 * Carousel arrows
 * ------------------------------------------------------------------ */

function initCarousels() {
    $$('[data-sf-carousel]').forEach((root) => {
        const track = $('[data-sf-carousel-track]', root);
        const step = () => (track?.firstElementChild?.getBoundingClientRect().width ?? 240) + 18;
        $('[data-carousel-prev]', root)?.addEventListener('click', () => track?.scrollBy({ left: -step(), behavior: 'smooth' }));
        $('[data-carousel-next]', root)?.addEventListener('click', () => track?.scrollBy({ left: step(), behavior: 'smooth' }));
    });
}

/* ------------------------------------------------------------------ *
 * Facet accordions
 * ------------------------------------------------------------------ */

function initFacets() {
    $$('[data-facet-toggle]').forEach((btn) => {
        const body = document.getElementById(btn.getAttribute('aria-controls'));
        btn.addEventListener('click', () => {
            const open = btn.getAttribute('aria-expanded') === 'true';
            btn.setAttribute('aria-expanded', open ? 'false' : 'true');
            if (body) body.hidden = open;
        });
    });

    // Auto-submit filter form when a checkbox/radio changes.
    const form = $('[data-sf-filter-form]');
    if (form) {
        $$('input[type="checkbox"], input[type="radio"], select', form).forEach((input) => {
            input.addEventListener('change', () => form.submit());
        });
    }
}

/* ------------------------------------------------------------------ *
 * Theme toggle (storefront respects system preference by default)
 * ------------------------------------------------------------------ */

function initTheme() {
    const KEY = 'sf-theme';
    const stored = localStorage.getItem(KEY);
    if (stored === 'dark' || stored === 'light') {
        document.documentElement.setAttribute('data-theme', stored);
    } else if (window.matchMedia?.('(prefers-color-scheme: dark)').matches) {
        document.documentElement.setAttribute('data-theme', 'dark');
    }

    $$('[data-sf-theme-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', current);
            localStorage.setItem(KEY, current);
        });
    });
}

/* ------------------------------------------------------------------ *
 * Copy to clipboard (order numbers, vouchers)
 * ------------------------------------------------------------------ */

function initCopy() {
    $$('[data-sf-copy]').forEach((btn) => {
        if (btn.dataset.sfCopyBooted === '1') return;
        btn.dataset.sfCopyBooted = '1';
        btn.addEventListener('click', async () => {
            const text = btn.dataset.sfCopy ?? '';
            if (!text) {
                toast('Tidak ada teks untuk disalin.', 'error');
                return;
            }
            try {
                await navigator.clipboard.writeText(text);
                const old = btn.textContent;
                btn.textContent = 'Tersalin';
                setTimeout(() => (btn.textContent = old), 1600);
            } catch {
                toast('Gagal menyalin.', 'error');
            }
        });
    });
}

/* ------------------------------------------------------------------ *
 * Confirm-before-submit (destructive actions)
 * ------------------------------------------------------------------ */

function initConfirms() {
    document.addEventListener('submit', (e) => {
        const message = e.target.dataset?.sfConfirm;
        if (message && !window.confirm(message)) e.preventDefault();
    });
}

/* ------------------------------------------------------------------ *
 * Product grid loading state (filter submit -> skeleton)
 * ------------------------------------------------------------------ */

function initProductGridLoading() {
    const grid = $('[data-sf-product-grid]');
    const loading = $('[data-sf-product-grid-loading]');
    const form = $('[data-sf-filter-form]');
    if (!grid || !loading || !form) return;

    form.addEventListener('submit', () => {
        grid.hidden = true;
        loading.hidden = false;
    });
}

/* ------------------------------------------------------------------ *
 * Boot
 * ------------------------------------------------------------------ */

function boot() {
    if (window.__sfBooted) return;
    window.__sfBooted = true;
    initTheme();
    initSearch();
    initDrawers();
    initQuantity();
    initGallery();
    initTabs();
    initVariants();
    initWishlist();
    initAddToCart();
    initCountdowns();
    initCarousels();
    initFacets();
    initProductGridLoading();
    initCopy();
    initConfirms();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
