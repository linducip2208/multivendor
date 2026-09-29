<?php

declare(strict_types=1);

namespace App\Services\Vendor;

use App\Models\Shop;
use App\Models\ShopShippingMethod;
use App\Models\ShippingMethod;
use App\Services\AuditLogger;
use App\Support\Currency;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Store, shipping, notification and security settings.
 *
 * The shop row owns identity and money-bearing settings, shipping rates live in
 * their own pivot, and notification/security preferences are per shop and
 * channel. Bank details are the one field that is never returned unmasked.
 */
final class VendorSettingsService
{
    public const CATEGORIES = [
        'order.created' => 'Pesanan baru',
        'order.paid' => 'Pembayaran diterima',
        'order.shipped' => 'Pesanan dikirim',
        'order.cancelled' => 'Pesanan dibatalkan',
        'return.requested' => 'Permintaan retur',
        'refund.processed' => 'Refund diproses',
        'payout.processed' => 'Penarikan dana diproses',
        'stock.low' => 'Stok menipis',
        'review.received' => 'Ulasan baru',
        'ticket.replied' => 'Balasan tiket',
    ];

    public const CHANNELS = ['email' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'in_app' => 'Dalam aplikasi'];

    public function __construct(private readonly VendorScope $scope) {}

    public function index(): array
    {
        return ['shop' => $this->publicShop()];
    }

    public function shipping(): array
    {
        $shopId = $this->scope->shopId();

        $assigned = ShopShippingMethod::query()
            ->where('shop_id', $shopId)
            ->get()
            ->keyBy('shipping_method_id');

        $available = ShippingMethod::query()
            ->where('status', true)
            ->orderBy('name')
            ->get()
            ->map(fn (ShippingMethod $method): array => [
                'id' => (int) $method->getKey(),
                'name' => (string) $method->name,
                'code' => (string) $method->code,
                'enabled' => $assigned->has($method->getKey()),
                'cost' => $assigned->has($method->getKey())
                    ? Money::of($assigned->get($method->getKey())->cost)
                    : Money::of($method->cost),
                'estimated_days' => (int) ($method->duration ?? 0),
            ]);

        return [
            'methods' => $available,
            'currency' => Currency::config(),
        ];
    }

    public function updateShipping(array $rates): void
    {
        $shopId = $this->scope->shopId();

        $valid = ShippingMethod::query()->where('status', true)->pluck('id')->map('intval')->all();

        DB::transaction(function () use ($shopId, $rates, $valid): void {
            ShopShippingMethod::query()->where('shop_id', $shopId)->delete();

            foreach ($rates as $id => $rate) {
                $methodId = (int) $id;

                if (! in_array($methodId, $valid, true) || ! is_array($rate)) {
                    continue;
                }

                if (! filter_var($rate['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    continue;
                }

                ShopShippingMethod::query()->create([
                    'shop_id' => $shopId,
                    'shipping_method_id' => $methodId,
                    'cost' => Money::of($rate['cost'] ?? 0)->maxZero()->toDecimal(),
                    'status' => true,
                ]);
            }

            app(AuditLogger::class)->log('vendor.shipping.updated', $this->scope->shop(), [], [
                'rates' => count($rates),
            ], $this->scope->userId());
        }, 3);
    }

    public function updateStore(array $payload): Shop
    {
        $shopId = $this->scope->shopId();

        $attributes = [
            'name' => VendorScope::clean($payload['name'], 160),
            'description' => VendorScope::cleanNullable($payload['description'] ?? null, 2000),
            'phone' => VendorScope::cleanNullable($payload['phone'] ?? null, 32),
            'email' => VendorScope::cleanNullable($payload['email'] ?? null, 160),
            'address' => VendorScope::cleanNullable($payload['address'] ?? null, 500),
            'city' => VendorScope::cleanNullable($payload['city'] ?? null, 100),
            'province' => VendorScope::cleanNullable($payload['province'] ?? null, 100),
            'postal_code' => VendorScope::cleanNullable($payload['postal_code'] ?? null, 12),
            'meta_title' => VendorScope::cleanNullable($payload['meta_title'] ?? null, 160),
            'meta_description' => VendorScope::cleanNullable($payload['meta_description'] ?? null, 500),
            'vacation_message' => VendorScope::cleanNullable($payload['vacation_message'] ?? null, 500),
        ];

        if (Schema::hasColumn('shops', 'vacation_mode')) {
            $attributes['vacation_mode'] = filter_var($payload['vacation_mode'] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        return DB::transaction(function () use ($shopId, $attributes, $payload): Shop {
            $shop = Shop::query()->lockForUpdate()->findOrFail($shopId);

            if (array_key_exists('bank_name', $payload) || array_key_exists('bank_account_name', $payload) || array_key_exists('bank_account_number', $payload)) {
                $attributes['bank_name'] = VendorScope::cleanNullable($payload['bank_name'] ?? $shop->bank_name, 120);
                $attributes['bank_account_name'] = VendorScope::cleanNullable($payload['bank_account_name'] ?? $shop->bank_account_name, 120);

                $number = VendorScope::cleanNullable($payload['bank_account_number'] ?? null, 64);

                if ($number !== null && $number !== '') {
                    $attributes['bank_account_number'] = $number;
                }
            }

            $shop->forceFill($attributes)->save();

            app(AuditLogger::class)->log('vendor.settings.updated', $shop, [], [
                'fields' => array_keys($attributes),
            ], $this->scope->userId());

            return $shop->refresh();
        }, 3);
    }

    public function notifications(): array
    {
        $shopId = $this->scope->shopId();

        $rows = DB::table('shop_notification_preferences')
            ->where('shop_id', $shopId)
            ->get()
            ->keyBy(fn (object $row): string => $row->category.'|'.$row->channel);

        $matrix = [];

        foreach (self::CATEGORIES as $category => $label) {
            foreach (self::CHANNELS as $channel => $channelLabel) {
                $row = $rows->get($category.'|'.$channel);

                $matrix[$category] ??= ['label' => $label, 'channels' => []];

                $matrix[$category]['channels'][$channel] = [
                    'label' => $channelLabel,
                    'enabled' => $row === null ? $channel === 'in_app' : (bool) $row->enabled,
                    'destination' => $row?->destination,
                ];
            }
        }

        return [
            'matrix' => $matrix,
            'categories' => self::CATEGORIES,
            'channels' => self::CHANNELS,
        ];
    }

    public function updateNotifications(array $payload): int
    {
        $shopId = $this->scope->shopId();
        $written = 0;

        DB::transaction(function () use ($shopId, $payload, &$written): void {
            foreach (self::CATEGORIES as $category => $_) {
                foreach (self::CHANNELS as $channel => $_) {
                    $cell = $payload[$category][$channel] ?? null;

                    if (! is_array($cell)) {
                        continue;
                    }

                    DB::table('shop_notification_preferences')->updateOrInsert(
                        ['shop_id' => $shopId, 'category' => $category, 'channel' => $channel],
                        [
                            'enabled' => filter_var($cell['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                            'destination' => VendorScope::cleanNullable($cell['destination'] ?? null, 190),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );

                    $written++;
                }
            }
        }, 3);

        return $written;
    }

    public function security(): array
    {
        $shopId = $this->scope->shopId();

        $row = DB::table('shop_security_settings')->where('shop_id', $shopId)->first();

        return [
            'settings' => $row,
            'two_factor_enabled' => (bool) ($row->two_factor_enabled ?? false),
            'login_alert' => (bool) ($row->login_alert ?? true),
            'session_timeout_minutes' => (int) ($row->session_timeout_minutes ?? 120),
            'ip_allowlist' => json_decode((string) ($row->ip_allowlist ?? '[]'), true) ?: [],
        ];
    }

    public function updateSecurity(array $payload): object
    {
        $shopId = $this->scope->shopId();

        return DB::transaction(function () use ($shopId, $payload): object {
            $allowlist = [];

            foreach (preg_split('/[\s,;]+/', (string) ($payload['ip_allowlist'] ?? '')) ?: [] as $ip) {
                $ip = trim($ip);

                if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                    $allowlist[] = $ip;
                }
            }

            $existing = DB::table('shop_security_settings')->where('shop_id', $shopId)->first();

            DB::table('shop_security_settings')->updateOrInsert(
                ['shop_id' => $shopId],
                [
                    'two_factor_enabled' => filter_var($payload['two_factor_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'login_alert' => filter_var($payload['login_alert'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'login_alert_after' => max(1, (int) ($payload['login_alert_after'] ?? 1)),
                    'session_timeout_minutes' => max(5, (int) ($payload['session_timeout_minutes'] ?? 120)),
                    'password_rotation_enabled' => filter_var($payload['password_rotation_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'password_rotation_days' => max(1, (int) ($payload['password_rotation_days'] ?? 90)),
                    'max_failed_attempts' => max(1, (int) ($payload['max_failed_attempts'] ?? 5)),
                    'lockout_minutes' => max(1, (int) ($payload['lockout_minutes'] ?? 15)),
                    'ip_allowlist' => $allowlist === [] ? null : json_encode($allowlist),
                    'last_password_change_at' => isset($payload['password'])
                        ? now()
                        : ($existing->last_password_change_at ?? null),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            app(AuditLogger::class)->log('vendor.security.updated', 'shop_security_settings', [], [
                'shop_id' => $shopId,
                'two_factor_enabled' => filter_var($payload['two_factor_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ], $this->scope->userId());

            return DB::table('shop_security_settings')->where('shop_id', $shopId)->first();
        }, 3);
    }

    public function publicShop(): Shop
    {
        $shop = $this->scope->shop();

        $shop->setAttribute('bank_account_number', VendorScope::maskAccount($shop->bank_account_number));

        return $shop;
    }

    public function maskedAccount(): ?string
    {
        return VendorScope::maskAccount($this->scope->shop()->bank_account_number);
    }
}
