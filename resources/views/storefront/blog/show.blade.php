@extends('layouts.storefront')

@section('content')
    <div class="sf-container">
        <x-storefront.breadcrumb :items="$breadcrumbItems" />
    </div>

    <article class="sf-section sf-section--tight">
        <div class="sf-container" style="max-width:820px">
            <header>
                <h1 style="font-size:clamp(1.6rem,1.2rem+1.8vw,2.4rem)">{{ $post->title }}</h1>
                <div class="sf-row sf-row--wrap sf-small sf-muted" style="gap:14px;margin-top:10px">
                    @if ($post->author)
                        <span class="sf-row" style="gap:7px">
                            <span class="sf-avatar" style="width:28px;height:28px;font-size:.7rem" aria-hidden="true">
                                {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($post->author->name, 0, 1)) }}
                            </span>
                            {{ $post->author->name }}
                        </span>
                    @endif
                    @if ($post->published_at)
                        <span>
                            <time datetime="{{ $post->published_at->toAtomString() }}">{{ $post->published_at->translatedFormat('d F Y') }}</time>
                        </span>
                    @endif
                    @if ($post->updated_at && $post->published_at && $post->updated_at->gt($post->published_at))
                        <span>
                            Diperbarui
                            <time datetime="{{ $post->updated_at->toAtomString() }}">{{ $post->updated_at->translatedFormat('d F Y') }}</time>
                        </span>
                    @endif
                </div>
            </header>

            @if ($post->featured_image)
                <img src="{{ url('img/'.ltrim((string) $post->featured_image, '/')) }}" alt="{{ $post->title }}"
                     width="1040" height="585" loading="eager" fetchpriority="high" decoding="async"
                     style="width:100%;aspect-ratio:16/9;object-fit:cover;border-radius:var(--sf-radius);margin-top:22px">
            @endif

            @if ($post->excerpt)
                <p class="sf-prose" style="font-size:1.05rem;margin-top:22px">
                    {{ $post->excerpt }}
                </p>
            @endif

            @if (trim((string) $content) !== '')
                <div class="sf-prose" style="margin-top:18px">{!! $content !!}</div>
            @else
                <p class="sf-muted" style="margin-top:18px">Konten artikel ini belum tersedia.</p>
            @endif

            <footer style="margin-top:36px">
                <hr class="sf-divider">
                <div class="sf-row sf-row--wrap" style="gap:10px">
                    <a href="{{ route('blog.index') }}" class="sf-btn sf-btn--outline sf-btn--sm">
                        <x-storefront.icon name="chevron-left" :size="15" /> Semua artikel
                    </a>
                    <a href="{{ route('blog.feed') }}" class="sf-btn sf-btn--ghost sf-btn--sm">
                        <x-storefront.icon name="external" :size="15" /> RSS feed
                    </a>
                    <a href="{{ route('products.index') }}" class="sf-btn sf-btn--ghost sf-btn--sm">Belanja sekarang</a>
                </div>
            </footer>
        </div>
    </article>

    @if ($related->isNotEmpty())
        <section class="sf-section sf-section--subtle" aria-labelledby="sf-blog-related">
            <div class="sf-container">
                <h2 class="sf-section-head__title" id="sf-blog-related" style="font-size:1.3rem;margin-bottom:16px">
                    Artikel terkait
                </h2>
                <div class="sf-grid" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">
                    @foreach ($related as $item)
                        <article class="sf-card">
                            <div class="sf-card__body">
                                <h3 class="sf-clamp-2" style="font-size:1rem">
                                    <a href="{{ route('blog.show', $item->slug) }}" style="color:var(--sf-text)">{{ $item->title }}</a>
                                </h3>
                                <p class="sf-tiny sf-muted sf-mb-0">
                                    <time datetime="{{ $item->published_at?->toAtomString() }}">{{ $item->published_at?->translatedFormat('d M Y') }}</time>
                                </p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
