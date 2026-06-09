<?php

namespace App\Services\Ai;

use App\Models\Provider;

class AiService
{
    protected array $adapters = [];

    public function getAdapter(Provider $provider): ?OpenAiCompatibleAdapter
    {
        if (isset($this->adapters[$provider->id])) {
            return $this->adapters[$provider->id];
        }

        if ($provider->type !== 'ai' || $provider->api_format !== 'openai-compatible') {
            return null;
        }

        $adapter = new OpenAiCompatibleAdapter($provider);
        $this->adapters[$provider->id] = $adapter;
        return $adapter;
    }

    public function chat(Provider $provider, string $prompt, string $systemPrompt = null, string $model = null, array $options = []): array
    {
        $adapter = $this->getAdapter($provider);
        if (!$adapter) {
            return ['success' => false, 'error' => 'Provider AI tidak didukung.'];
        }
        return $adapter->chat($prompt, $systemPrompt, $model, $options);
    }

    public function fetchModels(Provider $provider): array
    {
        $adapter = $this->getAdapter($provider);
        if (!$adapter) {
            return ['success' => false, 'error' => 'Provider tidak didukung.'];
        }
        return $adapter->fetchModels();
    }

    public function getActiveProviders(): array
    {
        return Provider::ofType('ai')->active()->orderBy('sort_order')->get()->all();
    }
}
