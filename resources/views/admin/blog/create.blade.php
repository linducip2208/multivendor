@extends('layouts.admin')
@section('title', 'Tulis Artikel')
@section('content')
<div class="mb-4"><a href="{{ route('admin.blog.index') }}" class="small"><x-admin.icon name="arrow-left" :size="16" class="me-1" />Kembali</a><h4 class="fw-bold mt-2">Tulis Artikel</h4></div>
<x-admin.card :padding="false"><div class="card-body p-4">
<form method="POST" action="{{ route('admin.blog.store') }}">@csrf
<div class="row g-3">
    <div class="col-md-8"><label class="fw-medium">Judul <span class="text-danger">*</span></label><input type="text" name="title" class="form-control" value="{{ old('title') }}" required></div>
    <div class="col-md-4"><label class="fw-medium">Kategori</label><select name="categories[]" class="form-select" multiple><option value="">--</option>@foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
    <div class="col-12"><label class="fw-medium">Excerpt</label><textarea name="excerpt" class="form-control" rows="2">{{ old('excerpt') }}</textarea></div>
    <div class="col-md-8"><label class="fw-medium" for="blog-image">Gambar Sampul (URL)</label><div class="input-group"><input type="text" name="featured_image" id="blog-image" class="form-control" value="{{ old('featured_image') }}" placeholder="/img/uploads/…"><x-admin.media-picker target="blog-image" preview="blog-image-preview" /></div><img id="blog-image-preview" src="{{ old('featured_image') }}" alt="" class="rounded border mt-2 {{ old('featured_image') ? '' : 'd-none' }}" style="max-height:120px;"></div>
    <div class="col-12"><label class="fw-medium">Konten <span class="text-danger">*</span></label><div id="quillEditor" style="height:300px;"></div><input type="hidden" name="content" id="contentInput" value="{{ old('content') }}"></div>
    <div class="col-md-6"><label class="fw-medium">Judul Meta</label><input type="text" name="meta_title" class="form-control" value="{{ old('meta_title') }}"></div>
    <div class="col-md-6"><label class="fw-medium">Deskripsi Meta</label><input type="text" name="meta_description" class="form-control" value="{{ old('meta_description') }}"></div>
    <div class="col-md-6"><div class="form-check mt-4"><input type="checkbox" name="is_published" class="form-check-input" id="pub" value="1" @checked(old('is_published', true))><label for="pub" class="fw-medium">Terbit</label></div></div>
    <div class="col-md-6"><label class="fw-medium" for="published_at">Jadwal terbit <small class="text-secondary">(kosongkan = sekarang; isi masa depan = Terjadwal)</small></label><input type="datetime-local" name="published_at" id="published_at" class="form-control" value="{{ old('published_at') }}"></div>
    <div class="col-12"><button class="btn btn-primary"><x-admin.icon name="check" :size="16" class="me-2" />Simpan</button></div>
</div>
</form></div></x-admin.card>
@endsection

@push('scripts')
<script>
var quill = new Quill('#quillEditor', { theme: 'snow', modules: { toolbar: [['bold','italic','underline','strike'],['blockquote','code-block'],[{header:[1,2,3,false]}],[{list:'ordered'},{list:'bullet'}],['link','image'],['clean']] }, placeholder: 'Tulis konten artikel...' });
quill.root.innerHTML = document.getElementById('contentInput').value || '';
document.querySelector('form').addEventListener('submit', function(){ document.getElementById('contentInput').value = quill.root.innerHTML; });
</script>
@endpush
