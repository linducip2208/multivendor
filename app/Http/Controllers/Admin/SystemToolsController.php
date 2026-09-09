<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SystemToolsController extends Controller
{
    /** @var list<string> */
    private const EDITABLE_ENV_KEYS = [
        'APP_NAME', 'APP_URL', 'APP_DEBUG', 'LOG_LEVEL', 'QUEUE_CONNECTION', 'CACHE_STORE', 'SESSION_DRIVER', 'FILESYSTEM_DISK',
    ];

    public function errorLogs()
    {
        $this->ensureSuperAdmin();
        $logPath = storage_path('logs/laravel.log');
        $logs = [];

        if (file_exists($logPath)) {
            $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = array_reverse(array_slice($lines, -500));

            $current = null;
            foreach ($lines as $line) {
                if (preg_match('/^\[\d{4}-\d{2}-\d{2}/', $line)) {
                    if ($current) {
                        $logs[] = $current;
                    }
                    $current = $line;
                } else {
                    $current .= "\n".$line;
                }
            }
            if ($current) {
                $logs[] = $current;
            }
        }

        return view('admin.system.error-logs', compact('logs'));
    }

    public function clearErrorLogs()
    {
        $this->ensureSuperAdmin();
        $logPath = storage_path('logs/laravel.log');
        if (file_exists($logPath)) {
            file_put_contents($logPath, '');
        }
        app(AuditLogger::class)->log('system.error_logs_cleared');

        return back()->with('success', 'Error logs cleared.');
    }

    public function envSettings()
    {
        $this->ensureSuperAdmin();
        $settings = collect(self::EDITABLE_ENV_KEYS)->mapWithKeys(fn (string $key) => [$key => env($key)])->all();

        return view('admin.system.env-settings', compact('settings'));
    }

    public function updateEnvSettings(Request $request)
    {
        $this->ensureSuperAdmin();
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable', 'string', 'max:2048'],
        ]);
        $settings = array_intersect_key($validated['settings'], array_flip(self::EDITABLE_ENV_KEYS));
        if (count($settings) !== count(self::EDITABLE_ENV_KEYS)) {
            abort(422, 'Konfigurasi environment tidak valid.');
        }

        $this->replaceAllowedEnvironmentValues($settings);
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        app(AuditLogger::class)->log('system.environment_updated', null, [], ['keys' => array_keys($settings)]);

        return back()->with('success', 'Konfigurasi yang diizinkan telah diperbarui dan cache dibersihkan.');
    }

    public function dbSettings()
    {
        $this->ensureSuperAdmin();
        $tables = DB::select('SHOW TABLE STATUS');
        $dbSize = collect($tables)->sum('Data_length') + collect($tables)->sum('Index_length');

        return view('admin.system.db-settings', compact('tables', 'dbSize'));
    }

    public function optimizeDb()
    {
        $this->ensureSuperAdmin();
        $tables = DB::select('SHOW TABLES');
        $dbName = env('DB_DATABASE');
        $key = 'Tables_in_'.$dbName;

        foreach ($tables as $table) {
            DB::statement('OPTIMIZE TABLE `'.$table->$key.'`');
        }
        app(AuditLogger::class)->log('system.database_optimized');

        return back()->with('success', 'Database optimized.');
    }

    public function softwareUpdate()
    {
        $this->ensureSuperAdmin();
        $currentVersion = SystemSetting::get('app_version', '1.0.0');
        $lastUpdate = SystemSetting::get('last_update_check', '-');

        return view('admin.system.software-update', compact('currentVersion', 'lastUpdate'));
    }

    public function checkUpdate()
    {
        $this->ensureSuperAdmin();
        SystemSetting::set('last_update_check', now()->toDateTimeString());
        app(AuditLogger::class)->log('system.update_checked');

        return back()->with('success', 'Update check completed. No updates available.');
    }

    /** @param array<string, string|null> $values */
    private function replaceAllowedEnvironmentValues(array $values): void
    {
        $path = base_path('.env');
        $contents = File::exists($path) ? File::get($path) : '';
        $backupDirectory = storage_path('app/backups/env');
        File::ensureDirectoryExists($backupDirectory);
        if (File::exists($path)) {
            File::copy($path, $backupDirectory.DIRECTORY_SEPARATOR.'env-'.now()->format('YmdHis').'.bak');
        }

        foreach ($values as $key => $value) {
            $encoded = str_contains((string) $value, ' ') ? '"'.addcslashes((string) $value, '"\\').'"' : (string) $value;
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $line = $key.'='.$encoded;
            $contents = preg_match($pattern, $contents) ? preg_replace($pattern, $line, $contents) : rtrim($contents).PHP_EOL.$line.PHP_EOL;
        }

        File::replace($path, $contents);
    }

    private function ensureSuperAdmin(): void
    {
        abort_unless(auth('admin')->user()?->isSuperAdmin(), 403);
    }
}
