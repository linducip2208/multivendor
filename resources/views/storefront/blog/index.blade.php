@extends('layouts.storefront')
@section('title', 'Blog')
@section('content')
<div class="container">
    <h3 class="fw-bold mb-4"><i class="fas fa-blog me-2 text-primary"></i> Blog <a href="/blog/feed.xml" class="btn btn-sm btn-outline-warning ms-2"><i class="fas fa-rss me-1"></i>RSS</a></h3>
    <div class="row g-4">
        @forelse($posts as $post)
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold"><a href="{{ route('blog.show', $post->slug) }}" class="text-decoration-none text-dark">{{ $post->title }}</a></h5>
                    <p class="text-muted small">{{ Str::limit($post->excerpt ?? strip_tags($post->content), 150) }}</p>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <small class="text-muted">{{ $post->author->name ?? 'Admin' }} · {{ $post->published_at?->format('d M Y') }}</small>
                        <a href="{{ route('blog.show', $post->slug) }}" class="btn btn-sm btn-outline-primary">Baca</a>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12 text-center py-5"><i class="fas fa-newspaper fa-3x text-muted mb-3 opacity-25"></i><h5>Belum ada artikel</h5></div>
        @endforelse
    </div>
    {{ $posts->links() }}
</div>
@endsection
