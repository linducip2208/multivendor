<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FileManagerController extends Controller
{
    public function index()
    {
        $files = collect(\Illuminate\Support\Facades\Storage::disk('public')->allFiles())
            ->map(fn($f) => ['name' => basename($f), 'path' => $f, 'size' => \Illuminate\Support\Facades\Storage::disk('public')->size($f), 'url' => \Illuminate\Support\Facades\Storage::disk('public')->url($f), 'modified' => \Illuminate\Support\Facades\Storage::disk('public')->lastModified($f)])
            ->sortByDesc('modified');
        return view('admin.file-manager.index', compact('files'));
    }

    public function upload(Request $request)
    {
        $request->validate(['file' => 'required|file|max:10240']);
        $path = $request->file('file')->store('uploads', 'public');
        return back()->with('success', 'File diupload: ' . $path);
    }

    public function destroy(Request $request)
    {
        \Illuminate\Support\Facades\Storage::disk('public')->delete($request->path);
        return back()->with('success', 'File dihapus.');
    }
}
