@php
    if (! isset($__money)) {
        $__money = static fn (float|int|string|null $value): string => \App\Support\Currency::format($value);
        $__int = static fn (float|int|string|null $value): string => \App\Support\Currency::number($value);
        $__date = static fn ($value, string $format = 'd M Y H:i'): string => $value ? \Carbon\Carbon::parse($value)->format($format) : '-';

        $__orderStatus = static function (mixed $status): string {
            $case = \App\Enums\OrderStatus::fromStored(is_string($status) ? $status : (string) $status);

            return '<span class="badge bg-'.$case->badge().'-lt text-'.$case->badge().' rounded-pill">'
                .e($case->label())
                .'</span>';
        };

        $__paymentStatus = static function (mixed $status): string {
            $map = [
                'unpaid' => ['Belum bayar', 'warning'],
                'pending' => ['Menunggu', 'warning'],
                'paid' => ['Lunas', 'success'],
                'failed' => ['Gagal', 'danger'],
                'refunded' => ['Dikembalikan', 'secondary'],
                'expired' => ['Kedaluwarsa', 'secondary'],
            ];

            $key = strtolower((string) $status);
            [$label, $color] = $map[$key] ?? [\Illuminate\Support\Str::headline($key), 'secondary'];

            return '<span class="badge bg-'.$color.'-lt text-'.$color.' rounded-pill">'.e($label).'</span>';
        };

        $__status = static function (mixed $status, array $map = []): string {
            $defaults = [
                'pending' => ['Menunggu', 'warning'],
                'open' => ['Terbuka', 'info'],
                'processing' => ['Diproses', 'primary'],
                'approved' => ['Disetujui', 'success'],
                'paid' => ['Lunas', 'success'],
                'success' => ['Berhasil', 'success'],
                'completed' => ['Selesai', 'success'],
                'delivered' => ['Diterima', 'success'],
                'answered' => ['Dijawab', 'success'],
                'rejected' => ['Ditolak', 'danger'],
                'failed' => ['Gagal', 'danger'],
                'canceled' => ['Dibatalkan', 'secondary'],
                'cancelled' => ['Dibatalkan', 'secondary'],
                'expired' => ['Kedaluwarsa', 'secondary'],
                'active' => ['Aktif', 'success'],
                'draft' => ['Draf', 'secondary'],
                'trialing' => ['Uji coba', 'info'],
                'grace' => ['Tenggang', 'warning'],
                'notified' => ['Sudah diberi tahu', 'success'],
            ];

            $key = strtolower(str_replace([' ', '-'], '_', (string) $status));
            [$label, $color] = $map[$key] ?? $defaults[$key] ?? [\Illuminate\Support\Str::headline($key), 'secondary'];

            return '<span class="badge bg-'.$color.'-lt text-'.$color.' rounded-pill">'.e($label).'</span>';
        };

        $__maskAccount = static fn (?string $account): string => \App\Services\Vendor\VendorScope::maskAccount($account) ?? '—';
    }
@endphp
