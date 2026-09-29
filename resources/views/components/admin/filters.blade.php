@props([
    'action' => '',
    'method' => 'GET',
    'filters' => [],
    'submitLabel' => 'Terapkan',
    'resetLabel' => 'Reset',
    'autoSubmit' => true,
    'showReset' => true,
])

@php
    $formId = 'admin-filters-'.substr(md5($action.'|'.json_encode(array_column((array) $filters, 'name'))), 0, 10);
    $method = strtoupper((string) $method) === 'POST' ? 'POST' : 'GET';
    $hasFilters = (array) $filters !== [];

    $currentQuery = request()->query();
    unset($currentQuery['page']);

    $activeCount = 0;
    foreach ((array) $filters as $filter) {
        $value = $filter['value'] ?? (request()->query($filter['name'] ?? null));
        if ($value !== null && $value !== '' && $value !== []) {
            $activeCount++;
        }
    }
@endphp

@if ($hasFilters)
    <form
        id="{{ $formId }}"
        action="{{ $action ?: request()->url() }}"
        method="{{ $method }}"
        class="admin-filters card admin-card mb-3"
        data-auto-submit="{{ $autoSubmit ? '1' : '0' }}"
    >
        @if ($method === 'POST')
            @csrf
        @endif

        <div class="card-body p-3">
            <div class="row g-2 align-items-end">
                @foreach ((array) $filters as $filter)
                    @php
                        $name = (string) ($filter['name'] ?? 'filter');
                        $type = (string) ($filter['type'] ?? 'search');
                        $value = $filter['value'] ?? request()->query($name);
                        $label = (string) ($filter['label'] ?? \Illuminate\Support\Str::headline($name));
                        $col = (string) ($filter['col'] ?? 'col-12 col-md-3 col-xl-2');
                        $options = $filter['options'] ?? [];
                    @endphp
                    <div class="{{ $col }}">
                        <label class="form-label small mb-1" for="{{ $formId }}-{{ $name }}">{{ $label }}</label>
                        @if ($type === 'select')
                            <select
                                class="form-select"
                                id="{{ $formId }}-{{ $name }}"
                                name="{{ $name }}"
                                data-filter-select
                            >
                                @foreach ((array) $options as $optionValue => $optionLabel)
                                    @php
                                        $isAssoc = is_string($optionValue);
                                        $optionKey = $isAssoc ? $optionValue : ($optionLabel['value'] ?? $optionValue);
                                        $optionText = $isAssoc ? $optionLabel : ($optionLabel['label'] ?? $optionLabel);
                                    @endphp
                                    <option value="{{ $optionKey }}" {{ (string) $value === (string) $optionKey ? 'selected' : '' }}>{{ $optionText }}</option>
                                @endforeach
                            </select>
                        @elseif ($type === 'date')
                            <input
                                type="date"
                                class="form-control"
                                id="{{ $formId }}-{{ $name }}"
                                name="{{ $name }}"
                                value="{{ $value }}"
                                data-filter-control
                            >
                        @elseif ($type === 'number')
                            <input
                                type="number"
                                class="form-control"
                                id="{{ $formId }}-{{ $name }}"
                                name="{{ $name }}"
                                value="{{ $value }}"
                                placeholder="{{ $filter['placeholder'] ?? '' }}"
                                data-filter-control
                            >
                        @else
                            <input
                                type="search"
                                class="form-control"
                                id="{{ $formId }}-{{ $name }}"
                                name="{{ $name }}"
                                value="{{ $value }}"
                                placeholder="{{ $filter['placeholder'] ?? $label }}"
                                data-filter-control
                            >
                        @endif
                    </div>
                @endforeach

                <div class="col-12 col-md-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <x-admin.icon name="filter" :size="16" />
                        <span>{{ $submitLabel }}</span>
                    </button>
                    @if ($showReset)
                        <a href="{{ $action ?: request()->url() }}" class="btn btn-outline-secondary">
                            <x-admin.icon name="refresh" :size="16" />
                            <span>{{ $resetLabel }}</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>

        @if ($method === 'GET' && $currentQuery !== [])
            @foreach ($currentQuery as $key => $value)
                @if (! is_array($value) && ! collect($filters)->contains(fn ($filter) => ($filter['name'] ?? null) === $key))
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
        @endif
    </form>

    @once
        <script data-admin-filters>
            (function () {
                function submitOnce(form) {
                    syncFormState(form);
                    form.submit();
                }

                function hasMeaningfulValue(control) {
                    if (control.type === 'checkbox' || control.type === 'radio') {
                        return control.checked;
                    }
                    return String(control.value || '').trim() !== '';
                }

                function syncFormState(form) {
                    form.querySelectorAll('input[name="page"]').forEach(function (input) {
                        input.value = '';
                    });
                }

                document.addEventListener('change', function (event) {
                    var control = event.target.closest('[data-filter-control], [data-filter-select]');
                    if (!control) {
                        return;
                    }
                    var form = control.closest('form[data-auto-submit="1"]');
                    if (!form) {
                        return;
                    }
                    submitOnce(form);
                });

                document.addEventListener('input', function (event) {
                    var control = event.target.closest('[data-filter-control]');
                    if (!control || control.tagName === 'SELECT' || control.type === 'date') {
                        return;
                    }
                    var form = control.closest('form[data-auto-submit="1"]');
                    if (!form) {
                        return;
                    }
                    window.clearTimeout(control.__adminFilterTimer);
                    control.__adminFilterTimer = window.setTimeout(function () {
                        submitOnce(form);
                    }, 650);
                });

                document.addEventListener('submit', function (event) {
                    var form = event.target.closest('form[data-auto-submit="1"]');
                    if (form) {
                        syncFormState(form);
                    }
                });

                window.__adminFilters = { hasMeaningfulValue: hasMeaningfulValue, submitOnce: submitOnce };
            })();
        </script>
    @endonce
@endif
