<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_number', 'customer_id', 'shop_id', 'coupon_code', 'coupon_discount',
    'sub_total', 'tax', 'shipping_cost', 'discount', 'total',
    'shipping_method', 'shipping_service', 'shipping_tracking_id',
    'delivery_verification_code', 'delivery_man_id', 'shipping_address',
    'billing_address', 'payment_method', 'payment_status', 'order_status',
    'note', 'cancel_reason', 'confirmed_at', 'processing_at', 'shipped_at',
    'delivered_at', 'canceled_at', 'payment_group_id', 'stock_released_at',
    'parent_order_id', 'source', 'fulfillment_status', 'warehouse_id',
    'packed_at', 'completed_at', 'returned_at', 'refunded_at', 'return_reason',
    'refunded_amount', 'currency', 'idempotency_key', 'pos_shift_id', 'pos_register_id',
    'reconciled_at', 'referral_code', 'utm_source', 'utm_medium', 'utm_campaign',
    'is_dropship', 'dropship_sender_name', 'dropship_sender_store', 'hide_price_in_package',
    'is_preorder', 'preorder_eta', 'preorder_dp_amount', 'preorder_remaining', 'preorder_settled_at',
    'is_gift', 'gift_wrap', 'gift_message', 'gift_fee',
    'cod_otp_hash', 'cod_otp_expires_at', 'cod_otp_attempts', 'cod_otp_verified_at',
])]
class Order extends Model
{
    protected function casts(): array
    {
        return [
            'shipping_address' => 'json',
            'billing_address' => 'json',
            'sub_total' => 'decimal:2',
            'tax' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'discount' => 'decimal:2',
            'coupon_discount' => 'decimal:2',
            'total' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'processing_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'canceled_at' => 'datetime',
            'stock_released_at' => 'datetime',
            'packed_at' => 'datetime',
            'completed_at' => 'datetime',
            'returned_at' => 'datetime',
            'refunded_at' => 'datetime',
            'reconciled_at' => 'datetime',
            'is_dropship' => 'boolean',
            'hide_price_in_package' => 'boolean',
            'is_preorder' => 'boolean',
            'preorder_eta' => 'date',
            'preorder_dp_amount' => 'decimal:2',
            'preorder_remaining' => 'decimal:2',
            'preorder_settled_at' => 'datetime',
            'is_gift' => 'boolean',
            'gift_wrap' => 'boolean',
            'gift_fee' => 'decimal:2',
            'cod_otp_expires_at' => 'datetime',
            'cod_otp_verified_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function paymentGroup(): BelongsTo
    {
        return $this->belongsTo(PaymentGroup::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function transaction(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function deliveryMan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_man_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_order_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_order_id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(OrderShipment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function posShift(): BelongsTo
    {
        return $this->belongsTo(PosShift::class, 'pos_shift_id');
    }

    /** Nomor seri invoice turunan dari nomor order (tanpa tabel baru). */
    public function invoiceNumber(): string
    {
        $base = (string) ($this->order_number ?: 'ORD-'.$this->getKey());

        return str_starts_with($base, 'INV-') ? $base : 'INV-'.$base;
    }

    /** Catatan per toko ditempel pada kolom note existing. */
    public static function formatShopNote(string $shopName, ?string $note): ?string
    {
        $note = is_string($note) ? trim($note) : '';

        if ($note === '') {
            return null;
        }

        return '[Toko '.$shopName.'] '.mb_substr($note, 0, 1500);
    }

    /** Status grup pembayaran yang masih boleh dicoba ulang tanpa order baru. */
    public function isPaymentRetryable(): bool
    {
        $status = (string) ($this->paymentGroup?->status ?? '');

        return in_array($status, ['pending', 'failed', 'expired'], true)
            && in_array((string) $this->order_status, ['pending', 'failed'], true);
    }

    public static function generateOrderNumber(): string
    {
        $prefix = \App\Models\SystemSetting::get('order_prefix', 'ORD');
        do {
            $number = "{$prefix}-".now()->format('Ymd').'-'.strtoupper(\Illuminate\Support\Str::random(10));
        } while (static::where('order_number', $number)->exists());
        return $number;
    }

    // ── Kapabilitas operasional (aditif, tanpa mengubah logika existing) ──

    /** Dropship: order dikirim atas nama dropshipper. */
    public function isDropship(): bool
    {
        return (bool) ($this->getAttribute('is_dropship') ?? false);
    }

    /** Nama pengirim pada label: toko/nama dropshipper bila dropship, nama toko bila tidak. */
    public function shippingLabelSender(): string
    {
        if ($this->isDropship()) {
            $sender = trim((string) ($this->getAttribute('dropship_sender_store') ?: $this->getAttribute('dropship_sender_name')));

            return $sender !== '' ? $sender : (string) ($this->shop?->name ?? '');
        }

        return (string) ($this->shop?->name ?? '');
    }

    /** Harga disembunyikan dari paket (label/invoice paket) untuk dropship. */
    public function shouldHidePrices(): bool
    {
        return (bool) ($this->getAttribute('hide_price_in_package') ?? false);
    }

    /** Pre-order: masih ada sisa pelunasan yang harus dibayar sebelum kirim. */
    public function isPreorder(): bool
    {
        return (bool) ($this->getAttribute('is_preorder') ?? false);
    }

    public function preorderBalanceDue(): float
    {
        if (! $this->isPreorder()) {
            return 0.0;
        }

        if ($this->getAttribute('preorder_settled_at') !== null) {
            return 0.0;
        }

        return max(0.0, (float) ($this->getAttribute('preorder_remaining') ?? 0));
    }

    public function hasSettledPreorder(): bool
    {
        return ! $this->isPreorder() || $this->preorderBalanceDue() <= 0;
    }

    /** Gift: order memakai bungkus kado. */
    public function isGift(): bool
    {
        return (bool) ($this->getAttribute('is_gift') ?? false);
    }

    /** Kartu ucapan untuk paket gift. */
    public function giftCardMessage(): ?string
    {
        $message = trim((string) ($this->getAttribute('gift_message') ?? ''));

        return $message === '' ? null : $message;
    }

    /** COD di atas ambang nominal wajib verifikasi OTP saat terima. */
    public function codOtpRequired(?float $threshold = null): bool
    {
        $method = strtolower((string) ($this->getAttribute('payment_method') ?? ''));

        if (! str_contains($method, 'cod')) {
            return false;
        }

        $limit = $threshold ?? (float) (\App\Models\SystemSetting::get('cod_otp_threshold', '500000') ?: 500000);

        return (float) ($this->getAttribute('total') ?? 0) >= $limit;
    }

    public function codOtpVerified(): bool
    {
        return $this->getAttribute('cod_otp_verified_at') !== null;
    }
}
