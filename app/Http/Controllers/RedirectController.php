<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Redirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-managed redirect table.
 *
 * Registered as the very last web route so it only ever sees a path that no
 * real route claimed. Unknown paths 404 unless an administrator has mapped
 * them, which restores correct crawl behaviour after the previous PSEO
 * catch-all answered HTTP 200 for every unmatched URL.
 */
class RedirectController extends Controller
{
    private const CACHE_KEY = 'redirects:map';
    private const CACHE_TTL = 600;

    public function resolve(Request $request, string $path)
    {
        $normalised = '/'.ltrim(trim($path, '/'), '/');
        $rule = $this->map()[$normalised] ?? null;

        if ($rule === null) {
            abort(404);
        }

        $status = (int) ($rule->status_code ?: 301);
        $target = $rule->to_path ?: '/';

        // A redirect target that points back at the source is a loop.
        if ($target === $normalised) {
            abort(404);
        }

        try {
            Redirect::whereKey($rule->id)->increment('hit_count');
        } catch (\Throwable) {
        }

        return redirect()->to($target, $status);
    }

    /** @return array<string, Redirect> */
    private function map(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            try {
                return Redirect::query()
                    ->where('is_active', true)
                    ->get()
                    ->keyBy('from_path')
                    ->all();
            } catch (\Throwable) {
                return [];
            }
        });
    }
}
