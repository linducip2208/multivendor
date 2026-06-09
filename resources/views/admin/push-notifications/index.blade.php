@extends('layouts.admin')
@section('title', 'Push Notification')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4"><h4 class="fw-bold mb-0"><i class="fas fa-bell me-2 text-danger"></i> Push Notification</h4></div>
<div class="row g-4"><div class="col-lg-4"><div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4"><form action="{{ route('admin.push-notifications.store') }}" method="POST">@csrf
<div class="mb-2"><label class="fw-medium">Judul <span class="text-danger">*</span></label><input type="text" name="title" class="form-control" required></div>
<div class="mb-2"><label class="fw-medium">Deskripsi <span class="text-danger">*</span></label><textarea name="description" class="form-control" rows="3" required></textarea></div>
<div class="mb-2"><label class="fw-medium">URL Gambar</label><input type="text" name="image" class="form-control"></div>
<div class="mb-2"><label class="fw-medium">Target URL</label><input type="text" name="target_url" class="form-control"></div>
<div class="mb-2"><label class="fw-medium">Target</label><select name="target_type" class="form-select"><option value="all">Semua</option><option value="customer">Customer</option><option value="vendor">Vendor</option></select></div>
<button class="btn btn-danger w-100"><i class="fas fa-paper-plane me-1"></i>Kirim Notifikasi</button>
</form></div></div></div>
<div class="col-lg-8"><div class="card border-0 rounded-4 shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Judul</th><th>Target</th><th>Status</th><th>Tgl</th><th></th></tr></thead><tbody>@forelse($notifications as $n)<tr><td class="fw-medium">{{ $n->title }}</td><td><span class="badge bg-info-subtle text-info">{{ $n->target_type }}</span></td><td><span class="badge bg-{{ $n->sent?'success' : 'warning' }}-subtle">{{ $n->sent?'Terkirim' : 'Draft' }}</span></td><td class="small">{{ $n->created_at->format('d/m/Y') }}</td><td>@if(!$n->sent)<form action="{{ route('admin.push-notifications.send', $n) }}" method="POST">@csrf<button class="btn btn-sm btn-success">Kirim</button></form>@endif</td></tr>@empty<tr><td colspan="5" class="text-center py-4 text-muted">Belum ada notifikasi</td></tr>@endforelse</tbody></table></div>@if($notifications->hasPages())<div class="p-3">{{ $notifications->links() }}</div>@endif</div></div></div>
@endsection
