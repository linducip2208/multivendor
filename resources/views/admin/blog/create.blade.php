@extends('layouts.admin')
@section('title', 'Tulis Artikel')
@section('content')
<div class="mb-4"><a href="{{ route('admin.blog.index') }}" class="small"><i class="fas fa-arrow-left me-1"></i>Kembali</a><h4 class="fw-bold mt-2">Tulis Artikel</h4></div>
<div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
<form method="POST" action="{{ route('admin.blog.store') }}">@csrf
<div class="row g-3">
    <div class="col-md-8"><label class="fw-medium">Judul <span class="text-danger">*</span></label><input type="text" name="title" class="form-control" value="{{ old('title') }}" required></div>
    <div class="col-md-4"><label class="fw-medium">Kategori</label><select name="categories[]" class="form-select" multiple><option value="">--</option>@foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
    <div class="col-12"><label class="fw-medium">Excerpt</label><textarea name="excerpt" class="form-control" rows="2">{{ old('excerpt') }}</textarea></div>
    <div class="col-12"><label class="fw-medium">Konten <span class="text-danger">*</span></label><div id="quillEditor" style="height:300px;"></div><input type="hidden" name="content" id="contentInput" value="{{ old('content') }}"></div>
    <div class="col-md-6"><label class="fw-medium">Meta Title</label><input type="text" name="meta_title" class="form-control" value="{{ old('meta_title') }}"></div>
    <div class="col-md-6"><label class="fw-medium">Meta Description</label><input type="text" name="meta_description" class="form-control" value="{{ old('meta_description') }}"></div>
    <div class="col-12"><div class="form-check"><input type="checkbox" name="is_published" class="form-check-input" id="pub" value="1" checked><label for="pub" class="fw-medium">Publish</label></div></div>
    <div class="col-12"><button class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan</button></div>
</div>
</form></div></div>
@endsection

@push('scripts')
<script>
var quill = new Quill('#quillEditor', { theme: 'snow', modules: { toolbar: [['bold','italic','underline','strike'],['blockquote','code-block'],[{header:[1,2,3,false]}],[{list:'ordered'},{list:'bullet'}],['link','image'],['clean']] }, placeholder: 'Tulis konten artikel...' });
quill.root.innerHTML = document.getElementById('contentInput').value || '';
document.querySelector('form').addEventListener('submit', function(){ document.getElementById('contentInput').value = quill.root.innerHTML; });
</script>
@endpush
