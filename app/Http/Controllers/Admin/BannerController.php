<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index()
    {
        $banners = Banner::latest()->paginate(10);
        $now = now();
        $scheduled = $banners->getCollection()->map(function (Banner $banner) use ($now): array {
            $startsAt = $this->column($banner, 'starts_at');
            $endsAt = $this->column($banner, 'ends_at');
            $active = (bool) $banner->status;
            if ($startsAt !== null && $now->lessThan($startsAt)) {
                return ['state' => 'terjadwal', 'badge' => 'info', 'detail' => 'Tayang '.$startsAt->format('d M Y H:i')];
            }
            if ($endsAt !== null && $now->greaterThan($endsAt)) {
                return ['state' => 'berakhir', 'badge' => 'secondary', 'detail' => 'Berakhir '.$endsAt->format('d M Y H:i')];
            }

            return ['state' => $active ? 'tayang' : 'nonaktif', 'badge' => $active ? 'success' : 'secondary', 'detail' => $active ? 'Sedang tayang' : 'Nonaktif'];
        })->all();

        return view('admin.banners.index', compact('banners', 'scheduled'));
    }

    public function create()
    {
        return view('admin.banners.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'image' => 'required|string|max:500',
            'link' => 'nullable|string|max:500',
            'position' => 'required|in:hero,sidebar,footer,popup',
            'sort_order' => 'integer|min:0',
            'status' => 'boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);
        $banner = Banner::create($this->schedulable($validated));
        app(\App\Services\AuditLogger::class)->log('banner.created', $banner, [], ['title' => $banner->title], auth('admin')->id());

        return redirect()->route('admin.banners.index')->with('success', 'Banner dibuat.');
    }

    public function edit(Banner $banner)
    {
        return view('admin.banners.edit', compact('banner'));
    }

    public function update(Request $request, Banner $banner)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'image' => 'nullable|string|max:500',
            'link' => 'nullable|string|max:500',
            'position' => 'required|in:hero,sidebar,footer,popup',
            'sort_order' => 'integer|min:0',
            'status' => 'boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);
        $before = ['title' => $banner->title, 'status' => $banner->status, 'position' => $banner->position];
        $banner->update($this->schedulable($validated));
        app(\App\Services\AuditLogger::class)->log('banner.updated', $banner, $before, [
            'title' => $banner->title,
            'status' => $banner->status,
            'position' => $banner->position,
        ], auth('admin')->id());

        return redirect()->route('admin.banners.index')->with('success', 'Banner diperbarui.');
    }

    public function destroy(Banner $banner)
    {
        $snapshot = ['title' => $banner->title];
        $banner->delete();
        app(\App\Services\AuditLogger::class)->log('banner.deleted', null, $snapshot, [], auth('admin')->id());

        return back()->with('success', 'Banner dihapus.');
    }

    /**
     * Penjadwalan banner: hanya tulis starts_at/ends_at bila kolom tersedia
     * (tanpa migration baru).
     */
    private function schedulable(array $validated): array
    {
        $out = $validated;
        foreach (['starts_at', 'ends_at'] as $column) {
            if (! \Illuminate\Support\Facades\Schema::hasColumn('banners', $column)) {
                unset($out[$column]);
            }
        }

        return $out;
    }

    private function column(Banner $banner, string $column): ?\Carbon\Carbon
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasColumn('banners', $column)) {
                return null;
            }
            $value = $banner->getAttribute($column);

            return $value !== null ? \Carbon\Carbon::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
