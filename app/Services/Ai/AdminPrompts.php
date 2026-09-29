<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\SystemSetting;

/**
 * Every prompt the backoffice sends to an AI provider.
 *
 * Prompts are stored as system settings so an operator can rewrite them without
 * a deploy, but the shipped defaults live here as the single source of truth.
 * Each system prompt carries the same guardrail: the model produces advisory
 * prose only and can never be treated as an instruction to move money.
 */
final class AdminPrompts
{
    public const SETTING_PREFIX = 'ai_prompt_';

    public const TASKS = [
        'sales_analysis' => [
            'label' => 'Analisis Penjualan',
            'description' => 'Menganalisis tren penjualan, jam ramai, dan produk terkuat.',
            'icon' => 'trending-up',
        ],
        'revenue_anomaly' => [
            'label' => 'Penjelasan Anomali',
            'description' => 'Mencari penyebab lonjakan atau penurunan pendapatan yang tidak wajar.',
            'icon' => 'alert-triangle',
        ],
        'product_performance' => [
            'label' => 'Performa Produk',
            'description' => 'Menilai kesehatan katalog: produk mati, level stok, dan harga.',
            'icon' => 'package',
        ],
        'vendor_performance' => [
            'label' => 'Performa Vendor',
            'description' => 'Membandingkan performa toko dan menandai seller yang perlu perhatian.',
            'icon' => 'store',
        ],
        'campaign_suggestions' => [
            'label' => 'Ide Kampanye',
            'description' => 'Mengusulkan kampanye berdasarkan kategori dan perilaku nyata.',
            'icon' => 'megaphone',
        ],
        'inventory_risk' => [
            'label' => 'Risiko Inventori',
            'description' => 'Memperkirakan produk yang akan habis dan kapan.',
            'icon' => 'boxes',
        ],
        'segment_suggestions' => [
            'label' => 'Saran Segmen',
            'description' => 'Mengusulkan segmen berdasarkan fakta transaksi.',
            'icon' => 'layers',
        ],
        'report_summary' => [
            'label' => 'Ringkasan Laporan',
            'description' => 'Meringkas laporan keuangan atau operasional menjadi catatan singkat.',
            'icon' => 'file-text',
        ],
    ];

    public const GUARDRAIL = <<<'TXT'
        Anda adalah asisten analitik untuk backoffice marketplace. Aturan yang tidak boleh dilanggar:
        1. Output Anda adalah teks consultatif. Anda tidak pernah memindahkan uang, mengubah status
           pesanan, atau menjalankan tindakan apa pun pada sistem ini.
        2. Jangan mengarang angka. Jika sebuah angka tidak ada di dalam data yang diberikan,
           tulis "data tidak tersedia".
        3. Jangan menyimpulkan kondisi kesehatan, kondisi keuangan, atau niat pengguna dari data
           transaksi. Anda hanya boleh menyebut fakta yang tercatat: jumlah pesanan, nilai, tanggal,
           dan kategori.
        4. Jawaban dalam Bahasa Indonesia, ringkas, format Markdown dengan heading dan bullet.
        TXT;

    public static function settingKey(string $task): string
    {
        return self::SETTING_PREFIX.$task.'_system';
    }

    public static function taskLabel(string $task): string
    {
        return self::TASKS[$task]['label'] ?? $task;
    }

    public static function defaultSystemPrompt(string $task): string
    {
        $specific = match ($task) {
            'sales_analysis' => 'Fokus pada tren penjualan harian, jam dengan transaksi terbanyak, dan produk dengan pertumbuhan tertinggi. Sertakan tiga temuan utama dan tiga rekomendasi yang dapat ditindaklanjuti.',
            'revenue_anomaly' => 'Fokus pada mengisolasi hari atau periode dengan deviasi pendapatan terbesar. Sebutkan kemungkinan penyebab berdasarkan data yang tersedia dan jelaskan langkah verifikasi yang diperlukan.',
            'product_performance' => 'Fokus pada produk yang tidak laku, penyesuaian harga yang tidak masuk akal, dan produk tanpa ulasan. Sertakan ringkasan level stok.',
            'vendor_performance' => 'Fokus pada seller dengan penjualan menurun, seller yang belum merespons cepat, dan seller yang konsisten tertinggal. Gunakan data agregat yang tersedia.',
            'campaign_suggestions' => 'Fokus pada kategori dan produk yang paling menarik dan paling sering dibeli. Sertakan saran anggaran, target, dan periode.',
            'inventory_risk' => 'Fokus pada produk yang akan habis dalam 30 hari, produk dengan perputaran sangat lambat, dan kelebihan stok. Sertakan saran restock berdasarkan kecepatan jual saat ini.',
            'segment_suggestions' => 'Fokus pada pola pembelian yang dapat diukur: frekuensi, nilai, kategori favorit, dan perilaku kupon. Jangan gunakan atribut pribadi.',
            'report_summary' => 'Fokus pada poin yang perlu ditindaklanjuti oleh operator, diurutkan dari paling mendesak.',
            default => 'Berikan analisis singkat dan konkret berdasarkan data yang diberikan.',
        };

        return trim(self::GUARDRAIL."\n\n".$specific);
    }

    public static function systemPrompt(string $task): string
    {
        $custom = null;

        try {
            $custom = SystemSetting::get(self::settingKey($task));
        } catch (\Throwable) {
            $custom = null;
        }

        $prompt = is_string($custom) && trim($custom) !== '' ? trim($custom) : self::defaultSystemPrompt($task);

        return str_contains($prompt, 'tidak pernah memindahkan uang')
            ? $prompt
            : trim($prompt."\n\n".self::GUARDRAIL);
    }

    /**
     * @return array<string, array{label: string, description: string, icon: string, system: string, default: string, setting: string, customised: bool}>
     */
    public static function catalogue(): array
    {
        $out = [];

        foreach (self::TASKS as $task => $definition) {
            $out[$task] = [
                'label' => (string) $definition['label'],
                'description' => (string) $definition['description'],
                'icon' => (string) $definition['icon'],
                'system' => self::systemPrompt($task),
                'default' => self::defaultSystemPrompt($task),
                'setting' => self::settingKey($task),
                'customised' => self::isCustomised($task),
            ];
        }

        return $out;
    }

    public static function isCustomised(string $task): bool
    {
        try {
            $custom = SystemSetting::get(self::settingKey($task));
        } catch (\Throwable) {
            return false;
        }

        return is_string($custom) && trim($custom) !== '' && trim($custom) !== trim(self::defaultSystemPrompt($task));
    }

    public static function reset(string $task): void
    {
        SystemSetting::set(self::settingKey($task), null);
    }
}
