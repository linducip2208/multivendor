<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Backoffice\SystemHealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class SystemHealthController extends Controller
{
    public function __construct(private readonly SystemHealthService $health) {}

    public function index(): View
    {
        return view('admin.system.health', [
            'report' => $this->health->health(),
            'writable' => $this->health->storageWriteProbe(),
        ]);
    }

    public function logs(Request $request): View
    {
        $name = (string) $request->query('file', '');
        $files = $this->health->logFiles();
        $selected = $name !== '' ? $name : (string) ($files[0]['name'] ?? '');

        return view('admin.system.logs', [
            'files' => $files,
            'selected' => $selected,
            'content' => $selected !== '' ? $this->health->readLog($selected, (int) $request->query('lines', 300)) : ['lines' => [], 'name' => null, 'bytes' => 0, 'modified' => ''],
        ]);
    }

    public function clearLogs(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin();

        $validated = $request->validate([
            'file' => ['nullable', 'string', 'max:255'],
        ]);

        $file = trim((string) ($validated['file'] ?? ''));

        if ($file === '' || $file === '*') {
            $count = $this->health->clearAllLogs();
            app(AuditLogger::class)->log('system.logs_cleared', null, [], ['count' => $count], auth('admin')->id());

            return back()->with('success', $count.' berkas log dihapus.');
        }

        $deleted = $this->health->clearLog($file);

        if (! $deleted) {
            return back()->with('error', 'Berkas log tidak ditemukan atau berada di luar direktori log.');
        }

        app(AuditLogger::class)->log('system.log_cleared', null, [], ['file' => basename($file)], auth('admin')->id());

        return back()->with('success', 'Berkas log '.basename($file).' dihapus.');
    }

    public function queue(): View
    {
        return view('admin.system.queue', $this->health->queueHealth());
    }

    public function clearCache(): RedirectResponse
    {
        $this->ensureSuperAdmin();

        $result = $this->health->clearCacheReport();

        app(AuditLogger::class)->log('system.cache_cleared', null, [], ['cleared' => $result['cleared']], auth('admin')->id());

        return back()->with('success', 'Cache dibersihkan: '.implode(', ', $result['cleared']).'.');
    }

    public function auditLogs(Request $request): View
    {
        return view('admin.audit-logs', $this->health->auditTrail(
            (int) $request->query('page', 1),
            (int) $request->query('per_page', 25),
            trim((string) $request->query('action', '')),
            trim((string) $request->query('entity', '')),
        ));
    }

    public function maintenance(): View
    {
        return view('admin.system.maintenance', [
            'down' => app()->isDownForMaintenance(),
            'env' => (string) config('app.env'),
            'cache' => $this->health->clearCacheReport(),
        ]);
    }

    public function toggleMaintenance(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin();

        $validated = $request->validate([
            'secret' => ['nullable', 'string', 'max:60'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        if (app()->isDownForMaintenance()) {
            Artisan::call('up');
            $detail = 'Mode maintenance dinonaktifkan.';
        } else {
            $downFile = storage_path('framework/down');
            \Illuminate\Support\Facades\File::put($downFile, json_encode([
                'time' => now()->toDateTimeString(),
                'message' => (string) ($validated['message'] ?? 'Situs sedang dalam pemeliharaan.'),
                'secret' => (string) ($validated['secret'] ?? ''),
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            $detail = 'Mode maintenance aktif. Hapus berkas storage/framework/down atau gunakan tombol di halaman ini untuk menonaktifkan.';
        }

        app(AuditLogger::class)->log('system.maintenance_toggled', null, [], ['down' => app()->isDownForMaintenance()], auth('admin')->id());

        return back()->with('success', $detail);
    }

    public function optimizeDb(): RedirectResponse
    {
        $this->ensureSuperAdmin();

        $connection = (string) config('database.default');
        $driver = (string) config('database.connections.'.$connection.'.driver');
        $database = (string) config('database.connections.'.$connection.'.database');

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return back()->with('error', 'Optimasi tabel hanya tersedia untuk MySQL/MariaDB.');
        }

        $tables = array_column(\Illuminate\Support\Facades\DB::select('SHOW TABLE STATUS'), 'Name');
        $optimised = 0;

        foreach ($tables as $table) {
            if (! is_string($table) || preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
                continue;
            }

            \Illuminate\Support\Facades\DB::statement('OPTIMIZE TABLE `'.$database.'`.`'.$table.'`');
            $optimised++;
        }

        app(AuditLogger::class)->log('system.database_optimized', null, [], ['tables' => $optimised], auth('admin')->id());

        return back()->with('success', $optimised.' tabel dioptimasi.');
    }

    private function ensureSuperAdmin(): void
    {
        abort_unless(auth('admin')->user()?->isSuperAdmin(), 403, 'Hanya super admin yang dapat menjalankan tindakan sistem.');
    }
}
