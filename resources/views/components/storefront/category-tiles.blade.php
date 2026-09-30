@props(['categories' => []])

{{-- Contract: Collection<Category> (see HomePageService::rootCategories).
     Non-model payloads (stale cache, misconfigured callers) are skipped
     item-by-item so one bad entry never 500s the homepage. --}}
@if (count($categories) > 0)
    <div {{ $attributes->merge(['class' => 'sf-cats']) }}>
        @foreach ($categories as $category)
            @if (! is_object($category))
                @continue
            @endif
            <a href="{{ route('categories.show', $category->slug) }}" class="sf-cat">
                <span class="sf-cat__icon">
                    @if ($category->icon && is_string($category->icon) && str_starts_with(ltrim($category->icon), '<svg'))
                        @php
                            // Ikon kategori adalah data admin; SVG mentah tidak pernah
                            // dirender tanpa sanitasi (tahan stored XSS via on*= / javascript:).
                            $__iconRaw = (string) $category->icon;
                            $__iconTmp = (string) preg_replace('/<\s*script\b.*?(<\s*\/\s*script\s*>|$)/is', '', $__iconRaw);
                            $__iconTmp = (string) preg_replace('/<\s*foreignObject\b.*?(<\s*\/\s*foreignObject\s*>|$)/is', '', $__iconTmp);
                            $__iconTmp = (string) preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $__iconTmp);
                            $__iconSafe = preg_match('/javascript\s*:|data\s*:\s*text\/html|<\s*script|<\s*iframe|\son[a-z]+\s*=/i', $__iconTmp) ? '' : $__iconTmp;
                        @endphp
                        @if ($__iconSafe !== '')
                            {!! $__iconSafe !!}
                        @else
                            <x-storefront.icon name="grid" :size="22" />
                        @endif
                    @elseif ($category->image_url)
                        <img src="{{ $category->image_url }}" alt="{{ $category->name }}" style="width:100%;height:100%;object-fit:contain" loading="lazy" decoding="async">
                    @else
                        <x-storefront.icon name="grid" :size="22" />
                    @endif
                </span>
                <span class="sf-cat__name">{{ $category->name }}</span>
                @if (isset($category->products_count))
                    <span class="sf-cat__count">{{ \App\Support\Currency::number($category->products_count) }} produk</span>
                @endif
            </a>
        @endforeach
    </div>
@endif
