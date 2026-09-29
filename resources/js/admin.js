const THEME_KEY = 'admin-theme';
const DENSITY_KEY = 'admin-density';
const MOBILE_BREAKPOINT = 992;

function onReady(callback) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback, { once: true });
    } else {
        callback();
    }
}

function isMobile() {
    return window.matchMedia(`(max-width: ${MOBILE_BREAKPOINT - 0.02}px)`).matches;
}

function decodePayload(value) {
    if (!value) {
        return null;
    }

    try {
        return JSON.parse(atob(value));
    } catch (error) {
        return null;
    }
}

function parseJson(value, fallback) {
    if (!value) {
        return fallback;
    }

    try {
        return JSON.parse(value);
    } catch (error) {
        return fallback;
    }
}

function initSidebar() {
    const sidebar = document.querySelector('[data-sidebar]');
    const scrim = document.querySelector('[data-sidebar-scrim]');
    const triggers = document.querySelectorAll('[data-sidebar-toggle]');

    if (!sidebar) {
        return;
    }

    const setOpen = (open) => {
        sidebar.classList.toggle('is-open', open);
        document.body.classList.toggle('admin-sidebar-open', open);
        triggers.forEach((trigger) => trigger.setAttribute('aria-expanded', open ? 'true' : 'false'));

        if (scrim) {
            scrim.hidden = !open;
        }

        if (open) {
            const first = sidebar.querySelector('a, button');
            if (first && typeof first.focus === 'function') {
                window.setTimeout(() => first.focus(), 60);
            }
        }
    };

    const close = () => setOpen(false);

    triggers.forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            setOpen(!sidebar.classList.contains('is-open'));
        });
    });

    if (scrim) {
        scrim.addEventListener('click', close);
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && sidebar.classList.contains('is-open')) {
            close();
        }
    });

    sidebar.addEventListener('click', (event) => {
        if (event.target.closest('a.nav-link, a.dropdown-item') && isMobile()) {
            close();
        }
    });

    window.addEventListener('resize', () => {
        if (!isMobile()) {
            close();
        }
    });

    setOpen(false);
}

function syncThemeIcons(theme) {
    const light = document.querySelector('[data-theme-icon-light]');
    const dark = document.querySelector('[data-theme-icon-dark]');

    if (light) {
        light.classList.toggle('d-none', theme === 'dark');
    }

    if (dark) {
        dark.classList.toggle('d-none', theme !== 'dark');
    }
}

function initTheme() {
    const root = document.documentElement;

    const apply = (theme) => {
        root.setAttribute('data-bs-theme', theme);
        root.style.colorScheme = theme;
        syncThemeIcons(theme);

        const meta = document.querySelector('meta[name="theme-color"]');
        const primary = getComputedStyle(root).getPropertyValue('--tblr-primary').trim();

        if (meta && primary) {
            meta.setAttribute('content', theme === 'dark' ? '#111827' : primary);
        }
    };

    apply(root.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light');

    document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', (event) => {
            event.preventDefault();
            const next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';

            try {
                window.localStorage.setItem(THEME_KEY, next);
            } catch (error) {
                return;
            }

            apply(next);
        });
    });
}

function initDensity() {
    const root = document.documentElement;

    const read = () => {
        try {
            return window.localStorage.getItem(DENSITY_KEY) === 'compact' ? 'compact' : 'comfortable';
        } catch (error) {
            return 'comfortable';
        }
    };

    const apply = (density) => {
        root.setAttribute('data-admin-density', density);
    };

    apply(read());

    document.querySelectorAll('[data-density-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', (event) => {
            event.preventDefault();
            const next = read() === 'compact' ? 'comfortable' : 'compact';

            try {
                window.localStorage.setItem(DENSITY_KEY, next);
            } catch (error) {
                return;
            }

            apply(next);
        });
    });
}

function rowCheckboxes() {
    return document.querySelectorAll('[data-bulk-checkbox], [data-bulk-master]');
}

function syncBulkState() {
    const masters = document.querySelectorAll('[data-bulk-master]');

    masters.forEach((master) => {
        const rows = master.form
            ? master.form.querySelectorAll('[data-bulk-checkbox]')
            : document.querySelectorAll('[data-bulk-checkbox]');

        const total = rows.length;
        const selected = Array.prototype.filter.call(rows, (row) => row.checked).length;

        master.checked = total > 0 && selected === total;
        master.indeterminate = selected > 0 && selected < total;
    });

    const checked = document.querySelectorAll('[data-bulk-checkbox]:checked').length;

    document.querySelectorAll('[data-bulk-count]').forEach((counter) => {
        counter.textContent = String(checked);
    });

    document.querySelectorAll('[data-bulk-submit]').forEach((submit) => {
        submit.disabled = checked === 0;
    });
}

function initBulkActions() {
    if (rowCheckboxes().length === 0) {
        return;
    }

    document.addEventListener('change', (event) => {
        const target = event.target;

        if (target.matches('[data-bulk-master]')) {
            const rows = target.form
                ? target.form.querySelectorAll('[data-bulk-checkbox]')
                : document.querySelectorAll('[data-bulk-checkbox]');

            Array.prototype.forEach.call(rows, (row) => {
                row.checked = target.checked;
            });
        }

        syncBulkState();
    });

    document.querySelectorAll('[data-bulk-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            form.querySelectorAll('input[name="page"]').forEach((input) => {
                input.value = '';
            });
        });
    });

    syncBulkState();
}

function initRowSelection() {
    if (rowCheckboxes().length === 0) {
        return;
    }

    document.addEventListener('keydown', (event) => {
        if (event.key !== ' ' || event.target.matches('input, textarea, select, button, a')) {
            return;
        }

        const row = event.target.closest('tr[data-row-id], tr[data-row-selectable]');
        const checkbox = row ? row.querySelector('[data-bulk-checkbox]') : null;

        if (checkbox) {
            event.preventDefault();
            checkbox.checked = !checkbox.checked;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });
}

function readCssVariable(name, fallback) {
    const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return value === '' ? fallback : value;
}

function chartOptions(canvas) {
    const type = canvas.getAttribute('data-chart-type') || 'line';
    const isRound = type === 'doughnut' || type === 'pie';
    const horizontal = canvas.getAttribute('data-chart-horizontal') === '1';
    const filled = canvas.getAttribute('data-chart-filled') === '1';
    const primary = readCssVariable('--brand-primary', '#206bc4');
    const palette = [primary, '#20c997', '#f76707', '#d63939', '#7048e8', '#12b886', '#1098ad', '#f59f00'];
    const suffix = canvas.getAttribute('data-chart-suffix') || '';

    const datasets = (Array.isArray(canvas.__adminSeries) ? canvas.__adminSeries : []).map((entry, index) => {
        if (isRound) {
            return {
                label: entry.label,
                data: entry.data,
                backgroundColor: entry.data.map((value, itemIndex) => palette[itemIndex % palette.length]),
                borderWidth: type === 'doughnut' ? 0 : 1,
            };
        }

        return {
            label: entry.label,
            data: entry.data,
            borderColor: primary,
            backgroundColor: filled ? primary : 'transparent',
            fill: filled,
            tension: 0.35,
            borderWidth: 2,
            pointRadius: 2,
            pointHoverRadius: 4,
            borderRadius: 6,
        };
    });

    const options = {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: horizontal ? 'y' : 'x',
        stacked: canvas.getAttribute('data-chart-stacked') === '1',
        plugins: {
            legend: {
                display: canvas.getAttribute('data-chart-legend') !== '0' || isRound,
                position: 'bottom',
                labels: { usePointStyle: true, boxWidth: 8 },
            },
            tooltip: {
                callbacks: {
                    label(context) {
                        const value = context.parsed && context.parsed.x !== undefined ? context.parsed.x : context.parsed;
                        return `${context.dataset.label}: ${value}${suffix}`;
                    },
                },
            },
        },
        ...parseJson(canvas.getAttribute('data-chart-options'), {}),
    };

    if (!isRound) {
        options.scales = {
            x: { grid: { display: false }, ticks: { precision: 0 } },
            y: { beginAtZero: true, grid: { color: readCssVariable('--tblr-border-color', '#e6e9ee') } },
        };
    }

    return { isRound, datasets, options };
}

function buildChart(canvas) {
    if (typeof window.Chart !== 'function') {
        return null;
    }

    const labels = decodePayload(canvas.getAttribute('data-chart-labels')) || [];
    const series = decodePayload(canvas.getAttribute('data-chart-series')) || [];
    const type = canvas.getAttribute('data-chart-type') || 'line';

    canvas.__adminSeries = (Array.isArray(series) ? series : []).map((entry, index) => {
        if (Array.isArray(entry)) {
            return { label: `Seri ${index + 1}`, data: entry };
        }

        return {
            label: entry && entry.label ? String(entry.label) : `Seri ${index + 1}`,
            data: entry && entry.data ? entry.data : [],
        };
    });

    const hasValues = canvas.__adminSeries.some((entry) => entry.data.length > 0);

    if (!hasValues || labels.length === 0) {
        return null;
    }

    const { isRound, datasets, options } = chartOptions(canvas);
    const context = canvas.getContext('2d');

    if (!context) {
        return null;
    }

    if (isRound) {
        const config = new window.Chart(context, {
            type: type === 'pie' ? 'pie' : 'doughnut',
            data: { labels, datasets },
            options,
        });

        if (type === 'doughnut') {
            config.options.cutout = '62%';
            config.update();
        }

        return config;
    }

    return new window.Chart(context, {
        type: type === 'bar' ? 'bar' : 'line',
        data: { labels, datasets },
        options,
    });
}

function initCharts() {
    const canvases = document.querySelectorAll('canvas[data-chart]');

    if (canvases.length === 0 || typeof window.Chart === 'undefined') {
        return;
    }

    if (window.Chart.defaults) {
        window.Chart.defaults.font.family = readCssVariable('--tblr-body-font-family', 'Inter, sans-serif');
        window.Chart.defaults.color = readCssVariable('--tblr-secondary-color', '#667382');
    }

    canvases.forEach((canvas) => {
        if (canvas.dataset.adminChartReady === '1') {
            return;
        }

        canvas.dataset.adminChartReady = '1';

        try {
            buildChart(canvas);
        } catch (error) {
            canvas.dataset.adminChartReady = '0';
        }
    });
}

function initSelect2() {
    if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 !== 'function') {
        return;
    }

    const $ = window.jQuery;

    document.querySelectorAll('select[data-select2], select.select2, .select2-select').forEach((select) => {
        if (select.dataset.select2Booted === '1') {
            return;
        }
        const name = select.getAttribute('name');

        try {
            if (name) {
                $(`select[name="${CSS.escape(name)}"]`).select2({
                    width: '100%',
                    theme: 'bootstrap-5',
                    allowClear: true,
                });
                select.dataset.select2Booted = '1';
                return;
            }

            $(select).select2({ width: '100%', theme: 'bootstrap-5', allowClear: true });
            select.dataset.select2Booted = '1';
        } catch (error) {
            return;
        }
    });
}

function initTooltips() {
    if (typeof window.bootstrap === 'undefined' || !window.bootstrap.Tooltip) {
        return;
    }

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
        if (element.dataset.tooltipBooted === '1') {
            return;
        }
        try {
            new window.bootstrap.Tooltip(element);
            element.dataset.tooltipBooted = '1';
        } catch (error) {
            return;
        }
    });
}

function initConfirmDialog() {
    const dialog = document.querySelector('[data-confirm-dialog]');

    if (!dialog || dialog.__adminBooted === true || typeof dialog.showModal !== 'function') {
        return;
    }
    dialog.__adminBooted = true;

    const message = dialog.querySelector('[data-confirm-message]');
    const accept = dialog.querySelector('[data-confirm-accept]');
    const cancel = dialog.querySelector('[data-confirm-cancel]');
    let pending = null;

    const cleanup = () => {
        pending = null;
        if (dialog.open) {
            dialog.close();
        }
    };

    const ask = (text, onAccept) => {
        message.textContent = text;
        pending = onAccept;
        if (!dialog.open) {
            dialog.showModal();
        }
    };

    accept.addEventListener('click', () => {
        const callback = pending;
        cleanup();
        if (typeof callback === 'function') {
            callback();
        }
    });

    cancel.addEventListener('click', cleanup);

    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        cleanup();
    });

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            cleanup();
        }
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || form.getAttribute('data-confirm-inline') === '1') {
            return;
        }

        const text = form.getAttribute('data-confirm');

        if (!text) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        ask(text, () => {
            form.removeAttribute('data-confirm');
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });
    });

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-confirm]');

        if (!trigger || trigger.tagName === 'FORM') {
            return;
        }

        const href = trigger.tagName === 'A' ? trigger.getAttribute('href') : null;

        if (href) {
            event.preventDefault();
            ask(trigger.getAttribute('data-confirm'), () => {
                window.location.href = href;
            });
        }
    });

    window.__adminConfirmDialog = { ask };
}

function initToasts() {
    const container = document.querySelector('[data-toast-container]');
    const template = container ? container.querySelector('[data-toast-template]') : null;

    const show = (options) => {
        const config = options || {};

        if (!container || !template || !template.content) {
            return;
        }

        const node = template.content.cloneNode(true).querySelector('.toast');

        if (!node) {
            return;
        }

        const allowed = ['success', 'danger', 'warning', 'info'];
        const type = allowed.indexOf(config.type) === -1 ? 'info' : config.type;
        const fallback = parseInt(container.getAttribute('data-autohide'), 10) || 5000;
        const autohide = typeof config.autohide === 'number' ? config.autohide : fallback;

        node.setAttribute('data-toast-type', type);
        node.className = `toast show admin-toast admin-toast--${type}`;
        const titleNode = node.querySelector('.toast-title');
        const bodyNode = node.querySelector('.toast-body');
        if (titleNode) titleNode.textContent = config.title || 'Notifikasi';
        if (bodyNode) bodyNode.textContent = config.message || '';

        container.appendChild(node);

        const dismiss = () => {
            node.classList.remove('show');
            window.setTimeout(() => {
                if (node.parentNode) {
                    node.parentNode.removeChild(node);
                }
            }, 250);
        };

        const closer = node.querySelector('[data-bs-dismiss="toast"]');

        if (closer) {
            closer.addEventListener('click', dismiss);
        }

        if (autohide > 0) {
            window.setTimeout(dismiss, autohide);
        }
    };

    window.__adminToast = show;

    if (typeof window.bootstrap === 'undefined' || !window.bootstrap.Toast) {
        return;
    }

    document.querySelectorAll('[data-toast-message]').forEach((node) => {
        show({
            type: node.getAttribute('data-toast-message'),
            title: '',
            message: node.getAttribute('data-toast-text') || '',
        });
    });
}

onReady(() => {
    if (window.__adminBooted) {
        return;
    }
    window.__adminBooted = true;
    initSidebar();
    initTheme();
    initDensity();
    initBulkActions();
    initRowSelection();
    initCharts();
    initSelect2();
    initTooltips();
    initConfirmDialog();
    initToasts();
});
