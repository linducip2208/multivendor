@extends('layouts.storefront')
@section('title', 'Leaderboard — Top Pembeli')
@section('content')
<div class="container"><h4 class="fw-bold mb-4"><i class="fas fa-trophy me-2 text-warning"></i> Leaderboard — Top Pembeli</h4>
@if($top->count()>0)
<div class="table-responsive"><table class="table table-hover"><thead><tr><th>#</th><th>Nama</th><th>Total Belanja</th><th>Badge</th></tr></thead><tbody>@foreach($top as $i=>$u)<tr><td class="fw-bold">{{ $i+1 }}</td><td>{{ $u->name }}</td><td class="fw-bold text-success">Rp {{ number_format($u->total_spent??0,0,',','.') }}</td><td>@if($i<3)<span class="badge bg-warning">⭐ Top {{ $i+1 }}</span>@else<span class="badge bg-secondary">Pembeli</span>@endif</td></tr>@endforeach</tbody></table></div>
@else<div class="empty-state"><i class="fas fa-trophy"></i><h5>Belum ada data</h5><a href="{{ route('products.index') }}" class="btn btn-primary">Mulai Belanja</a></div>@endif
</div>
@endsection
