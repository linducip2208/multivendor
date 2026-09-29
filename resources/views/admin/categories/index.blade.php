@extends('layouts.admin')

@section('title', 'Manajemen Kategori')

@section('content')
@php
    $faIconMap = ['fa-laptop' => 'monitor', 'fa-mobile-alt' => 'phone', 'fa-mobile' => 'phone', 'fa-tshirt' => 'tag', 'fa-home' => 'home', 'fa-box' => 'box', 'fa-boxes' => 'box', 'fa-shopping-bag' => 'shopping-bag', 'fa-shopping-cart' => 'shopping-cart', 'fa-heart' => 'heart', 'fa-book' => 'book', 'fa-gamepad' => 'grid', 'fa-baby' => 'user', 'fa-car' => 'truck', 'fa-gift' => 'package', 'fa-utensils' => 'box', 'fa-blender' => 'box', 'fa-couch' => 'home', 'fa-dumbbell' => 'activity', 'fa-paw' => 'heart', 'fa-gem' => 'award', 'fa-plug' => 'plug', 'fa-wrench' => 'settings'];
@endphp
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><x-admin.icon name="list" :size="16" class="me-2 text-success" /> Kategori</h4>
        <p class="text-muted small mb-0">Kelola kategori produk marketplace</p>
    </div>
    <a href="{{ route('admin.categories.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" class="me-2" /> Tambah Kategori</a>
</div>

<x-admin.card :padding="false">
    <div class="card-body p-0">
        <div class="p-3 border-bottom">
            <form method="GET" class="row g-2">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Cari kategori..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100"><x-admin.icon name="search" :size="16" class="me-1" /> Cari</button>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Kategori</th>
                        <th>Sub-Kategori</th>
                        <th>Status</th>
                        <th>Produk</th>
                        <th class="text-end pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $cat)
                    <tr>
                        <td class="ps-3">
                            <div class="d-flex align-items-center gap-2">
                                <x-admin.badge color="success" class="rounded-3 p-2"><x-admin.icon :name="$faIconMap[$cat->icon ?? ''] ?? \Illuminate\Support\Str::after($cat->icon ?? 'fa-folder', 'fa-')" :size="16" /></x-admin.badge>
                                <div>
                                    <div class="fw-semibold">{{ $cat->name }}</div>
                                    <small class="text-muted">{{ $cat->slug }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($cat->children->count() > 0)
                                @foreach($cat->children as $child)
                                    <x-admin.badge color="dark" class="me-1" :text="$child->name" />
                                @endforeach
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                        <td>
                            <x-admin.badge :color="$cat->status ? 'success' : 'secondary'" :text="$cat->status ? 'Aktif' : 'Nonaktif'" />
                        </td>
                        <td>{{ $cat->products_count ?? 0 }}</td>
                        <td class="text-end pe-3">
                            <a href="{{ route('admin.categories.edit', $cat) }}" class="btn btn-sm btn-outline-primary"><x-admin.icon name="edit" :size="16" /></a>
                            <form action="{{ route('admin.categories.destroy', $cat) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus kategori ini?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><x-admin.icon name="trash" :size="16" /></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center py-5 text-muted">Belum ada kategori</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($categories->hasPages())
        <div class="p-3 border-top"><x-admin.pagination :paginator="$categories" /></div>
        @endif
    </div>
</x-admin.card>
@endsection
