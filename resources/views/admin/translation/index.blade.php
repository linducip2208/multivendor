@extends('layouts.admin')
@section('title','Translation DB')
@section('content')
<h4 class="fw-bold mb-3"><i class="fas fa-language me-2 text-primary"></i> Translation Database</h4>
<div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4"><form action="{{ route('admin.translation.update') }}" method="POST">@csrf @method('PUT')
<div class="row g-2 mb-3"><div class="col-md-3"><input type="text" name="key" class="form-control form-control-sm" placeholder="Key (contoh: welcome_message)"></div><div class="col-md-3"><input type="text" name="group" class="form-control form-control-sm" placeholder="Group (contoh: frontend)" value="frontend"></div><div class="col-md-3"><input type="text" name="value_id" class="form-control form-control-sm" placeholder="Bahasa Indonesia"></div><div class="col-md-3"><input type="text" name="value_en" class="form-control form-control-sm" placeholder="English"></div></div>
<button class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Tambah</button>
</form></div></div>
<div class="card border-0 rounded-4 shadow-sm mt-3"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Key</th><th>Group</th><th>ID</th><th>EN</th></tr></thead><tbody>
@foreach(\App\Models\Translation::orderBy('group')->orderBy('key')->get() as $t)
<tr><td><code>{{ $t->key }}</code></td><td>{{ $t->group }}</td><td>{{ \App\Models\Translation::get('id',$t->group,$t->key) }}</td><td>{{ \App\Models\Translation::get('en',$t->group,$t->key) }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
