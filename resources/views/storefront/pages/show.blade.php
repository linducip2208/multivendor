@extends('layouts.storefront')
@section('title', $title ?? 'Halaman')
@section('content')
<div class="container" style="max-width:800px;">
    <h3 class="fw-bold mb-4">{{ $title ?? '' }}</h3>
    <div class="lh-lg">{!! $content ?? '' !!}</div>
</div>
@endsection
