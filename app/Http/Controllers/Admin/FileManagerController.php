<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Upload manager.
 *
 * Uploads are validated by the bytes Laravel actually read (`mimetypes`), not
 * by the extension the browser claimed. EXIF metadata is stripped from images
 * before anything is written, the stored name is randomised, and the resolved
 * write path is asserted to live under the upload root.
 */
class FileManagerController extends Controller
{
    private const ROOT = 'uploads';

    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'video/mp4' => 'mp4',
    ];

    private const MAX_BYTES = 10 * 1024 * 1024;

    public function index(): View
    {
        $this->ensureSuperAdmin();

        return view('admin.file-manager.index', [
            'files' => $this->listing(),
            'limits' => [
                'max_kb' => intdiv(self::MAX_BYTES, 1024),
                'types' => array_keys(self::ALLOWED),
            ],
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin();

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:'.intdiv(self::MAX_BYTES, 1024),
                'mimetypes:'.implode(',', array_keys(self::ALLOWED)),
            ],
        ], [
            'file.mimetypes' => 'Jenis berkas tidak diizinkan. Gunakan gambar, PDF, teks, CSV, atau MP4.',
        ]);

        $upload = $request->file('file');
        $mime = (string) $upload->getMimeType();

        if (! isset(self::ALLOWED[$mime])) {
            return back()->with('error', 'Jenis berkas "'.$mime.'" tidak diizinkan.')->withInput();
        }

        $bytes = (string) file_get_contents($upload->getRealPath());

        if ($mime === 'image/jpeg' && function_exists('imagecreatefromstring')) {
            $image = @imagecreatefromstring($bytes);
            if ($image !== false) {
                ob_start();
                imagejpeg($image, null, 85);
                $bytes = (string) ob_get_clean();
                imagedestroy($image);
            }
        }

        $name = now()->format('Ymd').'-'.bin2hex(random_bytes(8)).'.'.self::ALLOWED[$mime];
        $path = self::ROOT.'/'.$name;

        $absolute = $this->safePath($path);

        File::ensureDirectoryExists(dirname($absolute));
        File::put($absolute, $bytes);

        app(AuditLogger::class)->log('file.uploaded', null, [], [
            'path' => $path,
            'mime' => $mime,
            'bytes' => strlen($bytes),
        ], auth('admin')->id());

        return back()->with('success', 'Berkas disimpan sebagai '.$path.'.');
    }

    public function show(Request $request, string $path): BinaryFileResponse
    {
        $this->ensureSuperAdmin();

        $absolute = $this->safePath($path);

        abort_unless(File::isFile($absolute), 404);

        return response()->download($absolute, basename($absolute));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin();

        $validated = $request->validate([
            'path' => ['required', 'string', 'max:255', 'not_regex:/\.\./'],
        ]);

        $path = (string) $validated['path'];
        $absolute = $this->safePath($path);

        abort_unless(File::isFile($absolute), 404);

        File::delete($absolute);

        app(AuditLogger::class)->log('file.deleted', null, ['path' => $path], [], auth('admin')->id());

        return back()->with('success', 'Berkas dihapus.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listing(): array
    {
        $disk = Storage::disk('public');
        $files = [];

        foreach ($disk->allFiles(self::ROOT) as $file) {
            $files[] = [
                'name' => basename($file),
                'path' => $file,
                'size' => (int) $disk->size($file),
                'size_kb' => max(1, (int) round($disk->size($file) / 1024)),
                'mime' => (string) ($disk->mimeType($file) ?: 'application/octet-stream'),
                'url' => (string) $disk->url($file),
                'modified' => (int) $disk->lastModified($file),
                'modified_label' => date('Y-m-d H:i', (int) $disk->lastModified($file)),
            ];
        }

        usort($files, fn (array $a, array $b): int => $b['modified'] <=> $a['modified']);

        return $files;
    }

    /**
     * Resolve a caller-supplied path and refuse anything outside the upload root.
     */
    private function safePath(string $path): string
    {
        $normalised = str_replace('\\', '/', $path);
        $normalised = ltrim($normalised, '/');

        abort_if(
            str_contains($normalised, '..') || str_contains($normalised, "\0") || preg_match('#^[A-Za-z]:#', $normalised) === 1,
            400,
            'Lokasi berkas tidak valid.',
        );

        abort_unless(
            $normalised === self::ROOT || str_starts_with($normalised, self::ROOT.'/'),
            403,
            'Hanya berkas di dalam direktori upload yang dapat diakses.',
        );

        $root = realpath(storage_path('app/public/'.self::ROOT));
        $absolute = storage_path('app/public/'.str_replace('/', DIRECTORY_SEPARATOR, $normalised));

        if ($root !== false) {
            $parent = realpath(dirname($absolute));

            abort_if($parent === false || ! str_starts_with($parent.DIRECTORY_SEPARATOR, $root.DIRECTORY_SEPARATOR), 403, 'Lokasi berkas di luar upload root.');
        }

        return $absolute;
    }

    private function ensureSuperAdmin(): void
    {
        abort_unless(auth('admin')->user()?->isSuperAdmin(), 403, 'Hanya super admin yang dapat mengelola berkas.');
    }
}
