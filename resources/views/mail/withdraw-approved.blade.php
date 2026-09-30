@php($mailLocale = app()->getLocale() === 'en' ? 'en' : 'id')
@if ($mailLocale === 'en')
    <h1>Withdrawal approved</h1>
    <p>Withdrawal request #{{ $withdraw->id }} amounting to Rp {{ number_format($withdraw->amount, 0, ',', '.') }} has been approved.</p>
@else
    <h1>Penarikan dana disetujui</h1>
    <p>Permintaan penarikan #{{ $withdraw->id }} sebesar Rp {{ number_format($withdraw->amount, 0, ',', '.') }} telah disetujui.</p>
@endif
