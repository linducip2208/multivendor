<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function index()
    {
        $modules = [];
        $modulePath = base_path('Modules');

        if (is_dir($modulePath)) {
            foreach (glob($modulePath . '/*', GLOB_ONLYDIR) as $dir) {
                $name = basename($dir);
                $moduleJson = $dir . '/module.json';
                $info = file_exists($moduleJson) ? json_decode(file_get_contents($moduleJson), true) : [];
                $modules[] = [
                    'name' => $info['name'] ?? $name,
                    'alias' => $info['alias'] ?? $name,
                    'description' => $info['description'] ?? '',
                    'version' => $info['version'] ?? '1.0.0',
                    'active' => $info['active'] ?? false,
                    'path' => $dir,
                ];
            }
        }

        return view('admin.modules.index', compact('modules'));
    }

    public function toggle(Request $request)
    {
        $name = $request->input('module');
        if (!$name) return back()->with('error', 'Module name required.');

        $jsonPath = base_path("Modules/{$name}/module.json");
        if (!file_exists($jsonPath)) return back()->with('error', 'Module not found.');

        $info = json_decode(file_get_contents($jsonPath), true);
        $info['active'] = !($info['active'] ?? false);
        file_put_contents($jsonPath, json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        \Illuminate\Support\Facades\Artisan::call('config:clear');

        return back()->with('success', 'Module toggled.');
    }
}
