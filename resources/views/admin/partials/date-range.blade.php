@php
    $presets = \App\Services\Analytics\DateRange::PRESETS;
    $active = $range->preset ?? '';
@endphp
<form method="GET" action="{{ request()->url() }}" class="d-flex flex-wrap gap-2 align-items-end" aria-label="Pilih rentang tanggal">
    <div class="col-auto">
        <label class="form-label small mb-1" for="analytics-range">Periode</label>
        <select class="form-select form-select-sm" id="analytics-range" name="range" data-auto-submit>
            @foreach ($presets as $value => $label)
                <option value="{{ $value }}" {{ $active === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
            <option value="custom" {{ $active === '' || $active === 'custom' ? 'selected' : '' }}>Rentang khusus</option>
        </select>
    </div>
    <div class="col-auto">
        <label class="form-label small mb-1" for="analytics-from">Dari</label>
        <input type="date" class="form-control form-control-sm" id="analytics-from" name="from" value="{{ $range->from->toDateString() }}">
    </div>
    <div class="col-auto">
        <label class="form-label small mb-1" for="analytics-to">Sampai</label>
        <input type="date" class="form-control form-control-sm" id="analytics-to" name="to" value="{{ $range->to->toDateString() }}">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary btn-sm">
            <x-admin.icon name="filter" :size="14" /> Terapkan
        </button>
    </div>
    <div class="col-auto">
        <a href="{{ request()->url() }}" class="btn btn-outline-secondary btn-sm">Reset</a>
    </div>
    @foreach (request()->except(['range', 'from', 'to', 'page']) as $key => $value)
        @if (! is_array($value))
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach
    <p class="small text-secondary mb-0 ms-1">{{ $range->from->toDateString() }} &ndash; {{ $range->to->toDateString() }} ({{ $range->days() }} hari)</p>
</form>
