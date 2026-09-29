@props([
    'name' => null,
    'label' => null,
    'type' => 'text',
    'value' => null,
    'options' => [],
    'required' => false,
    'help' => null,
    'placeholder' => null,
    'rows' => 4,
    'col' => null,
    'autofocus' => false,
    'readonly' => false,
    'disabled' => false,
    'min' => null,
    'max' => null,
    'step' => null,
    'accept' => null,
    'multiple' => false,
    'hint' => null,
    'prefix' => null,
    'suffix' => null,
])

@php
    $type = in_array($type, ['text', 'email', 'password', 'number', 'tel', 'url', 'date', 'time', 'select', 'textarea', 'checkbox', 'radio', 'file'], true)
        ? $type
        : 'text';

    $fieldName = (string) ($name ?? 'field');
    $id = 'field-'.preg_replace('/[^a-z0-9]+/i', '-', strtolower($fieldName));
    $errorBag = $errors ?? null;
    $error = $errorBag !== null ? ($errorBag->first($fieldName) ?: $errorBag->first($id)) : null;
    $hasError = $error !== null && $error !== '';

    $describedBy = [];
    if ($help) {
        $describedBy[] = $id.'-help';
    }
    if ($hasError) {
        $describedBy[] = $id.'-invalid';
    }

    $wrapAttributes = $attributes->only('class', 'style');
    $common = $attributes->except('class', 'style')->merge([
        'id' => $id,
        'name' => $fieldName,
        'required' => $required,
        'placeholder' => $placeholder,
        'autofocus' => $autofocus,
        'readonly' => $readonly,
        'disabled' => $disabled,
        'aria-describedby' => $describedBy === [] ? null : implode(' ', $describedBy),
    ]);
    $invalid = $hasError ? ' is-invalid' : '';
    $old = old($fieldName, $value);
@endphp

@if ($col)
    <div class="col {{ $col }}">
@endif

<div {{ $wrapAttributes->merge(['class' => 'mb-3']) }}>
    @if ($type === 'checkbox')
        <div class="form-check">
            <input
                {{ $common->merge(['class' => 'form-check-input'.$invalid, 'type' => 'checkbox', 'value' => '1']) }}
                @checked((bool) $old)
            >
            @if ($label)
                <label class="form-check-label" for="{{ $id }}">{{ $label }}</label>
            @endif
        </div>
    @elseif ($type === 'radio')
        <fieldset>
            @if ($label)
                <legend class="form-label">{{ $label }}</legend>
            @endif
            @foreach ((array) $options as $optionValue => $optionLabel)
                @php
                    $isAssoc = is_string($optionValue);
                    $optionKey = $isAssoc ? $optionValue : ($optionLabel['value'] ?? $optionValue);
                    $optionText = $isAssoc ? $optionLabel : ($optionLabel['label'] ?? $optionLabel);
                    $optionId = $id.'-'.preg_replace('/[^a-z0-9]+/i', '-', strtolower((string) $optionKey));
                @endphp
                <div class="form-check">
                    <input
                        {{
                            $common->merge([
                                'class' => 'form-check-input'.$invalid,
                                'type' => 'radio',
                                'id' => $optionId,
                                'name' => $fieldName,
                                'value' => $optionKey,
                            ])
                        }}
                        @checked((string) $old === (string) $optionKey)
                    >
                    <label class="form-check-label" for="{{ $optionId }}">{{ $optionText }}</label>
                </div>
            @endforeach
        </fieldset>
    @else
        @if ($label)
            <label class="form-label" for="{{ $id }}">
                {{ $label }}
                @if ($required)<span class="text-danger" aria-hidden="true">*</span>@endif
            </label>
        @endif

        @if ($prefix || $suffix)
            <div class="input-group mb-2">
        @endif

        @if ($prefix)
            <span class="input-group-text">{!! $prefix !!}</span>
        @endif

        @if ($type === 'select')
            <select {{ $common->merge(['class' => 'form-select'.$invalid]) }}>
                @if ($placeholder !== null)
                    <option value="">{{ $placeholder }}</option>
                @endif
                @foreach ((array) $options as $optionValue => $optionLabel)
                    @php
                        $isAssoc = is_string($optionValue);
                        $optionKey = $isAssoc ? $optionValue : ($optionLabel['value'] ?? $optionValue);
                        $optionText = $isAssoc ? $optionLabel : ($optionLabel['label'] ?? $optionLabel);
                    @endphp
                    <option value="{{ $optionKey }}" @selected((string) $old === (string) $optionKey)>{{ $optionText }}</option>
                @endforeach
            </select>
        @elseif ($type === 'textarea')
            <textarea {{ $common->merge(['class' => 'form-control'.$invalid, 'rows' => $rows]) }}>{{ $old }}</textarea>
        @elseif ($type === 'file')
            <input {{ $common->merge(['class' => 'form-control'.$invalid, 'accept' => $accept, 'multiple' => $multiple]) }}>
        @else
            <input
                {{
                    $common->merge([
                        'class' => 'form-control'.$invalid,
                        'type' => $type,
                        'value' => $old,
                        'min' => $min,
                        'max' => $max,
                        'step' => $step,
                    ])
                }}
            >
        @endif

        @if ($prefix || $suffix)
            @if ($suffix)
                <span class="input-group-text">{!! $suffix !!}</span>
            @endif
            </div>
        @endif

        @if ($help || $hint)
            <div class="form-text" id="{{ $id }}-help">{{ $help ?? $hint }}</div>
        @endif

        @if ($hasError)
            <div class="invalid-feedback{{ in_array($type, ['select', 'textarea', 'file'], true) ? ' d-block' : '' }}" id="{{ $id }}-invalid">{{ $error }}</div>
        @endif
    @endif

    {{ $slot }}
</div>

@if ($col)
    </div>
@endif
