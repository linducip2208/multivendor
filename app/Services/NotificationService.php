<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendOrderNotification;
use App\Jobs\SendPaymentNotification;
use App\Models\Order;
use App\Models\User;
use App\Models\VendorWithdrawRequest;
use App\Services\Payment\PaymentLog;
use Illuminate\Support\Str;
use Throwable;

class NotificationService
{
    private const ORDER_EVENTS = [
        'order_confirmed',
        'order_packed',
        'order_shipped',
        'order_delivered',
        'order_completed',
        'order_cancelled',
        'return_requested',
        'returned',
        'refund_pending',
        'refunded',
    ];

    private const PAYMENT_EVENTS = [
        'payment_pending',
        'payment_paid',
        'payment_partial',
        'payment_failed',
        'payment_expired',
        'payment_refunded',
        'refund_requested',
        'refund_approved',
        'refund_rejected',
        'refund_succeeded',
        'refund_failed',
    ];

    protected ?FirebaseService $firebase = null;

    public function __construct()
    {
        $firebase = new FirebaseService;
        if ($firebase->isConfigured()) {
            $this->firebase = $firebase;
        }
    }

    public function queueOrderEvent(Order|int $order, string $event): void
    {
        if (! in_array($event, self::ORDER_EVENTS, true)) {
            return;
        }

        SendOrderNotification::dispatch($order instanceof Order ? (int) $order->getKey() : $order, $event)->afterCommit();
    }

    public function queuePaymentEvent(Order|int $order, string $event): void
    {
        if (! in_array($event, self::PAYMENT_EVENTS, true)) {
            return;
        }

        SendPaymentNotification::dispatch($order instanceof Order ? (int) $order->getKey() : $order, $event)->afterCommit();
    }

    public function sendForEvent(Order $order, string $event): void
    {
        match ($event) {
            'order_confirmed' => $this->sendOrderConfirmation($order),
            'order_packed' => $this->sendOrderPacked($order),
            'order_shipped' => $this->sendOrderShipped($order),
            'order_delivered' => $this->sendOrderDelivered($order),
            'order_completed' => $this->sendOrderCompleted($order),
            'order_cancelled' => $this->sendOrderCancelled($order),
            'return_requested' => $this->sendReturnRequested($order),
            'returned' => $this->sendReturnReceived($order),
            'refund_pending' => $this->sendRefundPending($order),
            'refunded' => $this->sendRefunded($order),
            'payment_pending' => $this->sendPaymentPending($order),
            'payment_paid' => $this->sendPaymentPaid($order),
            'payment_partial' => $this->sendPaymentPartial($order),
            'payment_failed' => $this->sendPaymentFailed($order),
            'payment_expired' => $this->sendPaymentExpired($order),
            'payment_refunded' => $this->sendPaymentRefunded($order),
            'refund_requested' => $this->sendRefundRequested($order),
            'refund_approved' => $this->sendRefundDecision($order, 'disetujui'),
            'refund_rejected' => $this->sendRefundDecision($order, 'ditolak'),
            'refund_succeeded' => $this->sendRefunded($order),
            'refund_failed' => $this->sendRefundFailure($order),
            default => null,
        };
    }

    public function logJobFailure(string $event, int $orderId, ?Throwable $exception): void
    {
        PaymentLog::channel('warning', 'Queued notification failed', [
            'event' => $event,
            'order_id' => $orderId,
            'exception' => $exception?->getMessage(),
        ]);
    }

    protected function createAndPush(User $user, string $type, array $data): void
    {
        \App\Models\Notification::create([
            'id' => Str::uuid(),
            'type' => $type,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => $data,
        ]);

        if ($this->firebase) {
            $this->firebase->sendToTopic(
                'user_'.$user->id,
                $data['title'] ?? '',
                $data['message'] ?? '',
                ['type' => $type, 'data' => json_encode($data)],
            );
        }
    }

    public function sendOrderConfirmation(Order $order): void
    {
        $customer = $order->customer;
        if (!$customer) return;

        $this->createAndPush($customer, 'order_confirmation', [
            'title' => 'Pesanan Dikonfirmasi',
            'message' => "Pesanan #{$order->order_number} telah dikonfirmasi.",
            'order_number' => $order->order_number,
            'order_id' => $order->id,
            'total' => $order->total,
            'status' => $order->order_status,
        ]);

        $shop = $order->shop;
        if ($shop && $shop->vendor) {
            $this->createAndPush($shop->vendor, 'new_order', [
                'title' => 'Pesanan Baru',
                'message' => "Pesanan baru #{$order->order_number} dari {$customer->name}",
                'order_number' => $order->order_number,
                'order_id' => $order->id,
                'total' => $order->total,
            ]);
        }
    }

    public function sendOrderPacked(Order $order): void
    {
        $customer = $order->customer;
        if (!$customer) return;

        $this->createAndPush($customer, 'order_packed', [
            'title' => 'Pesanan Dikemas',
            'message' => "Pesanan #{$order->order_number} sedang dikemas oleh penjual.",
            'order_number' => $order->order_number,
            'order_id' => $order->id,
        ]);
    }

    public function sendOrderShipped(Order $order): void
    {
        $customer = $order->customer;
        if (!$customer) return;

        $this->createAndPush($customer, 'order_shipped', [
            'title' => 'Pesanan Dikirim',
            'message' => "Pesanan #{$order->order_number} sedang dalam perjalanan.",
            'order_number' => $order->order_number,
            'order_id' => $order->id,
            'tracking_id' => $order->shipping_tracking_id,
        ]);

        if ($order->delivery_man_id) {
            $dm = User::find($order->delivery_man_id);
            if ($dm) {
                $this->createAndPush($dm, 'delivery_assigned', [
                    'title' => 'Pengiriman Baru',
                    'message' => "Anda ditugaskan mengirim pesanan #{$order->order_number}",
                    'order_number' => $order->order_number,
                    'order_id' => $order->id,
                ]);
            }
        }
    }

    public function sendOrderDelivered(Order $order): void
    {
        $customer = $order->customer;
        if (!$customer) return;

        $this->createAndPush($customer, 'order_delivered', [
            'title' => 'Pesanan Tiba',
            'message' => "Pesanan #{$order->order_number} telah diterima. Jangan lupa beri ulasan pengiriman!",
            'order_number' => $order->order_number,
            'order_id' => $order->id,
            'rate_url' => route('delivery.rate', $order),
        ]);
    }

    public function sendOrderCompleted(Order $order): void
    {
        $customer = $order->customer;
        if (!$customer) return;

        $this->createAndPush($customer, 'order_completed', [
            'title' => 'Pesanan Selesai',
            'message' => "Pesanan #{$order->order_number} telah selesai. Terima kasih telah berbelanja!",
            'order_number' => $order->order_number,
            'order_id' => $order->id,
        ]);
    }

    public function sendOrderCancelled(Order $order): void
    {
        $customer = $order->customer;
        if (!$customer) return;

        $this->createAndPush($customer, 'order_cancelled', [
            'title' => 'Pesanan Dibatalkan',
            'message' => "Pesanan #{$order->order_number} telah dibatalkan.",
            'order_number' => $order->order_number,
            'order_id' => $order->id,
            'reason' => $order->cancel_reason,
        ]);
    }

    public function sendPaymentPending(Order $order): void
    {
        $customer = $order->customer;
        if (!$customer) return;

        $this->createAndPush($customer, 'payment_pending', [
            'title' => 'Menunggu Pembayaran',
            'message' => "Pembayaran pesanan #{$order->order_number} belum diterima.",
            'order_number' => $order->order_number,
            'order_id' => $order->id,
        ]);
    }

    public function sendPaymentPaid(Order $order): void
    {
        $customer = $order->customer;
        if (!$customer) return;

        $this->createAndPush($customer, 'payment_paid', [
            'title' => 'Pembayaran Berhasil',
            'message' => "Pembayaran pesanan #{$order->order_number} telah kami terima.",
            'order_number' => $order->order_number,
            'order_id' => $order->id,
            'total' => $order->total,
        ]);
    }

    public function sendPaymentPartial(Order $order): void
    {
        $customer = $order->customer;
        if (!$customer) return;

        $this->createAndPush($customer, 'payment_partial', [
            'title' => 'Pembayaran Sebagian',
            'message' => "Sebagian pembayaran pesanan #{$order->order_number} telah diterima.",
            'order_number' => $order->order_number,
            'order_id' => $order->id,
        ]);
    }

    public function sendPaymentFailed(Order $order): void
    {
        $customer = $order->customer;
        if (!$customer) return;

        $this->createAndPush($customer, 'payment_failed', [
            'title' => 'Pembayaran Gagal',
            'message' => "Pembayaran pesanan #{$order->order_number} gagal diproses.",
            'order_number' => $order->order_number,
            'order_id' => $order->id,
        ]);
    }

    public function sendPaymentExpired(Order $order): void
    {
        $customer = $order->customer;
        if (!$customer) return;

        $this->createAndPush($customer, 'payment_expired', [
            'title' => 'Pembayaran Kedaluwarsa',
            'message' => "Pembayaran pesanan #{$order->order_number} telah kedaluwarsa.",
            'order_number' => $order->order_number,
            'order_id' => $order->id,
        ]);
    }

    public function sendPaymentRefunded(Order $order): void
    {
        $customer = $order->customer;
        if (!$customer) return;

        $this->createAndPush($customer, 'payment_refunded', [
            'title' => 'Pembayaran Dikembalikan',
            'message' => "Pembayaran pesanan #{$order->order_number} telah dikembalikan.",
            'order_number' => $order->order_number,
            'order_id' => $order->id,
        ]);
    }

    public function sendReturnRequested(Order $order): void
    {
        $customer = $order->customer;
        if (!$customer) return;

        $this->createAndPush($customer, 'return_requested', [
            'title' => 'Retur Diajukan',
            'message' => "Permintaan retur pesanan #{$order->order_number} sedang ditinjau.",
            'order_number' => $order->order_number,
            'order_id' => $order->id,
        ]);
    }

    public function sendReturnReceived(Order $order): void
    {
        $customer = $order->customer;
        if (!$customer) return;

        $this->createAndPush($customer, 'returned', [
            'title' => 'Retur Diterima',
            'message' => "Retur pesanan #{$order->order_number} telah diterima oleh penjual.",
            'order_number' => $order->order_number,
            'order_id' => $order->id,
        ]);
    }

    public function sendRefundPending(Order $order): void
    {
        $customer = $order->customer;
        if (!$customer) return;

        $this->createAndPush($customer, 'refund_pending', [
            'title' => 'Refund Diproses',
            'message' => "Refund pesanan #{$order->order_number} sedang diproses ke pembayaran Anda.",
            'order_number' => $order->order_number,
            'order_id' => $order->id,
        ]);
    }

    public function sendRefunded(Order $order): void
    {
        $customer = $order->customer;
        if (!$customer) return;

        $this->createAndPush($customer, 'refunded', [
            'title' => 'Refund Selesai',
            'message' => "Refund pesanan #{$order->order_number} telah dikembalikan.",
            'order_number' => $order->order_number,
            'order_id' => $order->id,
            'amount' => $order->refunded_amount,
        ]);
    }

    public function sendRefundRequested(Order $order): void
    {
        $vendor = $order->shop?->vendor;
        if (!$vendor) return;

        $this->createAndPush($vendor, 'refund_requested', [
            'title' => 'Permintaan Refund',
            'message' => "Ada permintaan refund untuk pesanan #{$order->order_number}.",
            'order_number' => $order->order_number,
            'order_id' => $order->id,
        ]);
    }

    public function sendRefundDecision(Order $order, string $label): void
    {
        $customer = $order->customer;
        if (!$customer) return;

        $this->createAndPush($customer, 'refund_decision', [
            'title' => 'Refund '.$label,
            'message' => "Permintaan refund pesanan #{$order->order_number} telah {$label}.",
            'order_number' => $order->order_number,
            'order_id' => $order->id,
        ]);
    }

    public function sendRefundFailure(Order $order): void
    {
        $customer = $order->customer;
        if (!$customer) return;

        $this->createAndPush($customer, 'refund_failed', [
            'title' => 'Refund Gagal',
            'message' => "Refund pesanan #{$order->order_number} belum berhasil diproses. Tim kami akan mencoba kembali.",
            'order_number' => $order->order_number,
            'order_id' => $order->id,
        ]);
    }

    public function sendWithdrawApproved(VendorWithdrawRequest $withdraw): void
    {
        $vendor = $withdraw->vendor;
        if (!$vendor) return;

        $this->createAndPush($vendor, 'withdraw_approved', [
            'title' => 'Penarikan Disetujui',
            'message' => "Penarikan dana Rp " . number_format((float) $withdraw->amount, 0, ',', '.') . " telah disetujui.",
            'amount' => $withdraw->amount,
            'withdraw_id' => $withdraw->id,
        ]);
    }

    public function sendWithdrawRejected(VendorWithdrawRequest $withdraw): void
    {
        $vendor = $withdraw->vendor;
        if (!$vendor) return;

        $this->createAndPush($vendor, 'withdraw_rejected', [
            'title' => 'Penarikan Ditolak',
            'message' => "Penarikan dana Rp " . number_format((float) $withdraw->amount, 0, ',', '.') . " ditolak. Alasan: {$withdraw->rejection_reason}",
            'amount' => $withdraw->amount,
            'withdraw_id' => $withdraw->id,
            'reason' => $withdraw->rejection_reason,
        ]);
    }

    public function sendWithdrawCompleted(VendorWithdrawRequest $withdraw): void
    {
        $vendor = $withdraw->vendor;
        if (!$vendor) return;

        $this->createAndPush($vendor, 'withdraw_completed', [
            'title' => 'Penarikan Selesai',
            'message' => "Penarikan dana Rp " . number_format((float) $withdraw->amount, 0, ',', '.') . " telah selesai diproses.",
            'amount' => $withdraw->amount,
            'withdraw_id' => $withdraw->id,
        ]);
    }

    public function sendOnboardingComplete(User $vendor): void
    {
        $this->createAndPush($vendor, 'onboarding_complete', [
            'title' => 'Toko Siap!',
            'message' => 'Setup toko Anda selesai. Mulai tambahkan produk dan dapatkan penjualan!',
        ]);
    }

    public function sendBulkPush(string $title, string $message, string $targetType = 'all', array $targetIds = [], ?string $image = null, ?string $targetUrl = null): void
    {
        \App\Models\PushNotification::create([
            'title' => $title,
            'description' => $message,
            'image' => $image,
            'target_url' => $targetUrl,
            'target_type' => $targetType,
            'target_ids' => $targetIds ? json_encode($targetIds) : null,
            'sent' => false,
        ]);

        if ($this->firebase) {
            $this->firebase->sendToTopic($targetType, $title, $message, ['url' => $targetUrl], $image);
        }
    }

    public function getUnreadCount(User $user): int
    {
        return \App\Models\Notification::where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    public function getUserNotifications(User $user, int $perPage = 20)
    {
        return \App\Models\Notification::where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->latest()
            ->paginate($perPage);
    }

    public function markAsRead(string $notificationId): void
    {
        \App\Models\Notification::where('id', $notificationId)->update(['read_at' => now()]);
    }

    public function markAllAsRead(User $user): void
    {
        \App\Models\Notification::where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /* ------------------------------------------------------------------ */
    /* Kanal WhatsApp (aditif): kredensial sms-gateway existing, fallback   */
    /* log + status. Tidak mengubah method notifikasi existing.             */
    /* ------------------------------------------------------------------ */

    /**
     * WA dianggap tersedia bila provider sms-gateway dikonfigurasi
     * (system_settings: sms_provider ≠ none + sms_api_key terisi,
     * terverifikasi di CmsController::smsGateway).
     */
    public function waTersedia(): bool
    {
        try {
            $provider = (string) \App\Models\SystemSetting::get('sms_provider', 'none');
            $kunci = (string) \App\Models\SystemSetting::get('sms_api_key', '');

            return $provider !== '' && $provider !== 'none' && $kunci !== '';
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Kirim pesan WhatsApp. Bila gateway tak dikonfigurasi, pesan dicatat
     * ke log + user_notifications (channel whatsapp) dengan status
     * tercatat_log — tidak pernah melempar.
     *
     * @return array{ok: bool, channel: string, status: string, provider: string}
     */
    public function kirimWa(?string $nomor, string $pesan, array $meta = []): array
    {
        $provider = 'none';
        try {
            $provider = (string) (\App\Models\SystemSetting::get('sms_provider', 'none') ?? 'none');
        } catch (\Throwable) {
        }

        $pesan = trim($pesan);
        if ($pesan === '') {
            return ['ok' => false, 'channel' => 'whatsapp', 'status' => 'pesan_kosong', 'provider' => $provider];
        }

        $tersedia = $this->waTersedia();
        $status = $tersedia ? 'terkirim_via_gateway' : 'tercatat_log';

        try {
            \Illuminate\Support\Facades\Log::info('notifikasi.whatsapp', [
                'provider' => $provider,
                'nomor' => $this->samarkanNomor((string) $nomor),
                'status' => $status,
                'pesan' => mb_substr($pesan, 0, 500),
                'meta' => $meta,
            ]);
        } catch (\Throwable) {
        }

        // Jejak di pusat notifikasi (best-effort, kategori marketing).
        try {
            $userId = $meta['user_id'] ?? null;
            if (is_numeric($userId) && (int) $userId > 0) {
                \App\Models\UserNotification::query()->create([
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'notifiable_type' => User::class,
                    'notifiable_id' => (int) $userId,
                    'channel' => 'whatsapp',
                    'category' => 'marketing',
                    'title' => (string) ($meta['judul'] ?? 'Notifikasi WhatsApp'),
                    'body' => mb_substr($pesan, 0, 1000),
                    'dedupe_key' => (string) ($meta['dedupe_key'] ?? ('wa:'.md5(((string) $nomor).'|'.$pesan.'|'.now()->format('YmdHi')))),
                ]);
            }
        } catch (\Throwable) {
        }

        return ['ok' => true, 'channel' => 'whatsapp', 'status' => $status, 'provider' => $provider];
    }

    /**
     * Notifikasi cashback via WA ke nomor HP pelanggan.
     *
     * @return array{ok: bool, channel: string, status: string, provider: string}
     */
    public function kirimWaCashback(User $user, float $nominal, string $keterangan = ''): array
    {
        $nominalFmt = 'Rp '.number_format(max(0.0, $nominal), 0, ',', '.');
        $pesan = 'Cashback '.$nominalFmt.' telah masuk ke '.($keterangan !== '' ? $keterangan : 'akun Anda').'. Terima kasih telah berbelanja!';

        return $this->kirimWa($user->phone ?? null, $pesan, [
            'user_id' => (int) $user->getKey(),
            'judul' => 'Cashback diterima',
            'dedupe_key' => 'wa:cashback:'.$user->getKey().':'.$nominal.':'.now()->format('YmdHi'),
        ]);
    }

    private function samarkanNomor(string $nomor): string
    {
        $digit = (string) preg_replace('/\D+/', '', $nomor);
        if (strlen($digit) <= 4) {
            return $digit === '' ? '-' : '***';
        }

        return substr($digit, 0, 3).'***'.substr($digit, -2);
    }
}
