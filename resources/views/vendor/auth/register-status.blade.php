<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>Status Pengajuan Toko — {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --brand-primary: {{ $whitelabel['brandColor'] }}; --brand-dark: {{ $whitelabel['brandColorDark'] }}; }
        body { font-family: 'Inter', system-ui, sans-serif; background: #f1f5f9; }
        .status-card { border: none; border-radius: 20px; box-shadow: 0 4px 40px rgba(0,0,0,.06); }
        .step { display: flex; gap: 12px; align-items: flex-start; }
        .step__dot { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
    </style>
</head>
<body>
<div class="min-vh-100 d-flex align-items-center justify-content-center p-4">
    <div class="status-card bg-white w-100" style="max-width: 680px;">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <i class="fas fa-store-alt fa-3x text-success mb-2"></i>
                <h1 class="h3 fw-bold mb-1">Status Pengajuan Toko</h1>
                <p class="text-muted mb-0">Simpan nomor referensi untuk memantau proses peninjauan.</p>
            </div>

            @if ($application)
                @php
                    $statusMeta = [
                        'pending' => ['Menunggu', 'warning', 'clock', 'Pengajuan Anda sudah diterima dan menunggu antrean peninjauan.'],
                        'under_review' => ['Ditinjau', 'info', 'search', 'Admin sedang memeriksa dokumen dan data toko Anda.'],
                        'resubmitted' => ['Diajukan ulang', 'info', 'refresh', 'Data sudah diperbarui dan menunggu peninjauan ulang.'],
                        'approved' => ['Disetujui', 'success', 'check-circle', 'Toko Anda sudah aktif. Silakan masuk ke panel vendor.'],
                        'rejected' => ['Ditolak', 'danger', 'x', 'Pengajuan ditolak. Perbaiki data dan kirim ulang.'],
                    ][$application->status] ?? ['Diproses', 'secondary', 'clock', 'Pengajuan sedang diproses.'];
                @endphp

                <div class="alert alert-{{ $statusMeta[1] }} d-flex gap-2 align-items-start">
                    <i class="fas fa-{{ $statusMeta[2] }} mt-1"></i>
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
                    <div class="h6 fw-semibold mb-3">Alur persetujuan</div>
                    <div class="d-flex flex-column gap-3">
                        @foreach ([
                            ['Pengajuan dikirim', $application->submitted_at, true],
                            ['Dokumen diverifikasi', $application->reviewed_at, in_array($application->status, ['under_review', 'approved', 'rejected'], true)],
                            ['Keputusan admin', $application->reviewed_at, in_array($application->status, ['approved', 'rejected'], true)],
                        ] as [$label, $at, $done])
                            <div class="step">
                                <span class="step__dot bg-{{ $done ? 'success' : 'secondary' }}-lt text-{{ $done ? 'success' : 'secondary' }}">
                                    <i class="fas fa-{{ $done ? 'check' : 'clock' }}"></i>
                                </span>
                                <div>
                                    <div class="fw-medium">{{ $label }}</div>
                                    <div class="text-muted small">{{ $at ? \Carbon\Carbon::parse($at)->format('d M Y H:i') : 'Menunggu' }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Masukkan nomor referensi untuk melihat status pengajuan Anda.
                </div>
                <form method="GET" action="{{ route('vendor.register.status') }}">
                    <label class="form-label" for="reference">Nomor referensi</label>
                    <div class="input-group">
                        <input type="text" id="reference" name="reference" class="form-control" value="{{ old('reference', $reference) }}" placeholder="VND-20260929-ABC123" required>
                        <button class="btn btn-success" type="submit">Cari</button>
                    </div>
                </form>
            @endif

            <div class="text-center mt-4">
                <a href="{{ route('vendor.login') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Kembali ke halaman masuk
                </a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
