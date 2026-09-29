@extends('layouts.storefront')

@section('content')
    <div class="sf-container">
        <x-storefront.breadcrumb :items="$breadcrumbItems" />
    </div>

    <section class="sf-section" aria-labelledby="sf-blog-title">
        <div class="sf-container">
            <div class="sf-section-head">
                <div>
                    <h1 class="sf-section-head__title" id="sf-blog-title">Blog &amp; Ulasan</h1>
                    <p class="sf-muted sf-small sf-mt-0" style="max-width:60ch">
                        Artikel, panduan belanja, dan ulasan produk dari tim kami.
                    </p>
                </div>
                <a href="{{ route('blog.feed') }}" class="sf-section-head__link">
                    <x-storefront.icon name="external" :size="16" /> RSS
                </a>
            </div>

            <form method="GET" action="{{ route('blog.index') }}" class="sf-panel sf-row sf-row--wrap" style="gap:10px;margin-bottom:22px">
                <div class="sf-field" style="flex:1 1 240px;min-width:0">
                    <label class="sf-sr-only" for="sf-blog-search">Cari artikel</label>
                    <input class="sf-input" id="sf-blog-search" type="search" name="q" value="{{ request('q') }}"
                           placeholder="Cari judul artikel…" autocomplete="off">
                </div>
                <button type="submit" class="sf-btn sf-btn--primary">
                    <x-storefront.icon name="search" :size="16" /> Cari
                </button>
            </form>

            @if ($posts->total() > 0)
                <div class="sf-grid" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr))">
                    @foreach ($posts as $post)
                        <article class="sf-card">
                            <a href="{{ route('blog.show', $post->slug) }}" class="sf-banner" style="border-radius:0" tabindex="-1" aria-hidden="true">
                                @if ($post->featured_image)
                                    <img src="{{ url('img/'.ltrim((string) $post->featured_image, '/')) }}" alt=""
                                         loading="lazy" width="520" height="293" decoding="async">
                                @else
                                    <span class="sf-row" style="justify-content:center;height:100%;color:var(--sf-text-subtle)">
                                        <x-storefront.icon name="book" :size="30" />
                                    </span>
                                @endif
                            </a>
                            <div class="sf-card__body">
                                <p class="sf-tiny sf-muted sf-mb-0">
                                    <time datetime="{{ $post->published_at?->toAtomString() }}">{{ $post->published_at?->translatedFormat('d M Y') }}</time>
                                    @if ($post->author)
                                        &middot; {{ $post->author->name }}
                                    @endif
                                </p>
                                <h2 class="sf-clamp-2" style="font-size:1.05rem;margin:6px 0">
                                    <a href="{{ route('blog.show', $post->slug) }}" style="color:var(--sf-text)">{{ $post->title }}</a>
                                </h2>
                                @if ($post->excerpt)
                                    <p class="sf-small sf-muted sf-clamp-3 sf-mb-0">{{ $post->excerpt }}</p>
                                @endif
                            </div>
                            <div class="sf-card__foot">
                                <a href="{{ route('blog.show', $post->slug) }}" class="sf-small sf-row" style="gap:6px">
                                    Baca selengkapnya <x-storefront.icon name="arrow-right" :size="14" />
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>

                <x-storefront.pagination :paginator="$posts" />
            @else
                <x-storefront.empty
                    title="Artikel tidak ditemukan"
                    text="Belum ada artikel yang cocok dengan pencarian Anda."
                    :href="route('blog.index')"
                    label="Tampilkan semua artikel"
                    icon="book"
                />
            @endif
        </div>
    </section>
@endsection
