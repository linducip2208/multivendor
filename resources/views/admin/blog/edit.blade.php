@extends('layouts.admin')
@section('title', 'Ubah Artikel')
@section('content')
<div class="mb-4"><a href="{{ route('admin.blog.index') }}" class="small"><x-admin.icon name="arrow-left" :size="16" class="me-1" />Kembali</a><h4 class="fw-bold mt-2">Ubah: {{ $blog->title }}</h4></div>
{{-- Tab locale ID/EN (tampilan saja; status per bahasa draft/published tersimpan di blog_post_translations) --}}
<ul class="nav nav-tabs mb-3" data-locale-tabs role="tablist">
    <li class="nav-item" role="presentation"><button type="button" class="nav-link active" data-locale-tab="id" role="tab">ID</button></li>
    <li class="nav-item" role="presentation"><button type="button" class="nav-link" data-locale-tab="en" role="tab">EN</button></li>
    <li class="nav-item ms-auto d-flex align-items-center"><span class="text-muted small">Fallback: ID · Status per bahasa: draft/published</span></li>
</ul>
<div data-locale-panel="en" class="alert alert-info d-none">Isi EN opsional — kosong berarti fallback ke ID. Status EN (draft/published) mengikuti kolom status terjemahan.</div>
<x-admin.card :padding="false"><div class="card-body p-4">
<form method="POST" action="{{ route('admin.blog.update', $blog) }}">@csrf @method('PUT')
<div class="row g-3">
    <div class="col-md-8"><label class="fw-medium">Judul</label><input type="text" name="title" class="form-control" value="{{ old('title', $blog->title) }}" required></div>
    <div class="col-md-4"><label>Kategori</label><select name="categories[]" class="form-select" multiple>@foreach($categories as $c)<option value="{{ $c->id }}" {{ $blog->categories->contains($c->id)?'selected' : '' }}>{{ $c->name }}</option>@endforeach</select></div>
    <div class="col-12"><label>Excerpt</label><textarea name="excerpt" class="form-control" rows="2">{{ old('excerpt', $blog->excerpt) }}</textarea></div>
    <div class="col-md-8"><label for="blog-image">Gambar Sampul (URL)</label><div class="input-group"><input type="text" name="featured_image" id="blog-image" class="form-control" value="{{ old('featured_image', $blog->featured_image) }}" placeholder="/img/uploads/…"><x-admin.media-picker target="blog-image" preview="blog-image-preview" /></div><img id="blog-image-preview" src="{{ old('featured_image', $blog->featured_image) }}" alt="" class="rounded border mt-2 {{ old('featured_image', $blog->featured_image) ? '' : 'd-none' }}" style="max-height:120px;"></div>
    <div class="col-12"><label>Konten</label><div id="quillEditor" style="height:300px;"></div><input type="hidden" name="content" id="contentInput" value="{{ old('content', $blog->content) }}"></div>
    <div class="col-md-6"><label>Judul Meta</label><input type="text" name="meta_title" class="form-control" value="{{ old('meta_title', $blog->meta_title) }}"></div>
    <div class="col-md-6"><label>Deskripsi Meta</label><input type="text" name="meta_description" class="form-control" value="{{ old('meta_description', $blog->meta_description) }}"></div>
    <div class="col-md-6"><input type="hidden" name="is_published" value="0"><div class="form-check mt-4"><input type="checkbox" name="is_published" class="form-check-input" id="pub" value="1" {{ old('is_published', $blog->is_published)?'checked' : '' }}><label for="pub" class="fw-medium">Terbit</label></div></div>
    <div class="col-md-6"><label for="published_at">Jadwal terbit <small class="text-secondary">(masa depan = Terjadwal, terbit otomatis)</small></label><input type="datetime-local" name="published_at" id="published_at" class="form-control" value="{{ old('published_at', $blog->published_at?->format('Y-m-d\TH:i')) }}"></div>
    <div class="col-12"><button class="btn btn-primary"><x-admin.icon name="check" :size="16" class="me-2" />Perbarui</button></div>
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
