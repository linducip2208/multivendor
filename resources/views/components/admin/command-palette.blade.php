@props(['items' => [], 'placeholder' => 'Ketik untuk mencari halaman...', 'hotkey' => 'K'])

@php
    $entries = [];
    foreach ((array) $items as $item) {
        if (! is_array($item) || ($item['url'] ?? '') === '' || ($item['label'] ?? '') === '') {
            continue;
        }
        $entries[] = [
            'label' => (string) $item['label'],
            'url' => (string) $item['url'],
            'group' => (string) ($item['group'] ?? ''),
            'svg' => \App\Support\Icons::path((string) ($item['icon'] ?? 'circle')),
        ];
    }

    $payload = $entries === []
        ? ''
        : base64_encode((string) json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
@endphp

<div
    class="admin-command"
    data-command-palette
    data-command-items="{{ $payload }}"
>
    <div class="admin-command__backdrop" data-command-backdrop hidden></div>

    <div class="admin-command__panel" role="dialog" aria-modal="true" aria-labelledby="admin-command-label" hidden>
        <div class="admin-command__search">
            <x-admin.icon name="search" :size="20" class="text-secondary flex-shrink-0" />
            <label class="visually-hidden" id="admin-command-label" for="admin-command-input">{{ $placeholder }}</label>
            <input
                type="text"
                id="admin-command-input"
                class="admin-command__input"
                placeholder="{{ $placeholder }}"
                autocomplete="off"
                spellcheck="false"
                role="combobox"
                aria-expanded="true"
                aria-controls="admin-command-results"
                aria-autocomplete="list"
                data-command-input
            >
            <kbd class="admin-command__kbd d-none d-sm-inline-block">{{ $hotkey }}</kbd>
            <button type="button" class="btn btn-ghost-light btn-sm" data-command-close aria-label="Tutup pencarian">
                <x-admin.icon name="x" :size="18" />
            </button>
        </div>

        <div
            class="admin-command__results"
            id="admin-command-results"
            role="listbox"
            aria-label="Hasil pencarian"
            data-command-results
        >
            <p class="admin-command__empty" data-command-empty>Tidak ada halaman yang cocok.</p>
        </div>

        <div class="admin-command__footer d-none d-md-flex">
            <span><kbd>&uarr;</kbd> <kbd>&darr;</kbd> navigasi</span>
            <span><kbd>Enter</kbd> buka</span>
            <span><kbd>Esc</kbd> tutup</span>
        </div>
    </div>
</div>

@once
    <script>
        (function () {
            function escapeHtml(value) {
                return String(value == null ? '' : value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function decodeItems(root) {
                try {
                    var payload = root.getAttribute('data-command-items') || '';
                    if (payload === '') {
                        return [];
                    }
                    var parsed = JSON.parse(window.atob(payload));
                    return Array.isArray(parsed) ? parsed : [];
                } catch (error) {
                    return [];
                }
            }

            function boot() {
                var root = document.querySelector('[data-command-palette]');
                if (!root || root.__adminBooted === true) {
                    return;
                }
                root.__adminBooted = true;

                var items = decodeItems(root);
                var panel = root.querySelector('.admin-command__panel');
                var backdrop = root.querySelector('[data-command-backdrop]');
                var input = root.querySelector('[data-command-input]');
                var results = root.querySelector('[data-command-results]');
                var emptyState = root.querySelector('[data-command-empty]');
                var closeButton = root.querySelector('[data-command-close]');
                var activeIndex = 0;
                var lastFocused = null;

                function isOpen() {
                    return panel.hidden === false;
                }

                function close() {
                    panel.hidden = true;
                    backdrop.hidden = true;
                    root.classList.remove('is-open');
                    if (lastFocused && typeof lastFocused.focus === 'function') {
                        lastFocused.focus();
                    }
                }

                function options() {
                    return results.querySelectorAll('.admin-command__item');
                }

                function setActive(index, scroll) {
                    var list = options();
                    if (list.length === 0) {
                        return;
                    }
                    for (var i = 0; i < list.length; i += 1) {
                        list[i].classList.remove('is-active');
                        list[i].setAttribute('aria-selected', 'false');
                    }
                    activeIndex = Math.max(0, Math.min(index, list.length - 1));
                    list[activeIndex].classList.add('is-active');
                    list[activeIndex].setAttribute('aria-selected', 'true');
                    if (scroll === true && typeof list[activeIndex].scrollIntoView === 'function') {
                        list[activeIndex].scrollIntoView({ block: 'nearest' });
                    }
                }

                function render(list) {
                    activeIndex = 0;

                    while (results.firstChild) {
                        results.removeChild(results.firstChild);
                    }

                    if (list.length === 0) {
                        emptyState.hidden = false;
                        results.appendChild(emptyState);
                        return;
                    }

                    emptyState.hidden = true;

                    var currentGroup = null;
                    var index = 0;

                    list.forEach(function (item) {
                        if (item.group && item.group !== currentGroup) {
                            currentGroup = item.group;
                            var heading = document.createElement('div');
                            heading.className = 'admin-command__group';
                            heading.textContent = currentGroup;
                            results.appendChild(heading);
                        }

                        var option = document.createElement('a');
                        option.className = 'admin-command__item';
                        option.setAttribute('role', 'option');
                        option.setAttribute('aria-selected', 'false');
                        option.setAttribute('data-index', String(index));
                        option.setAttribute('href', item.url);
                        option.innerHTML =
                            '<span class="admin-command__icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
                            'stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' +
                            escapeHtml(item.svg) +
                            '</svg></span><span class="admin-command__label">' +
                            escapeHtml(item.label) +
                            '</span><span class="admin-command__group-name">' +
                            escapeHtml(item.group) +
                            '</span>';

                        option.addEventListener('mouseenter', function () {
                            setActive(index, false);
                        });

                        results.appendChild(option);
                        index += 1;
                    });

                    setActive(0, true);
                }

                function filter(term) {
                    var needle = String(term || '').trim().toLowerCase();

                    if (needle === '') {
                        render(items);
                        return;
                    }

                    render(
                        items.filter(function (item) {
                            return (
                                String(item.label).toLowerCase().indexOf(needle) !== -1 ||
                                String(item.group || '').toLowerCase().indexOf(needle) !== -1
                            );
                        })
                    );
                }

                function open() {
                    if (items.length === 0) {
                        return;
                    }
                    lastFocused = document.activeElement;
                    panel.hidden = false;
                    backdrop.hidden = false;
                    root.classList.add('is-open');
                    input.value = '';
                    render(items);
                    window.setTimeout(function () {
                        input.focus();
                    }, 10);
                }

                if (closeButton) {
                    closeButton.addEventListener('click', close);
                }
                if (backdrop) {
                    backdrop.addEventListener('click', close);
                }

                input.addEventListener('input', function () {
                    filter(input.value);
                });

                input.addEventListener('keydown', function (event) {
                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        setActive(activeIndex + 1, true);
                    } else if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        setActive(activeIndex - 1, true);
                    } else if (event.key === 'Enter') {
                        event.preventDefault();
                        var target = options()[activeIndex];
                        if (target) {
                            window.location.href = target.getAttribute('href');
                        }
                    } else if (event.key === 'Escape') {
                        event.preventDefault();
                        close();
                    }
                });

                document.addEventListener('keydown', function (event) {
                    if ((event.ctrlKey || event.metaKey) && String(event.key).toLowerCase() === 'k') {
                        event.preventDefault();
                        if (isOpen()) {
                            close();
                        } else {
                            open();
                        }
                        return;
                    }

                    if (event.key === 'Escape' && isOpen()) {
                        close();
                    }
                });

                document.addEventListener('click', function (event) {
                    var trigger = event.target.closest('[data-command-toggle]');
                    if (!trigger) {
                        return;
                    }
                    event.preventDefault();
                    if (isOpen()) {
                        close();
                    } else {
                        open();
                    }
                });

                window.__adminCommandPalette = { open: open, close: close, count: items.length };
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', boot);
            } else {
                boot();
            }
        })();
    </script>
@endonce
