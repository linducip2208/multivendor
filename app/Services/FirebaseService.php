<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    protected ?string $serverKey = null;

    public function __construct()
    {
        $this->serverKey = SystemSetting::get('firebase_server_key');
    }

    public function isConfigured(): bool
    {
        return !empty($this->serverKey);
    }

    public function sendToDevice(string $deviceToken, string $title, string $body, array $data = [], ?string $image = null): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $payload = [
            'to' => $deviceToken,
            'notification' => array_filter([
                'title' => $title,
                'body' => $body,
                'image' => $image,
                'sound' => 'default',
            ]),
            'data' => $data,
        ];

        return $this->send($payload);
    }

    public function sendToTopic(string $topic, string $title, string $body, array $data = [], ?string $image = null): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $payload = [
            'to' => '/topics/' . $topic,
            'notification' => array_filter([
                'title' => $title,
                'body' => $body,
                'image' => $image,
                'sound' => 'default',
            ]),
            'data' => $data,
        ];

        return $this->send($payload);
    }

    public function sendToMultiple(array $deviceTokens, string $title, string $body, array $data = [], ?string $image = null): bool
    {
        if (!$this->isConfigured() || empty($deviceTokens)) {
            return false;
        }

        $payload = [
            'registration_ids' => $deviceTokens,
            'notification' => array_filter([
                'title' => $title,
                'body' => $body,
                'image' => $image,
                'sound' => 'default',
            ]),
            'data' => $data,
        ];

        return $this->send($payload);
    }

    protected function send(array $payload): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $this->serverKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)
              ->post('https://fcm.googleapis.com/fcm/send', $payload);

            if ($response->successful()) {
                $result = $response->json();
                if (($result['success'] ?? 0) > 0) {
                    return true;
                }
                Log::warning('FCM send returned 0 successes', ['result' => $result]);
            } else {
                Log::error('FCM send failed', ['status' => $response->status(), 'body' => $response->body()]);
            }

            return false;
        } catch (\Throwable $e) {
            Log::error('FCM send exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function subscribeToTopic(string $deviceToken, string $topic): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $this->serverKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)
              ->post('https://iid.googleapis.com/iid/v1/' . $deviceToken . '/rel/topics/' . $topic);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('FCM subscribe topic failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
