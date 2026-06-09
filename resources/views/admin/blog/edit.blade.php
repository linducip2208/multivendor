@extends('layouts.admin')
@section('title', 'Edit Artikel')
@section('content')
<div class="mb-4"><a href="{{ route('admin.blog.index') }}" class="small"><i class="fas fa-arrow-left me-1"></i>Kembali</a><h4 class="fw-bold mt-2">Edit: {{ $blog->title }}</h4></div>
<div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
<form method="POST" action="{{ route('admin.blog.update', $blog) }}">@csrf @method('PUT')
<div class="row g-3">
    <div class="col-md-8"><label class="fw-medium">Judul</label><input type="text" name="title" class="form-control" value="{{ old('title', $blog->title) }}" required></div>
    <div class="col-md-4"><label>Kategori</label><select name="categories[]" class="form-select" multiple>@foreach($categories as $c)<option value="{{ $c->id }}" {{ $blog->categories->contains($c->id)?'selected'=>'' }}>{{ $c->name }}</option>@endforeach</select></div>
    <div class="col-12"><label>Excerpt</label><textarea name="excerpt" class="form-control" rows="2">{{ old('excerpt', $blog->excerpt) }}</textarea></div>
    <div class="col-12"><label>Konten</label><div id="quillEditor" style="height:300px;"></div><input type="hidden" name="content" id="contentInput" value="{{ old('content', $blog->content) }}"></div>
    <div class="col-md-6"><label>Meta Title</label><input type="text" name="meta_title" class="form-control" value="{{ old('meta_title', $blog->meta_title) }}"></div>
    <div class="col-md-6"><label>Meta Description</label><input type="text" name="meta_description" class="form-control" value="{{ old('meta_description', $blog->meta_description) }}"></div>
    <div class="col-12"><div class="form-check"><input type="checkbox" name="is_published" class="form-check-input" id="pub" value="1" {{ $blog->is_published?'checked'=>'' }}><label for="pub" class="fw-medium">Publish</label></div></div>
    <div class="col-12"><button class="btn btn-primary"><i class="fas fa-save me-2"></i>Perbarui</button></div>
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
