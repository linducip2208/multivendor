<?php

declare(strict_types=1);

namespace App\Services\Backoffice;

use App\Models\Provider;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Operational health reporting.
 *
 * Every value shown to an operator is derived here rather than in Blade, and no
 * secret is ever returned: a configured credential is reported as present or
 * absent, never echoed. Live provider probes report reachability only.
 */
final class SystemHealthService
{
    public const REQUIRED_EXTENSIONS = ['pdo', 'mbstring', 'openssl', 'tokenizer', 'json', 'ctype', 'fileinfo', 'curl', 'gd', 'intl', 'bcmath'];
    public const MAIL_ENV_KEYS = ['MAIL_MAILER', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_ENCRYPTION', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME'];

    /**
     * @return array<string, mixed>
     */
    public function health(): array
    {
        return [
            'generated_at' => (string) now()->format('Y-m-d H:i:s'),
            'php' => $this->php(),
            'laravel' => $this->laravel(),
            'database' => $this->database(),
            'cache' => $this->cache(),
            'queue' => $this->queue(),
            'scheduler' => $this->scheduler(),
            'storage' => $this->storage(),
            'mail' => $this->mail(),
            'providers' => $this->providers(),
            'errors' => $this->recentErrors(),
            'migrations' => $this->migrations(),
            'maintenance' => [
                'enabled' => app()->isDownForMaintenance(),
                'file' => (string) storage_path('framework/down'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function php(): array
    {
        $missing = array_values(array_filter(self::REQUIRED_EXTENSIONS, fn (string $extension): bool => ! extension_loaded($extension)));

        return [
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'os' => PHP_OS_FAMILY,
            'memory_limit' => (string) ini_get('memory_limit'),
            'upload_max' => (string) ini_get('upload_max_filesize'),
            'post_max' => (string) ini_get('post_max_size'),
            'max_execution' => (string) ini_get('max_execution_time'),
            'extensions' => [
                'required' => self::REQUIRED_EXTENSIONS,
                'loaded' => array_values(array_filter(self::REQUIRED_EXTENSIONS, fn (string $extension): bool => extension_loaded($extension))),
                'missing' => $missing,
                'all_present' => $missing === [],
            ],
            'opcache' => extension_loaded('Zend OPcache') ? (string) ini_get('opcache.enable') : 'n/a',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function laravel(): array
    {
        return [
            'version' => (string) app()->version(),
            'environment' => (string) app()->environment(),
            'debug' => (bool) config('app.debug'),
            'locale' => (string) app()->getLocale(),
            'timezone' => (string) config('app.timezone'),
            'url' => (string) config('app.url'),
            'key_configured' => (string) config('app.key') !== '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function database(): array
    {
        $driver = (string) DB::connection()->getDriverName();
        $connection = (string) config('database.default');
        $version = null;
        $size = null;
        $latency = null;
        $tables = null;

        try {
            $start = microtime(true);
            DB::select('select 1');
            $latency = round((microtime(true) - $start) * 1000, 2);

            $version = (string) (DB::selectOne(match ($driver) {
                'mysql', 'mariadb' => 'select version() as v',
                'pgsql' => 'show server_version',
                'sqlite' => 'select sqlite_version() as v',
                'sqlsrv' => 'select @@VERSION as v',
                default => 'select 1 as v',
            })?->v ?? '');

            $database = (string) config('database.connections.'.$connection.'.database');

            if ($driver === 'sqlite') {
                $size = is_file($database) ? (int) round((int) filesize($database) / 1048576) : null;
            } else {
                $size = (int) round((float) (DB::selectOne('select sum(data_length + index_length) as s from information_schema.tables where table_schema = database()')?->s ?? 0) / 1048576);
                $tables = (int) (DB::selectOne('select count(*) as c from information_schema.tables where table_schema = database()')?->c ?? 0);
            }
        } catch (\Throwable $e) {
            Log::debug('Health database probe failed', ['error' => $e->getMessage()]);
        }

        return [
            'connection' => $connection,
            'driver' => $driver,
            'database' => (string) config('database.connections.'.$connection.'.database'),
            'host' => (string) config('database.connections.'.$connection.'.'.(str_contains($driver, 'sqlite') ? '' : 'host')),
            'version' => $version,
            'size_mb' => $size,
            'latency_ms' => $latency,
            'tables' => $tables,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cache(): array
    {
        $driver = (string) config('cache.default');
        $latency = null;
        $works = false;

        try {
            $start = microtime(true);
            Cache::put('health:probe', 'ok', 10);
            $works = Cache::get('health:probe') === 'ok';
            Cache::forget('health:probe');
            $latency = round((microtime(true) - $start) * 1000, 2);
        } catch (\Throwable $e) {
            $works = false;
        }

        return ['driver' => $driver, 'latency_ms' => $latency, 'writable' => $works];
    }

    /**
     * @return array<string, mixed>
     */
    private function queue(): array
    {
        $driver = (string) config('queue.default');
        $counts = ['pending' => 0, 'failed' => 0, 'reserved' => 0, 'delayed' => 0];
        $failedMaxTries = null;

        try {
            $connection = (string) config('queue.default');
            $queueConnection = (string) config("queue.connections.{$connection}.driver", $connection);
            $table = (string) config("queue.connections.{$connection}.table", 'jobs');

            if ($queueConnection === 'database' && \Illuminate\Support\Facades\Schema::hasTable($table)) {
                $counts['pending'] = (int) DB::table($table)->whereNull('reserved_at')->count();
                $counts['reserved'] = (int) DB::table($table)->whereNotNull('reserved_at')->count();
                $counts['delayed'] = (int) DB::table($table)->whereNotNull('available_at')->where('available_at', '>', now())->count();
            } elseif ($queueConnection === 'redis') {
                try {
                    $counts['pending'] = (int) (\Illuminate\Support\Facades\Redis::connection((string) config('queue.connections.redis.connection', 'default'))->llen('queues:default') ?? 0);
                } catch (\Throwable) {
                    $counts['pending'] = 0;
                }
            }

            $failedTable = (string) config('queue.failed.table', 'failed_jobs');
            if (\Illuminate\Support\Facades\Schema::hasTable($failedTable)) {
                $counts['failed'] = (int) DB::table($failedTable)->count();
                $failedMaxTries = (int) config('queue.connections.'.$connection.'.tries', 3);
            }
        } catch (\Throwable $e) {
            Log::debug('Health queue probe failed', ['error' => $e->getMessage()]);
        }

        return [
            'driver' => $driver,
            'connection' => (string) config('queue.default'),
            'counts' => $counts,
            'max_tries' => $failedMaxTries,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scheduler(): array
    {
        $lastRun = Cache::get('system:scheduler:last_run');
        $lastHeartbeat = Cache::get('system:scheduler:heartbeat');

        return [
            'last_run' => is_string($lastRun) ? $lastRun : null,
            'heartbeat' => is_string($lastHeartbeat) ? $lastHeartbeat : null,
            'due_minutes' => 60,
            'stale' => ! is_string($lastRun) || now()->diffInMinutes($lastRun) > 60,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function storage(): array
    {
        $disk = (string) config('filesystems.default');
        $root = storage_path();
        $logRoot = storage_path('logs');

        $logFiles = $this->logFiles();
        $logBytes = 0;
        foreach ($logFiles as $file) {
            $logBytes += (int) @filesize($file['path']);
        }

        $frameworks = collect(['framework/cache', 'framework/sessions', 'framework/views', 'app'])
            ->map(fn (string $path): array => [
                'path' => $path,
                'writable' => is_writable(storage_path($path)),
                'bytes' => (int) $this->directorySize(storage_path($path)),
            ])
            ->all();

        return [
            'disk' => $disk,
            'root' => $root,
            'writable' => is_writable($root),
            'bytes' => (int) $this->directorySize($root),
            'free_bytes' => @disk_free_space(base_path()) ?: null,
            'directories' => $frameworks,
            'logs' => [
                'files' => $logFiles,
                'count' => count($logFiles),
                'bytes' => $logBytes,
                'path' => $logRoot,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mail(): array
    {
        $mailer = (string) config('mail.default');
        $keys = [];

        foreach (self::MAIL_ENV_KEYS as $key) {
            $keys[] = [
                'key' => $key,
                'present' => (string) env($key, '') !== '',
            ];
        }

        return [
            'mailer' => $mailer,
            'from' => (string) config('mail.from.address'),
            'from_name' => (string) config('mail.from.name'),
            'host' => (string) config('mail.mailers.smtp.host'),
            'port' => (string) config('mail.mailers.smtp.port'),
            'encryption' => (string) config('mail.mailers.smtp.encryption'),
            'keys' => $keys,
            'credentials_present' => (string) env('MAIL_PASSWORD', '') !== '',
        ];
    }

    /**
     * Live reachability probe for each configured provider. The credential is
     * used for the request but never returned.
     *
     * @return list<array<string, mixed>>
     */
    private function providers(): array
    {
        $out = [];

        try {
            $providers = Provider::query()->orderBy('type')->orderBy('name')->get();
        } catch (\Throwable) {
            return [];
        }

        foreach ($providers as $provider) {
            $out[] = [
                'id' => (int) $provider->id,
                'name' => (string) $provider->name,
                'type' => (string) $provider->type,
                'api_format' => (string) $provider->api_format,
                'is_active' => (bool) $provider->is_active,
                'has_credentials' => $provider->getApiKeyAttribute() !== null,
                'base_url_host' => $this->hostOf((string) $provider->base_url),
                'health' => $provider->is_active ? $this->probe($provider) : ['status' => 'inactive', 'detail' => 'Provider nonaktif.'],
            ];
        }

        return $out;
    }

    /**
     * @return array{status: string, detail: string, latency_ms: float|null, code: int|null}
     */
    private function probe(Provider $provider): array
    {
        $base = rtrim((string) $provider->base_url, '/');

        if ($base === '') {
            return ['status' => 'unknown', 'detail' => 'Base URL belum diisi.', 'latency_ms' => null, 'code' => null];
        }

        $path = match ($provider->type) {
            'ai' => '/models',
            default => '/',
        };

        try {
            $start = microtime(true);
            $request = Http::acceptJson()->timeout(5)->connectTimeout(2);

            $key = $provider->getApiKeyAttribute();
            if ($key !== null && $key !== '') {
                $request = $request->withHeaders(['Authorization' => 'Bearer '.$key]);
            }

            $response = $request->get($base.$path);
            $latency = round((microtime(true) - $start) * 1000, 2);
            $code = $response->status();

            return [
                'status' => $response->successful() ? 'healthy' : ($code >= 500 ? 'down' : 'degraded'),
                'detail' => 'HTTP '.$code.' pada '.$this->hostOf($base).$path.' dalam '.$latency.' ms.',
                'latency_ms' => $latency,
                'code' => $code,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'unreachable',
                'detail' => 'Tidak dapat menghubungi '.$this->hostOf($base).'.',
                'latency_ms' => null,
                'code' => null,
            ];
        }
    }

    /**
     * @return list<array{level: string, message: string, at: string, file: string}>
     */
    private function recentErrors(int $limit = 12): array
    {
        $entries = [];

        foreach ($this->logFiles() as $file) {
            $lines = $this->tail((string) $file['path'], 400);

            foreach ($lines as $line) {
                if (! preg_match('/^\[\d{4}-\d{2}-\d{2}[^\]]*\]\s+(\w+)\.(\w+):\s*(.*)$/', $line, $matches)) {
                    continue;
                }

                $entries[] = [
                    'level' => strtoupper($matches[1]),
                    'message' => Str::limit($matches[3], 220),
                    'at' => trim($matches[0]),
                    'file' => (string) $file['name'],
                ];
            }
        }

        usort($entries, fn (array $a, array $b): int => strcmp((string) $b['at'], (string) $a['at']));

        return array_slice($entries, 0, max(1, $limit));
    }

    /**
     * @return array<string, mixed>
     */
    private function migrations(): array
    {
        $pending = [];
        $ran = 0;

        try {
            $ran = (int) DB::table('migrations')->count();
            $files = glob(database_path('migrations/*.php')) ?: [];

            $applied = DB::table('migrations')->pluck('migration')->flip();

            foreach ($files as $file) {
                $name = basename((string) $file, '.php');
                if (! $applied->has($name)) {
                    $pending[] = $name;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('Health migration probe failed', ['error' => $e->getMessage()]);
        }

        return ['ran' => $ran, 'pending' => $pending, 'pending_count' => count($pending)];
    }

    /**
     * Only `*.log` files directly under storage/logs are readable or removable.
     *
     * @return list<array{name: string, path: string, bytes: int, modified: string}>
     */
    public function logFiles(): array
    {
        $directory = storage_path('logs');
        $files = glob($directory.'/*.log') ?: [];

        $out = [];
        foreach ($files as $file) {
            $out[] = [
                'name' => basename((string) $file),
                'path' => (string) $file,
                'bytes' => (int) @filesize((string) $file),
                'modified' => (string) date('Y-m-d H:i:s', (int) @filemtime((string) $file)),
            ];
        }

        usort($out, fn (array $a, array $b): int => $b['bytes'] <=> $a['bytes']);

        return $out;
    }

    /**
     * Read the tail of one log file. The name is resolved against the log
     * directory and rejected if it escapes it.
     *
     * @return array<string, mixed>
     */
    public function readLog(string $name, int $lines = 300): array
    {
        $path = $this->safeLogPath($name);

        if ($path === null) {
            abort(404, 'Berkas log tidak ditemukan.');
        }

        return [
            'name' => basename($path),
            'bytes' => (int) @filesize($path),
            'modified' => (string) date('Y-m-d H:i:s', (int) @filemtime($path)),
            'lines' => $this->tail($path, max(10, min(2000, $lines))),
        ];
    }

    /**
     * Delete one log file, refusing anything outside storage/logs.
     */
    public function clearLog(string $name): bool
    {
        $path = $this->safeLogPath($name);

        if ($path === null) {
            return false;
        }

        return @unlink($path);
    }

    public function clearAllLogs(): int
    {
        $count = 0;

        foreach ($this->logFiles() as $file) {
            if ($this->clearLog((string) $file['name'])) {
                $count++;
            }
        }

        return $count;
    }

    private function safeLogPath(string $name): ?string
    {
        $name = basename($name);

        if (! str_ends_with(strtolower($name), '.log')) {
            return null;
        }

        $directory = realpath(storage_path('logs'));
        $candidate = realpath($directory.DIRECTORY_SEPARATOR.$name);

        if ($directory === false || $candidate === false) {
            return null;
        }

        if (! str_starts_with($candidate, $directory.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $candidate;
    }

    /**
     * @return list<string>
     */
    private function tail(string $path, int $lines): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        $buffer = [];
        fseek($handle, max(0, (int) filesize($path) - 512000));

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                continue;
            }
            $buffer[] = $line;

            if (count($buffer) > $lines) {
                array_shift($buffer);
            }
        }

        fclose($handle);

        return array_values($buffer);
    }

    private function directorySize(string $path): int
    {
        if (! is_dir($path)) {
            return 0;
        }

        $bytes = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $bytes += (int) $file->getSize();
            }
        }

        return $bytes;
    }

    private function hostOf(string $url): string
    {
        if ($url === '') {
            return '-';
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : '-';
    }

    /**
     * @return array<string, mixed>
     */
    public function queueHealth(): array
    {
        $health = $this->health();
        $failedJobs = [];

        try {
            $table = (string) config('queue.failed.table', 'failed_jobs');
            if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
                $failedJobs = DB::table($table)->orderByDesc('id')->limit(10)->get()
                    ->map(fn ($row): array => [
                        'id' => (string) $row->id,
                        'connection' => (string) $row->connection,
                        'queue' => (string) $row->queue,
                        'failed_at' => (string) $row->failed_at,
                        'error' => Str::limit((string) $row->exception, 200),
                    ])->all();
            }
        } catch (\Throwable) {
            $failedJobs = [];
        }

        return [
            'queue' => $health['queue'],
            'scheduler' => $health['scheduler'],
            'failed_jobs' => $failedJobs,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function clearCacheReport(): array
    {
        $cleared = [];

        try {
            Artisan::call('cache:clear');
            $cleared[] = 'cache';
        } catch (\Throwable $e) {
            $cleared[] = 'cache: gagal';
        }

        try {
            Artisan::call('view:clear');
            $cleared[] = 'view';
        } catch (\Throwable) {
            $cleared[] = 'view: gagal';
        }

        try {
            Artisan::call('config:clear');
            $cleared[] = 'config';
        } catch (\Throwable) {
            $cleared[] = 'config: gagal';
        }

        try {
            Artisan::call('route:clear');
            $cleared[] = 'route';
        } catch (\Throwable) {
            $cleared[] = 'route: gagal';
        }

        return ['cleared' => $cleared, 'at' => (string) now()->format('Y-m-d H:i:s')];
    }

    /**
     * @return array<string, mixed>
     */
    public function storageWriteProbe(): bool
    {
        try {
            $disk = Storage::disk('local');
            $disk->put('health/probe.txt', (string) now());
            $ok = $disk->exists('health/probe.txt');
            $disk->delete('health/probe.txt');

            return $ok;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsPresence(): array
    {
        $keys = [
            'app_name', 'maintenance_mode', 'maintenance_message', 'maintenance_allowed_ips',
            'order_prefix', 'seo', 'pseo_quality_threshold', 'pseo_min_products',
        ];

        $out = [];
        foreach ($keys as $key) {
            $out[] = ['key' => $key, 'present' => SystemSetting::query()->where('key', $key)->exists()];
        }

        return $out;
    }

    /**
     * Alert kesehatan operasional: daftar temuan + level.
     * Murni dari health() yang sudah ada + status backup terjadwal.
     *
     * @return list<array{key: string, level: string, label: string, detail: string}>
     */
    public function alerts(): array
    {
        $report = $this->health();
        $alerts = [];
        $missing = $report['php']['extensions']['missing'] ?? [];
        if ($missing !== []) {
            $alerts[] = ['key' => 'php_ext', 'level' => 'danger', 'label' => 'Ekstensi PHP hilang', 'detail' => 'Ekstensi belum terpasang: '.implode(', ', $missing).'.'];
        }
        if (($report['queue']['counts']['failed'] ?? 0) > 0) {
            $alerts[] = ['key' => 'queue_failed', 'level' => 'warning', 'label' => 'Job gagal menumpuk', 'detail' => $report['queue']['counts']['failed'].' job gagal. Periksa halaman Queue.'];
        }
        if (($report['scheduler']['stale'] ?? false) === true) {
            $alerts[] = ['key' => 'scheduler', 'level' => 'warning', 'label' => 'Scheduler tidak berjalan', 'detail' => 'Tidak ada heartbeat scheduler dalam 60 menit terakhir.'];
        }
        if (($report['migrations']['pending_count'] ?? 0) > 0) {
            $alerts[] = ['key' => 'migrations', 'level' => 'warning', 'label' => 'Migrasi tertunda', 'detail' => $report['migrations']['pending_count'].' migrasi belum dijalankan.'];
        }
        if (($report['storage']['writable'] ?? true) === false) {
            $alerts[] = ['key' => 'storage', 'level' => 'danger', 'label' => 'Storage tidak dapat ditulis', 'detail' => 'Direktori storage tidak writable.'];
        }
        $backup = $this->backupStatus();
        if ($backup['stale'] === true) {
            $alerts[] = ['key' => 'backup', 'level' => 'warning', 'label' => 'Backup kedaluwarsa', 'detail' => 'Backup terakhir: '.($backup['latest'] ?? 'belum pernah').'. Jadwal harian 03:00.'];
        }
        if ($alerts === []) {
            $alerts[] = ['key' => 'ok', 'level' => 'success', 'label' => 'Semua sistem normal', 'detail' => 'Tidak ada temuan pada pemeriksaan terakhir.'];
        }

        return $alerts;
    }

    /**
     * Status backup terjadwal (command db:backup harian 03:00).
     *
     * @return array{latest: string|null, count: int, stale: bool, schedule: string}
     */
    public function backupStatus(): array
    {
        $files = glob(storage_path('app/backups/*')) ?: [];
        rsort($files);
        $latest = null;
        if ($files !== []) {
            $latest = date('Y-m-d H:i:s', (int) @filemtime($files[0]));
        }
        $stale = $latest === null || now()->diffInHours($latest) > 30;

        return ['latest' => $latest, 'count' => count($files), 'stale' => $stale, 'schedule' => 'Harian 03:00 (db:backup)'];
    }

    /**
     * Kirim notifikasi operasional ke admin (defensif: tabel notifications bila ada).
     */
    public function notifyAdmins(string $title, string $body): int
    {
        $sent = 0;
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('notifications')) {
                return 0;
            }
            $admins = \App\Models\User::query()->where('role', 'admin')->pluck('id');
            foreach ($admins as $adminId) {
                DB::table('notifications')->insert([
                    'user_id' => $adminId,
                    'title' => mb_substr($title, 0, 160),
                    'body' => mb_substr($body, 0, 1000),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $sent++;
            }
        } catch (\Throwable) {
            return $sent;
        }

        return $sent;
    }

    /**
     * Administrative audit trail.
     *
     * @return array<string, mixed>
     */
    public function auditTrail(int $page = 1, int $perPage = 25, string $action = '', string $entity = ''): array
    {
        $query = \App\Models\AuditLog::query()->with('actor:id,name,role');

        if ($action !== '') {
            $query->where('action', 'like', '%'.$action.'%');
        }

        if ($entity !== '') {
            $query->where('entity_type', 'like', '%'.$entity.'%');
        }

        $page = max(1, $page);
        $perPage = max(5, min(100, $perPage));
        $total = (int) (clone $query)->count();

        $rows = $query->orderByDesc('id')->forPage($page, $perPage)->get()
            ->map(function (\App\Models\AuditLog $log): array {
                $before = is_array($log->old_values) ? $log->old_values : [];
                $after = is_array($log->new_values) ? $log->new_values : [];
                $keys = array_unique(array_merge(array_keys($before), array_keys($after)));

                $changed = [];
                foreach ($keys as $key) {
                    $from = $before[$key] ?? null;
                    $to = $after[$key] ?? null;

                    if ($from !== $to) {
                        $changed[] = $key.': '.self::scalar($from).' → '.self::scalar($to);
                    }
                }

                return [
                    'id' => (int) $log->id,
                    'actor_name' => (string) ($log->actor?->name ?? 'Sistem'),
                    'actor_role' => (string) ($log->actor?->role ?? 'system'),
                    'action' => (string) $log->action,
                    'entity_type' => (string) ($log->entity_type ?? ''),
                    'entity_id' => (string) ($log->entity_id ?? ''),
                    'changes' => $changed === [] ? '-' : \Illuminate\Support\Str::limit(implode(' · ', $changed), 140),
                    'ip_address' => (string) ($log->ip_address ?? ''),
                    'at' => (string) ($log->created_at?->format('Y-m-d H:i:s') ?? ''),
                ];
            })
            ->all();

        return [
            'rows' => $rows,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    private static function scalar(mixed $value): string
    {
        if ($value === null) {
            return 'kosong';
        }

        if (is_bool($value)) {
            return $value ? 'ya' : 'tidak';
        }

        if (is_array($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) \Illuminate\Support\Str::limit((string) $value, 40);
    }
}
