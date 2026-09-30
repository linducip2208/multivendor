<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Services\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $query = BlogPost::with(['author', 'categories'])->latest();
        if ($request->filled('search')) {
            $query->where('title', 'like', "%{$request->search}%");
        }
        // Filter status publisitas: draf | terjadwal | terbit.
        if ($request->filled('status')) {
            if ($request->status === 'draft') {
                $query->where(fn ($q) => $q->where('is_published', false)->orWhereNull('published_at'));
            } elseif ($request->status === 'scheduled') {
                $query->where('is_published', true)->where('published_at', '>', now());
            } elseif ($request->status === 'published') {
                $query->where('is_published', true)->where('published_at', '<=', now());
            }
        }
        $posts = $query->paginate(15)->withQueryString();

        foreach ($posts as $post) {
            $post->schedule_status = self::scheduleStatus($post);
        }

        return view('admin.blog.index', compact('posts'));
    }

    public function create()
    {
        $categories = BlogCategory::all();

        return view('admin.blog.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
            'featured_image' => 'nullable|string|max:500',
            'categories' => 'array',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:255',
        ]);

        $validated['author_id'] = auth('admin')->id();
        $validated['content'] = app(HtmlSanitizer::class)->sanitize($validated['content']);
        $validated['slug'] = Str::slug($validated['title']);
        $originalSlug = $validated['slug'];
        $counter = 1;
        while (BlogPost::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $originalSlug.'-'.$counter++;
        }
        // Jadwal terbit: bila dicentang Terbit tanpa tanggal -> sekarang;
        // bila tanggal di masa depan -> Terjadwal (storefront otomatis
        // menyembunyikan via filter published_at<=now, tanpa command baru).
        if ($request->boolean('is_published')) {
            $validated['is_published'] = true;
            $validated['published_at'] = $validated['published_at'] ?? now();
        } else {
            $validated['is_published'] = false;
            $validated['published_at'] = $validated['published_at'] ?? null;
        }

        $post = BlogPost::create($validated);

        if ($request->has('categories')) {
            $post->categories()->sync($request->categories);
        }

        return redirect()->route('admin.blog.index')->with('success', 'Artikel berhasil dibuat.');
    }

    public function edit(BlogPost $blog)
    {
        $categories = BlogCategory::all();

        return view('admin.blog.edit', compact('blog', 'categories'));
    }

    public function update(Request $request, BlogPost $blog)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
            'featured_image' => 'nullable|string|max:500',
            'categories' => 'array',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:255',
        ]);

        if ($validated['title'] !== $blog->title) {
            $validated['slug'] = Str::slug($validated['title']);
            $counter = 1;
            $original = $validated['slug'];
            while (BlogPost::where('slug', $validated['slug'])->where('id', '!=', $blog->id)->exists()) {
                $validated['slug'] = $original.'-'.$counter++;
            }
        }
        $validated['content'] = app(HtmlSanitizer::class)->sanitize($validated['content']);

        if ($request->boolean('is_published') && ! $blog->is_published) {
            $validated['published_at'] = $validated['published_at'] ?? now();
        }

        if (! $request->boolean('is_published')) {
            $validated['is_published'] = false;
        }

        $blog->update($validated);

        if ($request->has('categories')) {
            $blog->categories()->sync($request->categories);
        }

        return redirect()->route('admin.blog.index')->with('success', 'Artikel diperbarui.');
    }

    public function destroy(BlogPost $blog)
    {
        $blog->delete();

        return back()->with('success', 'Artikel dihapus.');
    }

    /* ── ADITIF deepening: workflow + terjemahan per bahasa ──
     * Untuk integrator: daftarkan route sendiri, mis.:
     *   PUT  admin/blog/{blog}/locale   -> updateLocale (name: admin.blog.locale)
     *   POST admin/blog/{blog}/workflow -> updateWorkflow (name: admin.blog.workflow)
     */

    /**
     * Simpan terjemahan per bahasa untuk satu artikel (overlay
     * blog_post_translations existing, tanpa ubah kolom induk).
     */
    public function updateLocale(Request $request, BlogPost $blog)
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', \Illuminate\Validation\Rule::in(['id', 'en'])],
            'title' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string', 'max:100000'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ]);

        $locale = (string) $validated['locale'];
        $payload = [];
        foreach (['title', 'slug', 'excerpt', 'content', 'meta_title', 'meta_description'] as $field) {
            if (array_key_exists($field, $validated) && $validated[$field] !== null) {
                $payload[$field] = $field === 'content'
                    ? app(\App\Services\HtmlSanitizer::class)->sanitize((string) $validated[$field])
                    : (string) $validated[$field];
            }
        }
        if ($payload === []) {
            return back()->with('success', 'Tidak ada perubahan terjemahan ('.$locale.').');
        }

        try {
            \Illuminate\Support\Facades\DB::table('blog_post_translations')->updateOrInsert(
                ['blog_post_id' => $blog->id, 'locale' => $locale],
                $payload + ['updated_at' => now(), 'created_at' => now()]
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal menyimpan terjemahan: '.mb_substr($e->getMessage(), 0, 160))->withInput();
        }

        return back()->with('success', 'Terjemahan '.$locale.' untuk "'.$blog->title.'" disimpan.');
    }

    /**
     * Terapkan workflow draft/review/published/scheduled per bahasa.
     */
    public function updateWorkflow(Request $request, BlogPost $blog)
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', \Illuminate\Validation\Rule::in(['id', 'en'])],
            'state' => ['required', 'string', \Illuminate\Validation\Rule::in(\App\Services\Cms\ContentWorkflowService::STATES)],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        try {
            $result = app(\App\Services\Cms\ContentWorkflowService::class)->transition(
                'blog', $blog->id, (string) $validated['locale'],
                (string) $validated['state'],
                isset($validated['scheduled_at']) ? (string) $validated['scheduled_at'] : null,
                auth('admin')->id()
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['scheduled_at' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Workflow ('.$validated['locale'].') kini '.$result['state'].'.');
    }

    /**
     * Status publisitas untuk badge admin: Draf | Terjadwal | Terbit.
     * Terbit otomatis terjadi saat published_at<=now karena storefront
     * (PageController::blogIndex/blogShow) memfilter demikian — tanpa command baru.
     *
     * @return array{key: string, label: string, color: string}
     */
    public static function scheduleStatus(BlogPost $post): array
    {
        if (! $post->is_published || $post->published_at === null) {
            return ['key' => 'draft', 'label' => 'Draf', 'color' => 'secondary'];
        }

        if ($post->published_at->isFuture()) {
            return ['key' => 'scheduled', 'label' => 'Terjadwal', 'color' => 'warning'];
        }

        return ['key' => 'published', 'label' => 'Terbit', 'color' => 'success'];
    }
}
