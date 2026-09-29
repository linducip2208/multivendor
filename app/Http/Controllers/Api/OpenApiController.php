<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\Api\OpenApiSpec;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class OpenApiController
{
    public function __construct(private readonly OpenApiSpec $spec) {}

    public function json(): Response
    {
        return new Response(
            (string) json_encode($this->spec->build(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            200,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    public function yaml(): Response
    {
        return new Response($this->spec->yaml(), 200, [
            'Content-Type' => 'application/yaml; charset=utf-8',
        ]);
    }

    public function ui(): SymfonyResponse
    {
        $html = $this->view('docs/api');

        if ($html !== null) {
            return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
        }

        return response($this->fallbackHtml(), 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    private function view(string $name): ?string
    {
        $path = resource_path('views/'.$name.'.blade.php');

        if (! is_file($path)) {
            return null;
        }

        return (string) file_get_contents($path);
    }

    private function fallbackHtml(): string
    {
        $title = 'Multivendor Marketplace API';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
<link rel="stylesheet" href="/docs/swagger-ui.css">
<style>body{margin:0;background:#fafafa}#missing{font-family:system-ui,sans-serif;padding:2rem;max-width:48rem}</style>
</head>
<body>
<div id="swagger-ui"></div>
<script src="/docs/swagger-ui-bundle.js" crossorigin></script>
<script src="/docs/swagger-ui-standalone-preset.js" crossorigin></script>
<script>
window.addEventListener('load', function () {
  window.ui = SwaggerUIBundle({
    url: '/docs/api/openapi.yaml',
    dom_id: '#swagger-ui',
    deepLinking: true,
    presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
    plugins: [SwaggerUIBundle.plugins.DownloadUrl],
    layout: 'StandaloneLayout'
  });
});
</script>
</body>
</html>
HTML;
    }
}
