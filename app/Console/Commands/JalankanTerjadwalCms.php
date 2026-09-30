<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Menjalankan hal terjadwal CMS: aktivasi tema + konten terjadwal.
 */
class JalankanTerjadwalCms extends Command
{
    protected $signature = 'cms:jalankan-terjadwal';

    protected $description = 'Aktivasi tema terjadwal dan terbitkan konten terjadwal yang waktunya tiba';

    public function handle(): int
    {
        $tema = app(\App\Services\Theme\ThemeManager::class)->runScheduledActivation();
        if ($tema !== null) {
            $this->info("Tema diaktifkan: {$tema}");
        }

        $konten = app(\App\Services\Cms\ContentWorkflowService::class)->dueScheduled();
        $this->info('Konten diterbitkan: '.count($konten));

        return self::SUCCESS;
    }
}
