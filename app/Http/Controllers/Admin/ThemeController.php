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
        return view('admin.theme.index', compact('colors'));
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
}
