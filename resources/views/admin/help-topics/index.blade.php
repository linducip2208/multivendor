@extends('layouts.admin')
@section('title','Help Topics')
@section('content')
<h4 class="fw-bold mb-3"><i class="fas fa-question-circle me-2 text-info"></i> Help Topics</h4>
<div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
<form action="{{ route('admin.help-topics.update') }}" method="POST">@csrf @method('PUT')
<div class="row g-3">
@for($i=1;$i<=10;$i++)
<div class="col-md-6"><div class="border rounded-3 p-3">
    <label class="fw-medium small">Topik #{{ $i }}</label>
    <input type="text" name="topic_title[{{ $i }}]" class="form-control form-control-sm mb-1" value="{{ \App\Models\SystemSetting::get('help_topic_'.$i.'_title') }}" placeholder="Judul topik...">
    <textarea name="topic_body[{{ $i }}]" class="form-control form-control-sm" rows="2" placeholder="Isi jawaban...">{{ \App\Models\SystemSetting::get('help_topic_'.$i.'_body') }}</textarea>
</div></div>
@endfor
<div class="col-12"><button class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan</button></div>
</div></form></div></div>
@endsection
