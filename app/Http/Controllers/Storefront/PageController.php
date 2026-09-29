<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\SystemSetting;
use App\Services\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Static content: legal pages, blog, documentation.
 *
 * Page bodies come from `SystemSetting` and are sanitised on read as well as
 * on write, so a value imported from a previous installation or written
 * straight to the database cannot inject script into the storefront.
 */
class PageController extends Controller
{
    private const PAGES = [
        'about' => ['title' => 'Tentang Kami', 'icon' => 'store'],
        'terms' => ['title' => 'Syarat & Ketentuan', 'icon' => 'file-text'],
        'privacy' => ['title' => 'Kebijakan Privasi', 'icon' => 'shield'],
        'return' => ['title' => 'Kebijakan Retur', 'icon' => 'refresh'],
        'faq' => ['title' => 'Pertanyaan Umum', 'icon' => 'help'],
        'seller' => ['title' => 'Cara Bergabung', 'icon' => 'users'],
    ];

    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    public function show(string $slug)
    {
        $page = self::PAGES[$slug] ?? abort(404);

        $content = (string) SystemSetting::get('page_'.$slug, '');
        $title = (string) (SystemSetting::get('page_'.$slug.'_title') ?: $page['title']);

        $breadcrumb = [['label' => $title, 'href' => null]];

        return view('storefront.pages.show', [
            'title' => $title,
            'icon' => $page['icon'],
            'content' => $this->sanitizer->clean($content),
            'breadcrumbItems' => $breadcrumb,
            'metaTitle' => $title,
            'metaDescription' => Str::limit(strip_tags($content), 155) ?: $title,
            'canonicalUrl' => route('page.show', $slug),
        ]);
    }

    public function blogIndex(Request $request)
    {
        $posts = BlogPost::query()
            ->where('is_published', true)
            ->where('published_at', '<=', now())
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->input('q').'%'))
            ->with(['author', 'category'])
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        $breadcrumb = [['label' => 'Blog', 'href' => null]];

        return view('storefront.blog.index', [
            'posts' => $posts,
            'breadcrumbItems' => $breadcrumb,
            'metaTitle' => 'Blog & Ulasan',
            'metaDescription' => 'Artikel, panduan belanja dan ulasan produk dari '.config('app.name').'.',
            'canonicalUrl' => route('blog.index'),
        ]);
    }

    public function blogShow(string $slug)
    {
        $post = BlogPost::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->where('published_at', '<=', now())
            ->with(['author', 'category'])
            ->firstOrFail();

        $related = BlogPost::query()
            ->where('is_published', true)
            ->where('published_at', '<=', now())
            ->where('id', '!=', $post->id)
            ->when($post->category_id, fn ($q) => $q->where('blog_category_id', $post->category_id))
            ->latest('published_at')
            ->limit(4)
            ->get();

        $breadcrumb = [
            ['label' => 'Beranda', 'href' => route('home')],
            ['label' => 'Blog', 'href' => route('blog.index')],
            ['label' => $post->title, 'href' => null],
        ];

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post->title,
            'description' => $post->excerpt ? Str::limit(strip_tags($post->excerpt), 200) : Str::limit(strip_tags((string) $post->content), 200),
            'datePublished' => $post->published_at?->toAtomString(),
            'dateModified' => ($post->updated_at ?? $post->published_at)?->toAtomString(),
            'author' => ['@type' => 'Person', 'name' => $post->author_name ?: ($post->author?->name ?? config('app.name'))],
            'publisher' => ['@type' => 'Organization', 'name' => config('app.name'), 'url' => url('/')],
            'mainEntityOfPage' => route('blog.show', $post->slug),
        ];

        return view('storefront.blog.show', [
            'post' => $post,
            'content' => $this->sanitizer->clean((string) $post->content),
            'related' => $related,
            'breadcrumbItems' => $breadcrumb,
            'metaTitle' => $post->meta_title ?: $post->title,
            'metaDescription' => $post->excerpt ? Str::limit(strip_tags($post->excerpt), 155) : Str::limit(strip_tags((string) $post->content), 155),
            'metaImage' => $post->image ? url('img/'.ltrim($post->image, '/')) : null,
            'ogType' => 'article',
            'canonicalUrl' => route('blog.show', $post->slug),
            'jsonLd' => $schema,
        ]);
    }

    public function blogFeed(): Response
    {
        $posts = BlogPost::query()
            ->where('is_published', true)
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->limit(20)
            ->get();

        $title = config('app.name').' — Blog';

        $items = $posts->map(function (BlogPost $post): string {
            $url = route('blog.show', $post->slug);
            $description = $post->excerpt
                ? Str::limit(strip_tags($post->excerpt), 300)
                : Str::limit(strip_tags((string) $post->content), 300);

            return '<item>'
                .'<title>'.e($post->title).'</title>'
                .'<link>'.e($url).'</link>'
                .'<guid isPermaLink="true">'.e($url).'</guid>'
                .'<description>'.e($description).'</description>'
                .'<pubDate>'.e($post->published_at->toRssString()).'</pubDate>'
                .'</item>';
        })->implode('');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>'
            .'<title>'.e($title).'</title>'
            .'<link>'.e(route('blog.index')).'</link>'
            .'<description>'.e('Artikel dan panduan belanja dari '.config('app.name')).'</description>'
            .'<language>'.e(app()->getLocale()).'</language>'
            .'<atom:link href="'.e(route('blog.feed')).'" rel="self" type="application/rss+xml"/>'
            .$items
            .'</channel></rss>';

        return response($xml, 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=1800',
        ]);
    }

    public function docs()
    {
        $breadcrumb = [['label' => 'Panduan', 'href' => null]];

        return view('storefront.docs.index', [
            'breadcrumbItems' => $breadcrumb,
            'metaTitle' => 'Panduan Belanja',
            'metaDescription' => 'Cara memesan, membayar, melacak dan mengembalikan barang di '.config('app.name').'.',
            'canonicalUrl' => route('docs'),
        ]);
    }
}
