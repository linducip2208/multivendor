<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>Status Pengajuan Toko — {{ config('app.name') }}</title>
    @vite(['resources/css/tabler.css', 'resources/js/tabler.js'])
</head>
<body class="d-flex flex-column">
<div class="page page-center flex-fill">
    <div class="container py-4" style="max-width: 680px;">
        <div class="text-center mb-4">
            <a href="{{ url('/') }}" class="navbar-brand navbar-brand-autodark">
                <span class="navbar-brand-icon"><x-admin.icon name="store" :size="32" /></span>
                <span>{{ config('app.name') }}</span>
            </a>
            <h1 class="h2 mt-3 mb-1">Status Pengajuan Toko</h1>
            <p class="text-secondary">Simpan nomor referensi untuk memantau proses peninjauan.</p>
        </div>
        <div class="card">
            <div class="card-body p-4">
                @if ($application)
                    @php
                        $statusMeta = [
                            'pending' => ['Menunggu', 'warning', 'clock', 'Pengajuan Anda sudah diterima dan menunggu antrean peninjauan.'],
                            'under_review' => ['Ditinjau', 'info', 'search', 'Admin sedang memeriksa dokumen dan data toko Anda.'],
                            'resubmitted' => ['Diajukan ulang', 'info', 'refresh', 'Data sudah diperbarui dan menunggu peninjauan ulang.'],
                            'approved' => ['Disetujui', 'success', 'check-circle', 'Toko Anda sudah aktif. Silakan masuk ke panel vendor.'],
                            'rejected' => ['Ditolak', 'danger', 'circle', 'Pengajuan ditolak. Perbaiki data dan kirim ulang.'],
                        ][$application->status] ?? ['Diproses', 'secondary', 'clock', 'Pengajuan sedang diproses.'];
                    @endphp
                    <div class="alert alert-{{ $statusMeta[1] }} d-flex gap-2 align-items-start" role="alert">
                        <span class="mt-1"><x-admin.icon name="{{ $statusMeta[2] }}" :size="18" /></span>
                        <div>
                            <div class="fw-semibold">{{ $statusMeta[0] }}</div>
                            <div class="small">{{ $statusMeta[3] }}</div>
                        </div>
                    </div>
                    <dl class="row small">
                        <dt class="col-5 text-secondary fw-normal">Nomor referensi</dt>
                        <dd class="col-7 text-end font-monospace fw-semibold">{{ $application->reference }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Nama toko</dt>
                        <dd class="col-7 text-end">{{ $application->shop_name }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Pemilik</dt>
                        <dd class="col-7 text-end">{{ $application->owner_name }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Email</dt>
                        <dd class="col-7 text-end text-break">{{ $application->email }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Rekening</dt>
                        <dd class="col-7 text-end font-monospace">{{ $application->bank_account_number ?? '—' }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Tingkat komisi</dt>
                        <dd class="col-7 text-end text-capitalize">{{ $application->commission_tier }} ({{ number_format((float) $application->commission_value, 2, ',', '.') }}%)</dd>
                        <dt class="col-5 text-secondary fw-normal">Diajukan</dt>
                        <dd class="col-7 text-end">{{ $application->submitted_at ? \Carbon\Carbon::parse($application->submitted_at)->format('d M Y H:i') : '—' }}</dd>
                    </dl>
                    @if ($application->status === 'rejected' && $application->rejection_reason)
                        <div class="alert alert-danger small">
                            <strong>Alasan penolakan:</strong> {{ $application->rejection_reason }}
                        </div>
                    @endif
                    <div class="mt-4 pt-3 border-top">
                        <div class="h4 mb-3">Alur persetujuan</div>
                        <ul class="steps steps-vertical">
                            @foreach ([
                                ['Pengajuan dikirim', $application->submitted_at, true],
                                ['Dokumen diverifikasi', $application->reviewed_at, in_array($application->status, ['under_review', 'approved', 'rejected'], true)],
                                ['Keputusan admin', $application->reviewed_at, in_array($application->status, ['approved', 'rejected'], true)],
                            ] as [$label, $at, $done])
                                <li class="step-item {{ $done ? 'active' : '' }}">
                                    <div class="h4 m-0">{{ $label }}</div>
                                    <div class="text-secondary">{{ $at ? \Carbon\Carbon::parse($at)->format('d M Y H:i') : 'Menunggu' }}</div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <div class="alert alert-warning d-flex gap-2" role="alert">
                        <span><x-admin.icon name="alert-circle" :size="18" /></span>
                        <div>Masukkan nomor referensi untuk melihat status pengajuan Anda.</div>
                    </div>
                    <form method="GET" action="{{ route('vendor.register.status') }}">
                        <label class="form-label" for="reference">Nomor referensi</label>
                        <div class="input-group">
                            <input type="text" id="reference" name="reference" class="form-control" value="{{ old('reference', $reference) }}" placeholder="VND-20260929-ABC123" required>
                            <button class="btn btn-primary" type="submit">Cari</button>
                        </div>
                    </form>
                @endif
                <div class="text-center mt-4">
                    <a href="{{ route('vendor.login') }}" class="btn btn-outline-secondary">
                        <x-admin.icon name="arrow-left" :size="14" /> Kembali ke halaman masuk
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
