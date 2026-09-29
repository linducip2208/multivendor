<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Super-admin-only system tooling.
 *
 * The `.env` writer is the most dangerous action in the backoffice, so it is
 * guarded three ways: the real super-admin flag is checked, the resolved path
 * must still be the project root after symlink resolution, and only an explicit
 * allow-list of keys can be written at all.
 */
class SystemToolsController extends Controller
{
    /** @var list<string> */
    private const EDITABLE_ENV_KEYS = [
        'APP_NAME', 'APP_URL', 'APP_DEBUG', 'LOG_LEVEL', 'QUEUE_CONNECTION', 'CACHE_STORE', 'SESSION_DRIVER', 'FILESYSTEM_DISK',
    ];

    /** @var list<string> */
    private const SECRET_KEYS = [
        'APP_KEY', 'DB_PASSWORD', 'MAIL_PASSWORD', 'REDIS_PASSWORD', 'AWS_SECRET_ACCESS_KEY',
        'JWT_SECRET', 'MIDTRANS_SERVER_KEY', 'XENDIT_SECRET_KEY', 'PAYGMENTS_SECRET',
    ];

    public function errorLogs(): View
    {
        $this->ensureSuperAdmin();

        return view('admin.system.error-logs', [
            'logs' => $this->readTail(storage_path('logs/laravel.log')),
        ]);
    }

    public function clearErrorLogs(): RedirectResponse
    {
        $this->ensureSuperAdmin();

        $logPath = storage_path('logs/laravel.log');
        if (File::exists($logPath)) {
            File::put($logPath, '');
        }

        app(AuditLogger::class)->log('system.error_logs_cleared', null, [], [], auth('admin')->id());

        return back()->with('success', 'Error logs dihapus.');
    }

    public function envSettings(): View
    {
        $this->ensureSuperAdmin();

        $settings = [];

        foreach (self::EDITABLE_ENV_KEYS as $key) {
            $settings[] = [
                'key' => $key,
                'value' => $this->maskIfSecret($key, (string) env($key, '')),
                'masked' => in_array($key, self::SECRET_KEYS, true),
                'configured' => (string) env($key, '') !== '',
            ];
        }

        return view('admin.system.env-settings', [
            'settings' => $settings,
            'editable_keys' => self::EDITABLE_ENV_KEYS,
            'env_path' => (string) $this->envPath(),
            'env_exists' => File::exists($this->envPath()),
        ]);
    }

    public function updateEnvSettings(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin();

        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable', 'string', 'max:2048'],
        ]);

        $settings = array_intersect_key($validated['settings'], array_flip(self::EDITABLE_ENV_KEYS));

        if (array_diff(array_keys(self::EDITABLE_ENV_KEYS), array_keys($settings)) !== []) {
            return back()->with('error', 'Semua kunci yang diizinkan harus dikirim.')->withInput();
        }

        $unknown = array_diff(array_keys($validated['settings']), self::EDITABLE_ENV_KEYS);
        if ($unknown !== []) {
            return back()->with('error', 'Kunci di luar daftar izin ditolak: '.implode(', ', $unknown).'.')->withInput();
        }

        foreach ($settings as $key => $value) {
            $value = (string) $value;

            if ($value !== '' && preg_match('/[\r\n]/', $value) === 1) {
                return back()->with('error', 'Nilai untuk '.$key.' tidak boleh berisi baris baru.')->withInput();
            }

            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) !== 1) {
                return back()->with('error', 'Kunci environment tidak valid: '.$key)->withInput();
            }
        }

        $path = $this->envPath();
        $this->writeEnv($path, $settings);

        Artisan::call('config:clear');
        Artisan::call('cache:clear');

        app(AuditLogger::class)->log(
            'system.environment_updated',
            null,
            [],
            ['keys' => array_keys($settings)],
            auth('admin')->id(),
        );

        return back()->with('success', 'Konfigurasi yang diizinkan diperbarui dan cache dibersihkan.');
    }

    public function dbSettings(): View
    {
        $this->ensureSuperAdmin();

        $tables = [];
        $dbSize = 0;

        if (DB::connection()->getDriverName() === 'sqlite') {
            $path = (string) config('database.connections.'.config('database.default').'.database');
            $bytes = is_file($path) ? (int) filesize($path) : 0;

            $tables = [['Name' => $path, 'Rows' => 0, 'Data_length' => $bytes, 'Index_length' => 0, 'total' => $bytes]];
            $dbSize = round($bytes / 1048576, 2);
        } else {
            $rows = DB::select('SHOW TABLE STATUS');
            foreach ($rows as $row) {
                $data = (float) $row->Data_length;
                $index = (float) $row->Index_length;
                $dbSize += $data + $index;

                $tables[] = [
                    'Name' => (string) $row->Name,
                    'Rows' => (int) $row->Rows,
                    'Data_length' => $data,
                    'Index_length' => $index,
                    'total' => $data + $index,
                ];
            }
        }

        usort($tables, fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return view('admin.system.db-settings', [
            'tables' => $tables,
            'dbSize' => round($dbSize / 1048576, 2),
            'driver' => (string) DB::connection()->getDriverName(),
        ]);
    }

    public function optimizeDb(): RedirectResponse
    {
        $this->ensureSuperAdmin();

        $driver = (string) DB::connection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return back()->with('error', 'Optimasi tabel hanya tersedia untuk MySQL/MariaDB.');
        }

        $database = (string) config('database.connections.'.config('database.default').'.database');
        $names = array_column(DB::select('SHOW TABLES'), 'Tables_in_'.$database);
        $optimised = 0;

        foreach ($names as $table) {
            if (! is_string($table) || preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
                continue;
            }

            DB::statement('OPTIMIZE TABLE `'.$database.'`.`'.$table.'`');
            $optimised++;
        }

        app(AuditLogger::class)->log('system.database_optimized', null, [], ['tables' => $optimised], auth('admin')->id());

        return back()->with('success', $optimised.' tabel dioptimasi.');
    }

    public function softwareUpdate(): View
    {
        $this->ensureSuperAdmin();

        return view('admin.system.software-update', [
            'currentVersion' => (string) SystemSetting::get('app_version', '1.0.0'),
            'lastUpdate' => (string) SystemSetting::get('last_update_check', '-'),
            'laravel' => (string) app()->version(),
            'php' => PHP_VERSION,
        ]);
    }

    public function checkUpdate(): RedirectResponse
    {
        $this->ensureSuperAdmin();

        SystemSetting::set('last_update_check', (string) now()->format('Y-m-d H:i:s'));

        app(AuditLogger::class)->log('system.update_checked', null, [], [], auth('admin')->id());

        return back()->with('success', 'Pemeriksaan versi selesai. Versi terpasang: Laravel '.app()->version().', PHP '.PHP_VERSION.'.');
    }

    /**
     * @param  array<string, string>  $values
     */
    private function writeEnv(string $path, array $values): void
    {
        $contents = File::exists($path) ? (string) File::get($path) : '';

        $backupDirectory = storage_path('app/backups/env');
        File::ensureDirectoryExists($backupDirectory);

        if (File::exists($path)) {
            File::copy($path, $backupDirectory.DIRECTORY_SEPARATOR.'env-'.now()->format('YmdHis').'-'.Str::random(4).'.bak');
        }

        foreach ($values as $key => $value) {
            $encoded = str_contains($value, ' ') || str_contains($value, '#')
                ? '"'.addcslashes($value, '"\\').'"'
                : $value;

            $line = $key.'='.$encoded;
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            $contents = preg_match($pattern, $contents) === 1
                ? (string) preg_replace($pattern, str_replace('$', '\$', $line), $contents)
                : rtrim($contents).PHP_EOL.$line.PHP_EOL;
        }

        File::put($path, $contents);
    }

    private function envPath(): string
    {
        $root = realpath(base_path());
        $path = base_path('.env');

        if ($root === false) {
            return $path;
        }

        $parent = realpath(dirname($path));

        if ($parent !== false && $parent !== $root) {
            abort(403, 'Lokasi berkas .env tidak berada di dalam root proyek.');
        }

        return $path;
    }

    private function maskIfSecret(string $key, string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (in_array($key, self::SECRET_KEYS, true)) {
            return str_repeat('•', min(12, strlen($value))).substr($value, -3);
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function readTail(string $path, int $lines = 500): array
    {
        if (! File::exists($path)) {
            return [];
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        $buffer = [];
        fseek($handle, max(0, (int) filesize($path) - 262144));

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                continue;
            }
            $buffer[] = $line;
        }

        fclose($handle);

        return array_slice(array_reverse($buffer), 0, $lines);
    }

    private function ensureSuperAdmin(): void
    {
        $user = auth('admin')->user();

        abort_if($user === null, 403, 'Sesi admin tidak valid.');
        abort_unless($user->isSuperAdmin(), 403, 'Hanya super admin yang dapat mengakses halaman sistem.');
    }
}
