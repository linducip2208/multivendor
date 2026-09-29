@extends('layouts.storefront')

@section('content')
    <div class="sf-container">
        <x-storefront.breadcrumb :items="$breadcrumbItems" />
    </div>

    <section class="sf-section" aria-labelledby="sf-categories-title">
        <div class="sf-container">
            <div class="sf-section-head">
                <div>
                    <h1 class="sf-section-head__title" id="sf-categories-title">Kategori Produk</h1>
                    <p class="sf-muted sf-small sf-mt-0" style="max-width:60ch">
                        Temukan produk berdasarkan kategori. Pilih kategori untuk melihat seluruh produk di dalamnya.
                    </p>
                </div>
                <a href="{{ route('products.index') }}" class="sf-section-head__link">
                    Semua produk <x-storefront.icon name="arrow-right" :size="16" />
                </a>
            </div>

            @if ($categories->isNotEmpty())
                <div class="sf-stack" style="gap:28px">
                    @foreach ($categories as $category)
                        <div class="sf-panel">
                            <div class="sf-row sf-row--between sf-row--wrap" style="gap:12px;margin-bottom:14px">
                                <a href="{{ route('categories.show', $category->slug) }}" class="sf-row" style="gap:12px;min-width:0">
                                    <span class="sf-cat__icon" style="width:44px;height:44px;font-size:1.1rem">
                                        @if ($category->image_url)
                                            <img src="{{ $category->image_url }}" alt="" width="44" height="44" loading="lazy" decoding="async" style="width:100%;height:100%;object-fit:contain">
                                        @else
                                            <x-storefront.icon name="grid" :size="20" />
                                        @endif
                                    </span>
                                    <span style="min-width:0">
                                        <span class="sf-bold sf-truncate" style="display:block;color:var(--sf-text)">{{ $category->name }}</span>
                                        @if ($category->description)
                                            <span class="sf-small sf-muted sf-clamp-2">{{ $category->description }}</span>
                                        @endif
                                    </span>
                                </a>
                                <a href="{{ route('categories.show', $category->slug) }}" class="sf-btn sf-btn--outline sf-btn--sm">
                                    Lihat produk
                                </a>
                            </div>

                            @if ($category->children->isNotEmpty())
                                <div class="sf-row sf-row--wrap" style="gap:8px">
                                    @foreach ($category->children as $child)
                                        <a href="{{ route('categories.show', $child->slug) }}" class="sf-chip">
                                            {{ $child->name }}
                                        </a>
                                    @endforeach
                                </div>
                            @else
                                <p class="sf-small sf-muted sf-mb-0">Belum ada subkategori.</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <x-storefront.empty
                    title="Belum ada kategori"
                    text="Kategori akan tampil begitu administrator menambahkannya."
                    :href="route('products.index')"
                    label="Lihat produk"
                    icon="grid"
                />
            @endif
        </div>
    </section>
@endsection
