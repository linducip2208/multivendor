@props(['items' => null])

@php
    $fallback = [
        ['icon' => 'shield-check', 'title' => 'Pembayaran Aman', 'text' => 'Transaksi diproses lewat payment gateway resmi dan terenkripsi end-to-end.'],
        ['icon' => 'store', 'title' => 'Toko Terverifikasi', 'text' => 'Setiap penjual melewati proses verifikasi sebelum dapat berjualan.'],
        ['icon' => 'refresh', 'title' => 'Retur Mudah', 'text' => 'Ajukan retur langsung dari halaman pesanan Anda.'],
        ['icon' => 'headset', 'title' => 'Bantuan Responsif', 'text' => 'Tim dukungan dan penjual menjawab pertanyaan Anda lewat tiket.'],
    ];

    $settings = [
        \App\Models\SystemSetting::get('trust_1_icon', $fallback[0]['icon']),
        \App\Models\SystemSetting::get('trust_1_title', $fallback[0]['title']),
        \App\Models\SystemSetting::get('trust_1_text', $fallback[0]['text']),
        \App\Models\SystemSetting::get('trust_2_icon', $fallback[1]['icon']),
        \App\Models\SystemSetting::get('trust_2_title', $fallback[1]['title']),
        \App\Models\SystemSetting::get('trust_2_text', $fallback[1]['text']),
        \App\Models\SystemSetting::get('trust_3_icon', $fallback[2]['icon']),
        \App\Models\SystemSetting::get('trust_3_title', $fallback[2]['title']),
        \App\Models\SystemSetting::get('trust_3_text', $fallback[2]['text']),
        \App\Models\SystemSetting::get('trust_4_icon', $fallback[3]['icon']),
        \App\Models\SystemSetting::get('trust_4_title', $fallback[3]['title']),
        \App\Models\SystemSetting::get('trust_4_text', $fallback[3]['text']),
    ];

    $list = collect($items ?: [])->filter(fn ($entry) => is_array($entry) && ($entry['title'] ?? ''))->values()->all();

    if ($list === []) {
        $list = [
            ['icon' => $settings[0], 'title' => $settings[1], 'text' => $settings[2]],
            ['icon' => $settings[3], 'title' => $settings[4], 'text' => $settings[5]],
            ['icon' => $settings[6], 'title' => $settings[7], 'text' => $settings[8]],
            ['icon' => $settings[9], 'title' => $settings[10], 'text' => $settings[11]],
        ];
    }

    $list = array_slice($list, 0, 4);
@endphp

@if ($list !== [])
    <div {{ $attributes->merge(['class' => 'sf-footer__trust']) }}>
        @foreach ($list as $entry)
            <div class="sf-trust">
                <span class="sf-trust__icon"><x-storefront.icon :name="$entry['icon'] ?? 'shield-check'" :size="20" /></span>
                <span style="min-width:0">
                    <span class="sf-trust__title">{{ $entry['title'] }}</span>
                    <span class="sf-trust__text">{{ $entry['text'] ?? '' }}</span>
                </span>
            </div>
        @endforeach
    </div>
@endif
