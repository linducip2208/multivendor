<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DatabaseBackup extends Command
{
    protected $signature = 'db:backup {--disk=local}';
    protected $description = 'Backup database to storage';

    public function handle(): int
    {
        $filename = 'backup-' . now()->format('Y-m-d-H-i-s') . '.sql';
        $path = storage_path('app/backups/' . $filename);

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $db = config('database.default');
        $config = config("database.connections.{$db}");

        $command = match ($db) {
            'mysql' => sprintf(
                'mysqldump -h%s -P%s -u%s -p%s %s > "%s"',
                escapeshellarg($config['host']),
                $config['port'] ?? '3306',
                escapeshellarg($config['username']),
                escapeshellarg($config['password']),
                escapeshellarg($config['database']),
                $path,
            ),
            'sqlite' => sprintf(
                'cp "%s" "%s"',
                database_path('database.sqlite'),
                $path,
            ),
            default => null,
        };

        if (!$command) {
            $this->error("Unsupported database driver: {$db}");
            return self::FAILURE;
        }

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            Log::error('Database backup failed', ['output' => $output]);
            $this->error('Backup failed.');
            return self::FAILURE;
        }

        $this->cleanupOldBackups();

        $this->info("Database backed up to {$path}");
        Log::info("Database backed up to {$path}");

        return self::SUCCESS;
    }

    protected function cleanupOldBackups(): void
    {
        $backups = glob(storage_path('app/backups/backup-*.sql'));
        rsort($backups);

        foreach (array_slice($backups, 7) as $old) {
            @unlink($old);
        }
    }
}
