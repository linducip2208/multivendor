<?php

namespace App\Services\Ai;

use App\Models\Provider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiCompatibleAdapter
{
    public function __construct(protected Provider $provider) {}

    public function chat(string $prompt, string $systemPrompt = null, string $model = null, array $options = []): array
    {
        $model = $model ?: ($this->provider->config['default_model'] ?? 'gpt-4.1-nano');

        $messages = [];
        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $body = array_merge([
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 2000,
        ], $options['extra'] ?? []);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->provider->getApiKeyAttribute(),
                'Content-Type' => 'application/json',
            ])->timeout(60)->post(rtrim($this->provider->base_url, '/') . '/chat/completions', $body);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'content' => $data['choices'][0]['message']['content'] ?? '',
                    'model' => $data['model'] ?? $model,
                    'tokens' => $data['usage'] ?? null,
                    'raw' => $data,
                ];
            }

            Log::error('AI chat error', ['status' => $response->status(), 'body' => $response->body()]);
            return ['success' => false, 'error' => $response->body()];
        } catch (\Exception $e) {
            Log::error('AI chat exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function fetchModels(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->provider->getApiKeyAttribute(),
            ])->timeout(30)->get(rtrim($this->provider->base_url, '/') . '/models');

            if ($response->successful()) {
                $data = $response->json();
                $models = collect($data['data'] ?? [])
                    ->pluck('id')
                    ->filter(fn ($id) => !str_contains($id, 'embed') && !str_contains($id, 'moderation') && !str_contains($id, 'dall-e') && !str_contains($id, 'whisper') && !str_contains($id, 'tts'))
                    ->values()
                    ->all();

                return ['success' => true, 'models' => $models];
            }

            return ['success' => false, 'error' => 'Failed to fetch models'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
