@extends('layouts.vendor')
@section('title', 'Wallet & Payout')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-wallet me-2 text-success"></i> Wallet & Payout</h4>
</div>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 rounded-4 shadow-sm"><div class="card-body text-center p-4">
            <div class="rounded-circle bg-success bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width:72px;height:72px;"><i class="fas fa-wallet fa-2x text-success"></i></div>
            <h2 class="fw-bold">Rp {{ number_format($wallet->balance ?? 0, 0, ',', '.') }}</h2>
            <p class="text-muted small">Saldo tersedia</p>
            <hr>
            <small class="text-muted">Pending: Rp {{ number_format($wallet->pending_balance ?? 0, 0, ',', '.') }}</small>
        </div></div>

        <div class="card border-0 rounded-4 shadow-sm mt-3"><div class="card-header bg-transparent border-0 pt-3"><h6 class="fw-bold mb-0"><i class="fas fa-hand-holding-usd me-2"></i> Pencairan Dana</h6></div><div class="card-body">
            <form action="{{ route('vendor.wallet.withdraw') }}" method="POST">@csrf
                <div class="mb-2"><label class="small fw-medium">Jumlah (Rp)</label><input type="number" name="amount" class="form-control" min="10000" max="{{ $wallet->balance ?? 0 }}" required></div>
                <div class="mb-2"><label class="small fw-medium">Bank</label><input type="text" name="bank_name" class="form-control" value="{{ $savedBank['bank_name'] }}" placeholder="BCA / BRI / Mandiri" required></div>
                <div class="mb-2"><label class="small fw-medium">No. Rekening</label><input type="text" name="bank_account_number" class="form-control" value="{{ $savedBank['bank_account_number'] }}" required></div>
                <div class="mb-2"><label class="small fw-medium">Atas Nama</label><input type="text" name="bank_account_name" class="form-control" value="{{ $savedBank['bank_account_name'] }}" required></div>
                @if(!$savedBank['bank_name'])<small class="text-muted">Simpan info bank dulu di <a href="{{ route('vendor.shop.settings') }}">Pengaturan Toko</a>.</small>@endif
                <button class="btn btn-success w-100"><i class="fas fa-paper-plane me-1"></i> Ajukan Pencairan</button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        <ul class="nav nav-tabs border-0 mb-3" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#history">Riwayat Transaksi</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#withdrawals">Riwayat Pencairan</button></li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="history"><div class="card border-0 rounded-4 shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>Tgl</th><th>Deskripsi</th><th>Tipe</th><th>Jumlah</th><th>Saldo</th></tr></thead><tbody>
                @forelse($transactions as $t)
                <tr><td class="small">{{ $t->created_at->format('d/m/Y H:i') }}</td><td>{{ $t->description ?? '-' }}</td><td><span class="badge bg-{{ $t->type==='credit' ? 'success' : 'danger' }}-subtle">{{ $t->type }}</span></td><td class="fw-medium">Rp {{ number_format($t->amount,0,',','.') }}</td><td>Rp {{ number_format($t->balance_after,0,',','.') }}</td></tr>
                @empty
                <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada transaksi</td></tr>
                @endforelse
            </tbody></table></div>@if($transactions->hasPages())<div class="p-3">{{ $transactions->links() }}</div>@endif</div></div>
            <div class="tab-pane fade" id="withdrawals"><div class="card border-0 rounded-4 shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>Tgl</th><th>Jumlah</th><th>Bank</th><th>Status</th></tr></thead><tbody>
                @forelse($withdrawRequests as $w)
                <tr><td class="small">{{ $w->created_at->format('d/m/Y') }}</td><td class="fw-medium">Rp {{ number_format($w->amount,0,',','.') }}</td><td>{{ $w->bank_name }} ({{ $w->bank_account_number }})</td><td><span class="badge bg-{{ ['pending'=>'warning','approved'=>'success','rejected'=>'danger','completed'=>'info'][$w->status] }}-subtle">{{ $w->status }}</span></td></tr>
                @empty
                <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada pencairan</td></tr>
                @endforelse
            </tbody></table></div>@if($withdrawRequests->hasPages())<div class="p-3">{{ $withdrawRequests->links() }}</div>@endif</div></div>
        </div>
    </div>
</div>
@endsection
