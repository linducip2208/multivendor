<?php

namespace Tests\Feature;

use App\Services\Backoffice\SystemHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Regression: queue() me-cast array konfigurasi ke string
 * ("Array to string conversion") sehingga probe antrean SELALU gagal
 * dan panel kesehatan tidak pernah menampilkan status antrean.
 */
class QueueHealthProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_probe_returns_driver_without_errors(): void
    {
        Log::spy();

        $health = app(SystemHealthService::class)->queueHealth();

        $this->assertSame(config('queue.default'), $health['queue']['driver']);
        $this->assertSame(
            ['pending' => 0, 'failed' => 0, 'reserved' => 0, 'delayed' => 0],
            $health['queue']['counts']
        );

        Log::shouldNotHaveReceived('debug');
    }
}
