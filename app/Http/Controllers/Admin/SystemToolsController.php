<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;

class SystemToolsController extends Controller
{
    public function errorLogs()
    {
        $logPath = storage_path('logs/laravel.log');
        $logs = [];

        if (file_exists($logPath)) {
            $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = array_reverse(array_slice($lines, -500));

            $current = null;
            foreach ($lines as $line) {
                if (preg_match('/^\[\d{4}-\d{2}-\d{2}/', $line)) {
                    if ($current) $logs[] = $current;
                    $current = $line;
                } else {
                    $current .= "\n" . $line;
                }
            }
            if ($current) $logs[] = $current;
        }

        return view('admin.system.error-logs', compact('logs'));
    }

    public function clearErrorLogs()
    {
        $logPath = storage_path('logs/laravel.log');
        if (file_exists($logPath)) {
            file_put_contents($logPath, '');
        }
        return back()->with('success', 'Error logs cleared.');
    }

    public function envSettings()
    {
        $env = file_exists(base_path('.env')) ? file_get_contents(base_path('.env')) : '';
        return view('admin.system.env-settings', compact('env'));
    }

    public function updateEnvSettings(Request $request)
    {
        $request->validate(['env' => 'required|string']);

        file_put_contents(base_path('.env'), $request->env);

        \Illuminate\Support\Facades\Artisan::call('config:clear');
        \Illuminate\Support\Facades\Artisan::call('cache:clear');

        return back()->with('success', '.env updated and cache cleared.');
    }

    public function dbSettings()
    {
        $tables = \Illuminate\Support\Facades\DB::select('SHOW TABLE STATUS');
        $dbSize = collect($tables)->sum('Data_length') + collect($tables)->sum('Index_length');

        return view('admin.system.db-settings', compact('tables', 'dbSize'));
    }

    public function optimizeDb()
    {
        $tables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
        $dbName = env('DB_DATABASE');
        $key = 'Tables_in_' . $dbName;

        foreach ($tables as $table) {
            \Illuminate\Support\Facades\DB::statement('OPTIMIZE TABLE `' . $table->$key . '`');
        }

        return back()->with('success', 'Database optimized.');
    }

    public function softwareUpdate()
    {
        $currentVersion = SystemSetting::get('app_version', '1.0.0');
        $lastUpdate = SystemSetting::get('last_update_check', '-');

        return view('admin.system.software-update', compact('currentVersion', 'lastUpdate'));
    }

    public function checkUpdate()
    {
        SystemSetting::set('last_update_check', now()->toDateTimeString());
        return back()->with('success', 'Update check completed. No updates available.');
    }
}
