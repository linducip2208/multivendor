<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\Events\ConversationMessageSent;
use App\Events\CouponRedeemed;
use App\Events\CustomerRegistered;
use App\Events\NewVendorRegistered;
use App\Events\OrderCancelled;
use App\Events\OrderCompleted;
use App\Events\OrderConfirmed;
use App\Events\OrderCreated;
use App\Events\OrderDelivered;
use App\Events\OrderPacked;
use App\Events\OrderPaid;
use App\Events\OrderShipped;
use App\Events\PaymentCreated;
use App\Events\PaymentFailed;
use App\Events\PaymentPaid;
use App\Events\PayoutCompleted;
use App\Events\ProductCreated;
use App\Events\ProductRejected;
use App\Events\ProductUpdated;
use App\Events\RefundCompleted;
use App\Events\RefundCreated;
use App\Events\ReturnCompleted;
use App\Events\ReturnRequested;
use App\Events\ReviewCreated;
use App\Events\StockLow;
use App\Events\StockOut;
use App\Events\SupportTicketOpened;
use App\Events\VendorApproved;
use App\Events\VendorSuspended;
use App\Events\WithdrawalApproved;
use App\Events\WithdrawalRequested;
use App\Events\WithdrawApproved;
use ReflectionClass;

final class WebhookEventCatalogue
{
    /** @var array<class-string, string> */
    public const EVENTS = [
        OrderCreated::class => 'order.created',
        OrderPaid::class => 'order.paid',
        OrderConfirmed::class => 'order.confirmed',
        OrderPacked::class => 'order.packed',
        OrderShipped::class => 'order.shipped',
        OrderDelivered::class => 'order.delivered',
        OrderCompleted::class => 'order.completed',
        OrderCancelled::class => 'order.cancelled',
        PaymentCreated::class => 'payment.created',
        PaymentPaid::class => 'payment.paid',
        PaymentFailed::class => 'payment.failed',
        ReturnRequested::class => 'return.requested',
        ReturnCompleted::class => 'return.completed',
        RefundCreated::class => 'refund.created',
        RefundCompleted::class => 'refund.completed',
        NewVendorRegistered::class => 'vendor.registered',
        VendorApproved::class => 'vendor.approved',
        VendorSuspended::class => 'vendor.suspended',
        ProductCreated::class => 'product.created',
        ProductUpdated::class => 'product.updated',
        ProductRejected::class => 'product.rejected',
        StockLow::class => 'product.stock_low',
        StockOut::class => 'product.stock_out',
        ReviewCreated::class => 'review.created',
        CustomerRegistered::class => 'customer.registered',
        CouponRedeemed::class => 'coupon.redeemed',
        WithdrawalRequested::class => 'withdrawal.requested',
        WithdrawalApproved::class => 'withdrawal.approved',
        WithdrawApproved::class => 'withdrawal.approved',
        PayoutCompleted::class => 'payout.completed',
        ConversationMessageSent::class => 'conversation.message_sent',
        SupportTicketOpened::class => 'support.ticket_opened',
    ];

    /** @var array<string, string> */
    private const GROUPS = [
        'order.' => 'Pesanan',
        'payment.' => 'Pembayaran',
        'return.' => 'Retur',
        'refund.' => 'Refund',
        'vendor.' => 'Seller',
        'product.' => 'Katalog',
        'review.' => 'Ulasan',
        'customer.' => 'Pelanggan',
        'coupon.' => 'Promo',
        'withdrawal.' => 'Pencairan',
        'payout.' => 'Pencairan',
        'conversation.' => 'Percakapan',
        'support.' => 'Bantuan',
    ];

    /** @var array<string, string> */
    private const DESCRIPTIONS = [
        'order.created' => 'Pesanan baru dibuat oleh pelanggan.',
        'order.paid' => 'Pembayaran pesanan berhasil dikonfirmasi.',
        'order.confirmed' => 'Pesanan dikonfirmasi seller.',
        'order.packed' => 'Pesanan selesai dikemas.',
        'order.shipped' => 'Pesanan diserahkan ke kurir.',
        'order.delivered' => 'Pesanan diterima pelanggan.',
        'order.completed' => 'Pesanan selesai dan telah diselesaikan.',
        'order.cancelled' => 'Pesanan dibatalkan.',
        'payment.created' => 'Tagihan pembayaran dibuat.',
        'payment.paid' => 'Gateway mengonfirmasi pembayaran.',
        'payment.failed' => 'Pembayaran gagal atau kedaluwarsa.',
        'return.requested' => 'Pelanggan mengajukan retur.',
        'return.completed' => 'Retur selesai diterima.',
        'refund.created' => 'Refund dicatat.',
        'refund.completed' => 'Refund berhasil dikirim ke gateway.',
        'vendor.registered' => 'Pendaftaran vendor baru.',
        'vendor.approved' => 'Vendor disetujui.',
        'vendor.suspended' => 'Vendor ditangguhkan.',
        'product.created' => 'Produk baru ditambahkan.',
        'product.updated' => 'Data produk diperbarui.',
        'product.rejected' => 'Produk ditolak moderasi.',
        'product.stock_low' => 'Stok produk mendekati habis.',
        'product.stock_out' => 'Stok produk habis.',
        'review.created' => 'Ulasan baru menunggu moderasi.',
        'customer.registered' => 'Pelanggan mendaftar.',
        'coupon.redeemed' => 'Kupon ditebus.',
        'withdrawal.requested' => 'Vendor mengajukan pencairan.',
        'withdrawal.approved' => 'Pencairan disetujui admin.',
        'payout.completed' => 'Dana pencairan telah dikirim.',
        'conversation.message_sent' => 'Pesan baru pada percakapan.',
        'support.ticket_opened' => 'Tiket bantuan baru dibuka.',
    ];

    /**
     * @return list<array{event: string, class: class-string, group: string, description: string, entity: string}>
     */
    public function all(): array
    {
        $rows = [];

        foreach (self::EVENTS as $class => $event) {
            $rows[] = [
                'event' => $event,
                'class' => $class,
                'group' => $this->groupFor($event),
                'description' => self::DESCRIPTIONS[$event] ?? '',
                'entity' => $this->entityFor($class),
            ];
        }

        usort($rows, fn (array $a, array $b): int => [$a['group'], $a['event']] <=> [$b['group'], $b['event']]);

        return $rows;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_values(array_unique(array_values(self::EVENTS)));
    }

    /** @return list<class-string> */
    public function classes(): array
    {
        return array_keys(self::EVENTS);
    }

    public function describe(string $event): ?string
    {
        return self::DESCRIPTIONS[$event] ?? null;
    }

    public function groupFor(string $event): string
    {
        foreach (self::GROUPS as $prefix => $group) {
            if (str_starts_with($event, $prefix)) {
                return $group;
            }
        }

        return 'Lainnya';
    }

    private function entityFor(string $class): string
    {
        $constant = (new ReflectionClass($class))->getConstant('ENTITY');

        return is_string($constant) ? $constant : '';
    }
}
