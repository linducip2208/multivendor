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
}
