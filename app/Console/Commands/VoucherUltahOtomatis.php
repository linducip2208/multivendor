<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Marketing\RetentionService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Terbitkan voucher ulang tahun otomatis untuk kandidat hari ini.
 *
 * Memakai tanggal lahir bila kolom tersedia, fallback bulan registrasi.
 * Idempoten: aman dijalankan harian tanpa voucher ganda.
 *
 * Sengaja TIDAK didaftarkan ke schedule — integrator yang wiring
 * jadwalnya (mis. harian pagi) setelah meninjau kebutuhan bisnis.
 */
class VoucherUltahOtomatis extends Command
{
    protected $signature = 'marketing:voucher-ultah
        {--date= : Tanggal acuan Y-m-d (default hari ini)}';

    protected $description = 'Terbitkan voucher ulang tahun otomatis untuk pelanggan yang berulang tahun';

    public function handle(RetentionService $retensi): int
    {
        $raw = trim((string) $this->option('date'));
        try {
            $tanggal = $raw !== '' ? Carbon::createFromFormat('Y-m-d', $raw) : now();
        } catch (\Throwable) {
            $this->error('Format --date tidak valid, gunakan Y-m-d.');

            return self::FAILURE;
        }

        $hasil = $retensi->issueBirthdayVouchers($tanggal, null);

        $this->info("Voucher diterbitkan: {$hasil['issued']}, dilewati (sudah ada/gagal): {$hasil['skipped']}.");

        return self::SUCCESS;
    }
}
