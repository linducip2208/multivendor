<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
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

    /**
     * Batasan ketat khusus media library / picker (terpisah dari
     * MAX_BYTES/ALLOWED legacy agar method existing tidak berubah).
     * Disajikan via route img.serve (/img/...).
     */
    private const MEDIA_MAX_BYTES = 5 * 1024 * 1024;

    private const MEDIA_ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/svg+xml' => 'svg',
        'application/pdf' => 'pdf',
    ];

    private const MEDIA_IMAGE_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/svg+xml',
    ];

    private const BLOCKED_EXTENSIONS = [
        'php', 'phtml', 'phar', 'php5', 'php7', 'phps',
        'exe', 'msi', 'com', 'bat', 'cmd', 'scr', 'ps1',
        'sh', 'bash', 'zsh', 'so', 'dll', 'dylib',
        'js', 'mjs', 'html', 'htm', 'xhtml', 'swf', 'jar',
        'py', 'rb', 'pl', 'cgi', 'asp', 'aspx', 'jsp', 'jspx',
    ];

    private const MEDIA_EXT_BY_MIME = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'image/gif' => ['gif'],
        'image/svg+xml' => ['svg'],
        'application/pdf' => ['pdf'],
    ];

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

    /* ------------------------------------------------------------------
     * Media library JSON + picker backend (baru; method existing di bawah
     * tidak diubah). Perlu di-wiring di routes/admin.php:
     *   GET  file-manager/library -> libraryJson  (name: admin.file-manager.library)
     *   POST file-manager/folder  -> makeFolder   (name: admin.file-manager.folder)
     *   POST file-manager/media   -> uploadMedia  (name: admin.file-manager.media)
     * Daftar disajikan sebagai URL /img/... (route img.serve).
     * --------------------------------------------------------------- */

    public function libraryJson(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin();

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'in:all,image,document'],
            'dir' => ['nullable', 'string', 'max:180'],
        ]);

        $dir = self::sanitizeDir((string) ($validated['dir'] ?? ''));

        if ($dir === null) {
            return response()->json(['message' => 'Direktori tidak valid.'], 400);
        }

        $items = $this->mediaItems($dir, (string) ($validated['q'] ?? ''), (string) ($validated['type'] ?? 'all'));

        return response()->json([
            'data' => $items,
            'meta' => [
                'root' => self::ROOT,
                'dir' => $dir,
                'count' => count($items),
            ],
        ]);
    }

    public function makeFolder(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'parent' => ['nullable', 'string', 'max:180'],
        ]);

        $name = self::sanitizeFolderName($validated['name']);

        if ($name === null) {
            return response()->json(['message' => 'Nama folder tidak valid. Gunakan huruf, angka, spasi, strip, atau underscore (1-60 karakter).'], 422);
        }

        $parent = self::sanitizeDir((string) ($validated['parent'] ?? ''));

        if ($parent === null) {
            return response()->json(['message' => 'Direktori induk tidak valid.'], 422);
        }

        $relative = trim(self::ROOT.'/'.($parent !== '' ? $parent.'/' : '').$name, '/');

        // Pertahanan ganda: pola seperti img.serve + safePath.
        if (str_contains($relative, '..')) {
            return response()->json(['message' => 'Lokasi folder tidak valid.'], 400);
        }

        $this->safePath($relative);

        $disk = Storage::disk('public');

        if ($disk->exists($relative)) {
            return response()->json(['message' => 'Folder sudah ada.', 'path' => $relative], 409);
        }

        $disk->makeDirectory($relative);

        app(AuditLogger::class)->log('file.folder_created', null, [], ['path' => $relative], auth('admin')->id());

        return response()->json(['message' => 'Folder dibuat.', 'path' => $relative], 201);
    }

    public function uploadMedia(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin();

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:'.intdiv(self::MEDIA_MAX_BYTES, 1024),
                'mimetypes:'.implode(',', array_keys(self::MEDIA_ALLOWED)),
            ],
            'folder' => ['nullable', 'string', 'max:180'],
        ], [
            'file.mimetypes' => 'Jenis berkas tidak diizinkan. Gunakan JPG, PNG, WebP, GIF, SVG, atau PDF.',
            'file.max' => 'Ukuran berkas maksimal 5 MB.',
        ]);

        $folder = self::sanitizeDir((string) ($validated['folder'] ?? ''));

        if ($folder === null) {
            return response()->json(['message' => 'Folder tujuan tidak valid.'], 422);
        }

        $upload = $request->file('file');
        $clientName = (string) $upload->getClientOriginalName();

        if (self::hasRiskyName($clientName)) {
            return response()->json(['message' => 'Nama berkas ditolak (executable atau double-extension).'], 422);
        }

        $mime = (string) $upload->getMimeType();

        if (! isset(self::MEDIA_ALLOWED[$mime])) {
            return response()->json(['message' => 'Jenis berkas "'.$mime.'" tidak diizinkan.'], 422);
        }

        $clientExt = strtolower((string) pathinfo($clientName, PATHINFO_EXTENSION));
        $expected = self::MEDIA_EXT_BY_MIME[$mime] ?? [];

        if ($clientExt === '' || ! in_array($clientExt, $expected, true)) {
            return response()->json(['message' => 'Ekstensi berkas tidak sesuai dengan isi berkas.'], 422);
        }

        $bytes = (string) file_get_contents($upload->getRealPath());

        if ($mime === 'image/svg+xml' && preg_match('/<\s*script|onload\s*=|javascript\s*:/i', $bytes) === 1) {
            return response()->json(['message' => 'SVG mengandung skrip aktif dan ditolak.'], 422);
        }

        if ($mime === 'application/pdf' && ! str_starts_with(ltrim($bytes), '%PDF')) {
            return response()->json(['message' => 'Berkas PDF tidak valid.'], 422);
        }

        if ($mime === 'image/jpeg' && function_exists('imagecreatefromstring')) {
            $image = @imagecreatefromstring($bytes);
            if ($image !== false) {
                ob_start();
                imagejpeg($image, null, 85);
                $bytes = (string) ob_get_clean();
                imagedestroy($image);
            }
        }

        $name = now()->format('Ymd').'-'.bin2hex(random_bytes(8)).'.'.self::MEDIA_ALLOWED[$mime];
        $path = self::ROOT.'/'.($folder !== '' ? $folder.'/' : '').$name;

        $absolute = $this->safePath($path);

        File::ensureDirectoryExists(dirname($absolute));
        File::put($absolute, $bytes);

        app(AuditLogger::class)->log('file.media_uploaded', null, [], [
            'path' => $path,
            'mime' => $mime,
            'bytes' => strlen($bytes),
        ], auth('admin')->id());

        return response()->json([
            'message' => 'Berkas disimpan.',
            'path' => $path,
            'url' => self::toImgUrl($path),
        ], 201);
    }

    /**
     * Aturan validasi ketat untuk form upload media (maks 5 MB).
     *
     * @return array<string, mixed>
     */
    public static function mediaRules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.intdiv(self::MEDIA_MAX_BYTES, 1024),
                'mimetypes:'.implode(',', array_keys(self::MEDIA_ALLOWED)),
            ],
        ];
    }

    public static function mediaMaxKb(): int
    {
        return intdiv(self::MEDIA_MAX_BYTES, 1024);
    }

    /**
     * @return list<string>
     */
    public static function mediaAllowedMimes(): array
    {
        return array_keys(self::MEDIA_ALLOWED);
    }

    public static function isImageMime(string $mime): bool
    {
        return in_array($mime, self::MEDIA_IMAGE_MIMES, true);
    }

    /**
     * Tolak executable dan double-extension (mis. foto.php.jpg, x.pdf.exe).
     */
    public static function hasRiskyName(string $filename): bool
    {
        if ($filename === '' || str_contains($filename, "\0") || str_contains($filename, '..')) {
            return true;
        }

        $base = strtolower(basename(str_replace('\\', '/', $filename)));

        if ($base === '' || $base === '.' || $base === '..') {
            return true;
        }

        $parts = explode('.', $base);

        if (count($parts) < 2 || end($parts) === '') {
            return true;
        }

        $final = (string) end($parts);

        if (in_array($final, self::BLOCKED_EXTENSIONS, true)) {
            return true;
        }

        foreach (array_slice($parts, 0, -1) as $middle) {
            if (in_array($middle, self::BLOCKED_EXTENSIONS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validasi nama folder; kembalikan nama bersih atau null bila ditolak.
     */
    public static function sanitizeFolderName(mixed $name): ?string
    {
        if (! is_string($name)) {
            return null;
        }

        $clean = trim($name);

        if ($clean === '' || strlen($clean) > 60) {
            return null;
        }

        if (str_contains($clean, '..') || str_contains($clean, '/') || str_contains($clean, '\\') || str_contains($clean, "\0")) {
            return null;
        }

        if (preg_match('/\A[A-Za-z0-9 _-]+\z/', $clean) !== 1) {
            return null;
        }

        if (trim($clean, ' .') === '' || in_array(strtolower($clean), ['con', 'prn', 'aux', 'nul'], true)) {
            return null;
        }

        return $clean;
    }

    /**
     * Normalisasi subdirektori di bawah uploads/; null bila traversal.
     */
    public static function sanitizeDir(mixed $dir): ?string
    {
        if (! is_string($dir)) {
            return null;
        }

        $normalised = str_replace('\\', '/', trim($dir));

        if ($normalised !== '' && str_starts_with($normalised, '/')) {
            return null;
        }

        $normalised = trim($normalised, '/');

        if ($normalised === '') {
            return '';
        }

        if (
            str_contains($normalised, '..') || str_contains($normalised, "\0")
            || str_starts_with($normalised, '/') || preg_match('#^[A-Za-z]:#', $normalised) === 1
        ) {
            return null;
        }

        if (strlen($normalised) > 180) {
            return null;
        }

        foreach (explode('/', $normalised) as $segment) {
            if ($segment === '' || $segment === '.' || self::sanitizeFolderName($segment) === null) {
                return null;
            }
        }

        return $normalised;
    }

    /**
     * URL publik via route img.serve (/img/...).
     */
    public static function toImgUrl(string $path): string
    {
        $clean = ltrim(str_replace('\\', '/', $path), '/');

        return url('img/'.$clean);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mediaItems(string $dir, string $q, string $type): array
    {
        $disk = Storage::disk('public');
        $base = self::ROOT.($dir !== '' ? '/'.$dir : '');
        $items = [];

        foreach ($disk->allFiles($base) as $file) {
            $basename = basename($file);

            if ($q !== '' && stripos($basename, $q) === false && stripos($file, $q) === false) {
                continue;
            }

            try {
                $mime = (string) ($disk->mimeType($file) ?: 'application/octet-stream');
            } catch (\Throwable) {
                $mime = 'application/octet-stream';
            }

            $isImage = str_starts_with($mime, 'image/');
            $kind = $isImage ? 'image' : ($mime === 'application/pdf' ? 'document' : 'other');

            if ($type === 'image' && ! $isImage) {
                continue;
            }

            if ($type === 'document' && $mime !== 'application/pdf') {
                continue;
            }

            try {
                $size = (int) $disk->size($file);
                $modified = (int) $disk->lastModified($file);
            } catch (\Throwable) {
                $size = 0;
                $modified = 0;
            }

            $url = self::toImgUrl($file);

            $items[] = [
                'name' => $basename,
                'path' => $file,
                'url' => $url,
                'thumb' => $isImage ? $url : null,
                'mime' => $mime,
                'kind' => $kind,
                'is_image' => $isImage,
                'size' => $size,
                'size_kb' => max(1, (int) round($size / 1024)),
                'modified' => $modified,
                'modified_label' => $modified > 0 ? date('Y-m-d H:i', $modified) : '—',
            ];
        }

        usort($items, fn (array $a, array $b): int => $b['modified'] <=> $a['modified']);

        return $items;
    }

    /* ── ADITIF deepening: tag/koleksi/alt massal + tak terpakai ──
     * Untuk integrator: daftarkan route sendiri, mis.:
     *   GET  admin/file-manager/meta   -> metaOverview (name: admin.file-manager.meta)
     *   POST admin/file-manager/meta   -> saveMeta     (name: admin.file-manager.meta.save)
     *   POST admin/file-manager/alt    -> bulkAlt      (name: admin.file-manager.meta.alt)
     * Memakai ulang view file-manager.index (payload @isset).
     */
    public function metaOverview(): \Illuminate\View\View
    {
        $this->ensureSuperAdmin();
        $meta = app(\App\Services\Cms\MediaMetaService::class);

        return view('admin.file-manager.index', [
            'files' => $this->listing(),
            'limits' => [
                'max_kb' => intdiv(self::MAX_BYTES, 1024),
                'types' => array_keys(self::ALLOWED),
            ],
            'mediaInventory' => $meta->inventory(),
        ]);
    }

    public function saveMeta(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin();

        $validated = $request->validate([
            'path' => ['required', 'string', 'max:255', 'not_regex:/\.\./'],
            'tags' => ['nullable', 'string', 'max:600'],
            'collection' => ['nullable', 'string', 'max:80'],
            'alt' => ['nullable', 'string', 'max:200'],
        ]);

        $tags = $validated['tags'] ?? '';
        app(\App\Services\Cms\MediaMetaService::class)->tag(
            (string) $validated['path'],
            is_string($tags) ? preg_split('/[,;\n]+/', $tags) ?: [] : [],
            (string) ($validated['collection'] ?? ''),
            (string) ($validated['alt'] ?? '')
        );

        return back()->with('success', 'Meta media disimpan.');
    }

    public function bulkAlt(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin();

        $validated = $request->validate([
            'alts' => ['required', 'array', 'max:200'],
            'alts.*' => ['nullable', 'string', 'max:200'],
        ]);

        $count = app(\App\Services\Cms\MediaMetaService::class)->bulkAlt((array) $validated['alts']);

        return back()->with('success', $count.' alt teks disimpan massal.');
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
