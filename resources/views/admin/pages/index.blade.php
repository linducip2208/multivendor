@extends('layouts.admin')
@section('title','Pages')
@section('content')
<h4 class="fw-bold mb-3"><i class="fas fa-file-alt me-2 text-info"></i> Halaman</h4>
<div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
<form action="{{ route('admin.pages.update') }}" method="POST">@csrf @method('PUT')
@php $pages=['about'=>'Tentang Kami','terms'=>'Syarat & Ketentuan','privacy'=>'Kebijakan Privasi','return'=>'Kebijakan Pengembalian','faq'=>'FAQ']; @endphp
@foreach($pages as $key=>$label)
<div class="mb-4"><h6 class="fw-bold">{{ $label }}</h6>
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
<div id="editor-{{ $key }}" style="height:200px;"></div>
<input type="hidden" name="pages[{{ $key }}]" id="input-{{ $key }}" value="{{ \App\Models\SystemSetting::get('page_'.$key) }}">
<script>new Quill('#editor-{{ $key }}',{theme:'snow',modules:{toolbar:[['bold','italic','underline'],['link'],[{list:'ordered'},{list:'bullet'}],['clean']]}}).root.innerHTML=document.getElementById('input-{{ $key }}').value||'';document.querySelector('form').addEventListener('submit',function(){document.getElementById('input-{{ $key }}').value=Quill.find(document.getElementById('editor-{{ $key }}')).root.innerHTML})</script>
</div>
@endforeach
<button class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan Semua</button>
</form></div></div>
@endsection
