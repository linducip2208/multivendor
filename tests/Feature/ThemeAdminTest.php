<?php

namespace Tests\Feature;

use App\Services\Theme\ThemeManager;
use Tests\TestCase;

class ThemeAdminTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;
    public function test_manager_api_dasar(): void
    {
        $manager = app(ThemeManager::class);

        $available = $manager->available();
        $this->assertNotEmpty($available);
        $this->assertSame('default', $available[0]['code']);
        $this->assertSame('default', $manager->active());

        $this->assertSame('storefront.home', $manager->resolve('storefront.home'));
    }

    public function test_aktivasi_tema_tak_dikenal_ditolak(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(ThemeManager::class)->activate('tema-tidak-ada-xyz');
    }

    public function test_snapshot_dan_rollback(): void
    {
        $manager = app(ThemeManager::class);
        $manager->snapshot('uji-rollback');

        $snaps = $manager->snapshots();
        $this->assertNotEmpty($snaps);
        $this->assertSame('uji-rollback', $snaps[0]['label']);

        $this->assertSame('default', $manager->rollback(0));
    }

    public function test_jadwal_command_terdaftar(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events());
        $this->assertTrue(
            $events->contains(fn ($e) => str_contains((string) $e->command, 'cms:jalankan-terjadwal')),
            'Jadwal cms:jalankan-terjadwal harus terdaftar.'
        );
    }

    public function test_route_tema_terdaftar(): void
    {
        foreach (['admin.theme.activate', 'admin.theme.duplicate', 'admin.theme.rollback', 'admin.theme.schedule', 'admin.theme.preview'] as $name) {
            $this->assertTrue(\Illuminate\Support\Facades\Route::has($name), "Route hilang: {$name}");
        }
    }
}
