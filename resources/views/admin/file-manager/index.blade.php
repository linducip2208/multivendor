@extends('layouts.admin')
@section('title', 'File Manager')
@section('content')
<h4 class="fw-bold mb-3"><i class="fas fa-folder me-2 text-warning"></i> File Manager</h4>
<div class="card border-0 rounded-4 shadow-sm mb-4"><div class="card-body p-3"><form action="{{ route('admin.file-manager.upload') }}" method="POST" enctype="multipart/form-data" class="d-flex gap-2">@csrf<input type="file" name="file" class="form-control" required><button class="btn btn-primary"><i class="fas fa-upload me-1"></i>Upload</button></form></div></div>
<div class="card border-0 rounded-4 shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>File</th><th>Path</th><th>Size</th><th>Modified</th><th>Actions</th></tr></thead><tbody>@forelse($files->take(50) as $f)
<tr><td><a href="{{ $f['url'] }}" target="_blank" class="fw-medium">{{ $f['name'] }}</a></td><td><small class="text-muted">{{ $f['path'] }}</small></td><td>{{ number_format($f['size']/1024,1) }} KB</td><td class="small">{{ date('d/m/Y H:i', $f['modified']) }}</td><td><form action="{{ route('admin.file-manager.destroy') }}" method="POST" onsubmit="return confirm('Hapus?')">@csrf @method('DELETE')<input type="hidden" name="path" value="{{ $f['path'] }}"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form></td></tr>
@empty<tr><td colspan="5" class="text-center py-5 text-muted">Tidak ada file</td></tr>@endforelse</tbody></table></div></div>
@endsection
