<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Regression: every static route('name') / to_route('name') reference in
 * app/, routes/ and resources/views/ must resolve to a registered route.
 *
 * Self-contained static analysis (no database, no HTTP). Boots the app only
 * to read the route registry via Route::getRoutes(), then scans source
 * files for route-name literals and asserts each one exists.
 *
 * Exclusions (false-positive guards, NOT bug fixes):
 * - Request::route('param') parameter lookups (->route( / ?->route() are
 *   parameter access, not URL generation) are never collected.
 * - Dynamic names containing $, {, }, :: or string concatenation are
 *   skipped: they cannot be resolved statically.
 */
class RouteReferenceTest extends TestCase
{
    public function test_all_static_route_references_exist(): void
    {
        $registered = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = $route->getName();
            if (is_string($name) && $name !== '') {
                $registered[$name] = true;
            }
        }

        $this->assertNotEmpty($registered, 'Route registry must not be empty.');

        $base = \dirname(__DIR__, 2);
        $dirs = [
            $base.'/app',
            $base.'/routes',
            $base.'/resources/views',
        ];

        // Negative lookbehind excludes ->route( (?->route(), ::route()
        // and safeRoute(/signedRoute( word-char prefixes.
        $patterns = [
            '/(?<![\w:>?-])route\(\s*[\'"]([^\'"]+)[\'"]/u',
            '/(?<![\w:>?-])to_route\(\s*[\'"]([^\'"]+)[\'"]/u',
        ];

        $missing = [];

        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $path = $file->getPathname();
                $isBlade = str_ends_with($path, '.blade.php');
                $isPhp = str_ends_with($path, '.php');
                if (! $isBlade && ! $isPhp) {
                    continue;
                }

                $lines = file($path, FILE_IGNORE_NEW_LINES);
                if ($lines === false) {
                    continue;
                }

                foreach ($lines as $no => $line) {
                    foreach ($patterns as $pattern) {
                        if (! preg_match_all($pattern, $line, $m)) {
                            continue;
                        }

                        foreach ($m[1] as $name) {
                            // Skip dynamic / non-literal references.
                            if (str_contains($name, '$')
                                || str_contains($name, '{')
                                || str_contains($name, '}')
                                || str_contains($name, '::')
                                || str_contains($name, ' ')
                                || str_contains($name, '.php')
                            ) {
                                continue;
                            }

                            if (! isset($registered[$name])) {
                                $rel = ltrim(str_replace($base, '', $path), '/\\');
                                $missing[] = "{$name}  ({$rel}:".($no + 1).')';
                            }
                        }
                    }
                }
            }
        }

        $missing = array_values(array_unique($missing));
        sort($missing);

        $this->assertSame(
            [],
            $missing,
            'Referenced-but-missing route names found ('.count($missing)."):\n - ".implode("\n - ", $missing)
        );
    }
}
