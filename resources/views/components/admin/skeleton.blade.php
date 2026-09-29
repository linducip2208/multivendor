@props([
    'type' => 'text',
    'rows' => 3,
    'width' => null,
    'avatar' => false,
])

@php
    $type = in_array($type, ['text', 'title', 'table', 'avatar', 'card'], true) ? $type : 'text';
    $rows = max(1, min(50, (int) $rows));
    $style = $width ? ' style="width: '.$width.'"' : '';
@endphp

@if ($type === 'title')
    <div {{ $attributes->merge(['class' => 'placeholder-glow']) }}>
        <span class="placeholder col-6 admin-skeleton-title{{ $style ? '' : '' }}" {!! $style !!}></span>
    </div>
@elseif ($type === 'avatar')
    <div {{ $attributes->merge(['class' => 'd-flex align-items-center gap-3']) }}>
        <span class="placeholder-glow">
            <span class="placeholder avatar rounded-circle" style="width: 2.5rem; height: 2.5rem;"></span>
        </span>
        <span class="flex-fill placeholder-glow">
            <span class="placeholder col-7 mb-1"></span>
            <span class="placeholder col-4"></span>
        </span>
    </div>
@elseif ($type === 'card')
    <div {{ $attributes->merge(['class' => 'card admin-card']) }}>
        <div class="card-body">
            <div class="placeholder-glow">
                <span class="placeholder col-5 mb-3" style="height: 1.25rem;"></span>
                @for ($i = 0; $i < $rows; $i++)
                    <span class="placeholder {{ $i % 3 === 2 ? 'col-9' : 'col-12' }} mb-2"></span>
                @endfor
            </div>
        </div>
    </div>
@elseif ($type === 'table')
    <div {{ $attributes->merge(['class' => 'table-responsive']) }}>
        <table class="table admin-table mb-0">
            <thead>
                <tr>
                    @for ($i = 0; $i < 5; $i++)
                        <th><span class="placeholder col-8"></span></th>
                    @endfor
                </tr>
            </thead>
            <tbody>
                @for ($r = 0; $r < $rows; $r++)
                    <tr>
                        @for ($i = 0; $i < 5; $i++)
                            <td><span class="placeholder {{ $i % 2 === 0 ? 'col-9' : 'col-6' }}"></span></td>
                        @endfor
                    </tr>
                @endfor
            </tbody>
        </table>
    </div>
@else
    <div {{ $attributes->merge(['class' => 'placeholder-glow']) }}>
        @for ($i = 0; $i < $rows; $i++)
            <span class="placeholder {{ $i % 3 === 2 ? 'col-9' : 'col-12' }} mb-2" {!! $style !!}></span>
        @endfor
    </div>
@endif
