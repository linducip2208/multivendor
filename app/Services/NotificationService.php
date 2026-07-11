<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\VendorWithdrawRequest;
use Illuminate\Support\Str;

class NotificationService
{
    protected ?FirebaseService $firebase = null;

    public function __construct()
    {
        $firebase = new FirebaseService;
        if ($firebase->isConfigured()) {
            $this->firebase = $firebase;
        }
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
                'user_' . $user->id,
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

    public function sendWithdrawApproved(VendorWithdrawRequest $withdraw): void
    {
        $vendor = $withdraw->vendor;
        if (!$vendor) return;

        $this->createAndPush($vendor, 'withdraw_approved', [
            'title' => 'Penarikan Disetujui',
            'message' => "Penarikan dana Rp " . number_format($withdraw->amount, 0, ',', '.') . " telah disetujui.",
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
            'message' => "Penarikan dana Rp " . number_format($withdraw->amount, 0, ',', '.') . " ditolak. Alasan: {$withdraw->rejection_reason}",
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
            'message' => "Penarikan dana Rp " . number_format($withdraw->amount, 0, ',', '.') . " telah selesai diproses.",
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
