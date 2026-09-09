<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileManagerController extends Controller
{
    public function index()
    {
        $this->ensureSuperAdmin();
        $disk = Storage::disk('public');
        $files = collect($disk->allFiles('uploads'))
            ->map(fn ($file) => ['name' => basename($file), 'path' => $file, 'size' => $disk->size($file), 'url' => $disk->url($file), 'modified' => $disk->lastModified($file)])
            ->sortByDesc('modified');

        return view('admin.file-manager.index', compact('files'));
    }

    public function upload(Request $request)
    {
        $this->ensureSuperAdmin();
        $request->validate(['file' => 'required|file|max:10240|mimetypes:image/jpeg,image/png,image/webp,application/pdf,text/plain,text/csv,video/mp4']);
        $path = $request->file('file')->store('uploads', 'public');
        app(AuditLogger::class)->log('file.uploaded', null, [], ['path' => $path]);

        return back()->with('success', 'File diupload: '.$path);
    }

    public function destroy(Request $request)
    {
        $this->ensureSuperAdmin();
        $validated = $request->validate(['path' => ['required', 'string', 'max:255']]);
        $path = str_replace('\\', '/', ltrim($validated['path'], '/'));
        abort_unless(str_starts_with($path, 'uploads/') && ! str_contains($path, '..') && Storage::disk('public')->exists($path), 404);
        Storage::disk('public')->delete($path);
        app(AuditLogger::class)->log('file.deleted', null, ['path' => $path]);

        return back()->with('success', 'File dihapus.');
    }

    private function ensureSuperAdmin(): void
    {
        abort_unless(auth('admin')->user()?->isSuperAdmin(), 403);
    }
}
