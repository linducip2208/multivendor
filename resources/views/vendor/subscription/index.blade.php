@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Langganan')
@section('subtitle', $plan ? $plan->name : 'Belum ada paket aktif')

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Langganan'],
])

@section('content')
    @if ($status['key'] === 'grace')
        <x-admin.alert type="warning" title="Masa tenggang">
            Periode paket Anda telah berakhir. Toko masih dapat beroperasi sampai
            <strong>{{ $subscription?->grace_ends_at?->format('d M Y') }}</strong>. Perpanjang sekarang agar akses tidak terganggu.
        </x-admin.alert>
    @elseif ($status['key'] === 'expired')
        <x-admin.alert type="danger" title="Langganan kedaluwarsa">
            Paket Anda sudah berakhir. Kuota produk, tim, penyimpanan, dan transaksi terkunci
            sampai Anda memilih paket baru.
        </x-admin.alert>
    @elseif ($status['key'] === 'trialing')
        <x-admin.alert type="info" title="Masa uji coba">
            Uji coba berakhir {{ $subscription?->trial_ends_at?->format('d M Y') }}.
            Pilih paket sekarang agar toko tidak terputus.
        </x-admin.alert>
    @endif

    <div class="row g-3 mb-3">
        @foreach ($entitlements as $key => $limit)
            @php $metric = $consumption[$key] ?? ['used' => 0, 'limit' => $limit, 'percent' => 0, 'unlimited' => $limit <= 0]; @endphp
            <div class="col-6 col-xl">
                <x-admin.card class="h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary small text-capitalize">{{ ['products' => 'produk', 'staff' => 'tim', 'storage_mb' => 'penyimpanan (MB)', 'transactions' => 'transaksi'][(string) $key] ?? str_replace('_', ' ', $key) }}</span>
                        <x-admin.badge
                            :text="$metric['unlimited'] ? 'Tak terbatas' : $metric['used'].' / '.$metric['limit']"
                            :color="$metric['unlimited'] ? 'secondary' : ($metric['percent'] >= 90 ? 'danger' : ($metric['percent'] >= 70 ? 'warning' : 'success'))"
                            pill
                        />
                    </div>
                    <div class="progress progress-sm" role="progressbar" aria-label="Pemakaian {{ ['products' => 'produk', 'staff' => 'tim', 'storage_mb' => 'penyimpanan', 'transactions' => 'transaksi'][(string) $key] ?? $key }}" aria-valuenow="{{ $metric['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar bg-{{ $metric['unlimited'] ? 'secondary' : ($metric['percent'] >= 90 ? 'bg-danger' : ($metric['percent'] >= 70 ? 'bg-warning' : 'bg-success')) }}" style="width: {{ $metric['unlimited'] ? 0 : max(2, $metric['percent']) }}%"></div>
                    </div>
                </x-admin.card>
            </div>
        @endforeach
    </div>

    @if ($subscription)
        <x-admin.card title="Paket berjalan" icon="receipt" class="mb-3">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <dl class="row mb-0 small">
                        <dt class="col-6 text-secondary fw-normal">Paket</dt>
                        <dd class="col-6 text-end fw-medium">{{ $plan?->name ?? '—' }}</dd>
                        <dt class="col-6 text-secondary fw-normal">Status</dt>
                        <dd class="col-6 text-end">{{ $__status($subscription->status) }}</dd>
                        <dt class="col-6 text-secondary fw-normal">Mulai</dt>
                        <dd class="col-6 text-end">{{ $subscription->starts_at?->format('d M Y') }}</dd>
                        <dt class="col-6 text-secondary fw-normal">Berakhir</dt>
                        <dd class="col-6 text-end">{{ $subscription->ends_at?->format('d M Y') }}</dd>
                    </dl>
                </div>
                <div class="col-12 col-md-6">
                    <dl class="row mb-0 small">
                        <dt class="col-6 text-secondary fw-normal">Komisi</dt>
                        <dd class="col-6 text-end fw-medium">
                            {{ $plan?->commission_type === 'percentage' ? rtrim(rtrim((string) $plan->commission_value, '0'), '.') . '%' : Currency::format($plan?->commission_value ?? 0) }}
                            @if ($plan?->commission_tier)
                                <span class="text-secondary small">({{ $plan->commission_tier }})</span>
                            @endif
                        </dd>
                        <dt class="col-6 text-secondary fw-normal">Perpanjangan otomatis</dt>
                        <dd class="col-6 text-end">{{ $subscription->auto_renew ? 'Aktif' : 'Nonaktif' }}</dd>
                        <dt class="col-6 text-secondary fw-normal">Metode</dt>
                        <dd class="col-6 text-end text-capitalize">{{ $subscription->payment_method ?? '—' }}</dd>
                        <dt class="col-6 text-secondary fw-normal">Masa tenggang</dt>
                        <dd class="col-6 text-end">{{ $plan?->grace_days > 0 ? $plan->grace_days . ' hari' : 'Tidak ada' }}</dd>
                    </dl>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <form method="POST" action="{{ route('vendor.subscription.renew') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                <x-admin.icon name="refresh" :size="14" class="me-1" />
                                <span>Perpanjang sekarang</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </x-admin.card>
    @endif

    @isset($graceReminder)
        @if ($graceReminder['active'])
            <x-admin.alert :type="$graceReminder['urgent'] ? 'danger' : 'warning'" title="Pengingat langganan">
                {{ $graceReminder['message'] }}
            </x-admin.alert>
        @endif
    @endisset
    @isset($downgradeSuggestion)
        @if ($downgradeSuggestion['available'])
            <x-admin.alert type="info" title="Usulan hemat paket">
                {{ $downgradeSuggestion['reason'] }} Pertimbangkan turun ke paket yang lebih hemat bila tren pemakaian tetap.
            </x-admin.alert>
        @endif
    @endisset

    <x-admin.card title="Pilih paket" icon="layers" class="mb-3" :padding="false">
        <div class="card-body p-3">
            <div class="row g-3">
                @forelse ($plans as $planOption)
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="card h-100 {{ (int) $planOption['id'] === $currentPlanId ? 'border-primary' : '' }}">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                    <h3 class="card-title mb-0">{{ $planOption['name'] }}</h3>
                                    @if ($planOption['is_featured'])
                                        <x-admin.badge text="Populer" color="primary" pill />
                                    @endif
                                </div>

                                <div class="h2 mb-1">{{ Currency::format($planOption['price']->toFloat()) }}</div>
                                <div class="text-secondary small mb-3">
                                    per {{ $planOption['billing_cycle'] === 'monthly' ? 'bulan' : ($planOption['billing_cycle'] === 'yearly' ? 'tahun' : $planOption['billing_cycle']) }}
                                    @if ($planOption['trial_days'] > 0)
                                        · uji coba {{ $planOption['trial_days'] }} hari
                                    @endif
                                </div>

                                <ul class="list-unstyled small text-secondary flex-grow-1 mb-3">
                                    <li class="mb-1">
                                        <x-admin.icon name="check" :size="14" class="me-1 text-success" />
                                        {{ ($planOption['entitlements']['products'] ?? 0) > 0 ? \App\Support\Currency::number($planOption['entitlements']['products']).' produk' : 'Produk tak terbatas' }}
                                    </li>
                                    <li class="mb-1">
                                        <x-admin.icon name="check" :size="14" class="me-1 text-success" />
                                        {{ ($planOption['entitlements']['staff'] ?? 0) > 0 ? \App\Support\Currency::number($planOption['entitlements']['staff']).' anggota tim' : 'Tim tak terbatas' }}
                                    </li>
                                    <li class="mb-1">
                                        <x-admin.icon name="check" :size="14" class="me-1 text-success" />
                                        {{ ($planOption['entitlements']['storage_mb'] ?? 0) > 0 ? \App\Support\Currency::number($planOption['entitlements']['storage_mb']).' MB penyimpanan' : 'Penyimpanan tak terbatas' }}
                                    </li>
                                    <li>
                                        <x-admin.icon name="check" :size="14" class="me-1 text-success" />
                                        {{ ($planOption['entitlements']['transactions'] ?? 0) > 0 ? \App\Support\Currency::number($planOption['entitlements']['transactions']).' transaksi/bulan' : 'Transaksi tak terbatas' }}
                                    </li>
                                </ul>

                                <div class="text-secondary small mb-3">
                                    Komisi
                                    <span class="fw-medium text-body">
                                        {{ $planOption['commission']['type'] === 'percentage' ? rtrim(rtrim($planOption['commission']['value']->toDecimal(), '0'), '.') . '%' : Currency::format($planOption['commission']['value']->toFloat()) }}
                                    </span>
                                    @if ($planOption['commission']['tier'])
                                        <span class="text-secondary">({{ $planOption['commission']['tier'] }})</span>
                                    @endif
                                </div>

                                @if ((int) $planOption['id'] === $currentPlanId)
                                    <button type="button" class="btn btn-outline-secondary w-100" disabled>Paket saat ini</button>
                                @else
                                    <form method="POST" action="{{ route('vendor.subscription.subscribe', $planOption['id']) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-primary w-100">
                                            <x-admin.icon name="check" :size="16" class="me-1" />
                                            <span>Pilih paket</span>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <x-admin.empty-state icon="layers" title="Belum ada paket" text="Admin platform belum mengaktifkan paket langganan apa pun." />
                    </div>
                @endforelse
            </div>
        </div>
    </x-admin.card>

    @if ($subscription && $status['key'] !== 'expired')
        <x-admin.card title="Batalkan langganan" icon="alert-triangle" class="mb-3">
            <x-admin.alert type="warning">
                Pembatalan berlaku di akhir periode berjalan. Toko tetap dapat berjualan sampai
                {{ $subscription->ends_at?->format('d M Y') }}.
            </x-admin.alert>

            <form method="POST" action="{{ route('vendor.subscription.cancel') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-12 col-md-8">
                    <x-admin.form-field name="reason" label="Alasan pembatalan" required placeholder="mis. Alasan tidak relevan" />
                </div>
                <div class="col-12 col-md-4">
                    <button type="submit" class="btn btn-outline-danger w-100" data-confirm="Batalkan langganan sekarang?">
                        Batalkan langganan
                    </button>
                </div>
            </form>
        </x-admin.card>
    @endif
@endsection
