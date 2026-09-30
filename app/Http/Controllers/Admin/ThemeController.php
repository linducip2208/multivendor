<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    public function index()
    {
        $colors = ['#4F46E5','#2563EB','#059669','#DC2626','#7C3AED','#EA580C','#0891B2','#DB2777','#65A30D','#9333EA'];
        $themes = app(\App\Services\Theme\ThemeManager::class);
        return view('admin.theme.index', [
            'colors' => $colors,
            'daftarTema' => $themes->available(),
            'temaAktif' => $themes->active(),
            'snapshots' => $themes->snapshots(),
        ]);
    }

    public function update(Request $request)
    {
        $settings = [
            'theme_primary_color',
            'theme_primary_dark',
            'theme_border_radius',
            'theme_font_family',
            'theme_sidebar_width',
            'theme_topbar_height',
            'theme_dark_mode_default',
            'theme_show_language_switcher',
            'theme_logo_text',
            'theme_favicon',
        ];

        foreach ($settings as $key) {
            if ($request->has($key)) {
                SystemSetting::set($key, $request->$key);
            }
        }

        \Illuminate\Support\Facades\Cache::forget('whitelabel_branding');
        \Illuminate\Support\Facades\Cache::forget('theme_settings');

        return back()->with('success', 'Theme settings saved.');
    }

    public function activate(Request $request)
    {
        $validated = $request->validate(['theme' => ['required', 'string', 'max:80']]);
        try {
            app(\App\Services\Theme\ThemeManager::class)->activate($validated['theme']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Tema "'.$validated['theme'].'" diaktifkan.');
    }

    public function duplicate(Request $request)
    {
        $validated = $request->validate([
            'from' => ['required', 'string', 'max:80'],
            'to' => ['required', 'string', 'max:80'],
        ]);
        try {
            $code = app(\App\Services\Theme\ThemeManager::class)->duplicate($validated['from'], $validated['to']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Tema diduplikasi sebagai "'.$code.'".');
    }

    public function rollback(Request $request)
    {
        $validated = $request->validate(['index' => ['required', 'integer', 'min:0', 'max:9']]);
        try {
            $theme = app(\App\Services\Theme\ThemeManager::class)->rollback((int) $validated['index'], auth('admin')->id());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Tema dikembalikan ke "'.$theme.'".');
    }

    public function schedule(Request $request)
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', 'max:80'],
            'at' => ['required', 'date', 'after:now'],
        ]);
        try {
            app(\App\Services\Theme\ThemeManager::class)->scheduleActivation($validated['theme'], $validated['at'], auth('admin')->id());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Aktivasi terjadwal disimpan.');
    }

    public function preview(Request $request)
    {
        $validated = $request->validate(['theme' => ['required', 'string', 'max:80']]);

        return redirect('/?theme_preview='.urlencode($validated['theme']));
    }
}
