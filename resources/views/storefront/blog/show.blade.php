@extends('layouts.storefront')
@section('title', $post->title)
@section('content')
<div class="container">
    <article style="max-width:760px;margin:0 auto;">
        <nav class="small mb-3"><a href="{{ route('blog.index') }}">Blog</a> &raquo; {{ $post->title }}</nav>
        <h2 class="fw-bold mb-2">{{ $post->title }}</h2>
        <p class="text-muted small mb-4">{{ $post->author->name ?? 'Admin' }} · {{ $post->published_at?->translatedFormat('d F Y') }}</p>
        @if($post->excerpt)<div class="lead mb-4">{{ $post->excerpt }}</div>@endif
        <div class="lh-lg">{!! $post->content !!}</div>
    </article>
</div>
@endsection
