<?php

declare(strict_types=1);

namespace App\Services\Vendor;

use App\Models\Coupon;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Vendor promotions.
 *
 * A shop owns its coupons and its campaigns. Every write is scoped by `shop_id`
 * in the WHERE clause rather than by an in-memory check, so a crafted id can
 * never reach another tenant's row.
 */
final class VendorPromotionService
{
    public const COUPON_TYPES = [
        'percentage' => 'Persentase',
        'fixed' => 'Nominal tetap',
        'free_shipping' => 'Gratis ongkir',
    ];

    public const CAMPAIGN_TYPES = [
        'promotion' => 'Promo',
        'flash_sale' => 'Flash sale',
        'bundle' => 'Bundel',
    ];

    public function __construct(private readonly VendorScope $scope) {}

    public function index(string $type = 'coupon', string $search = ''): array
    {
        return [
            'type' => in_array($type, ['coupon', 'campaign'], true) ? $type : 'coupon',
            'search' => $search,
            'coupons' => $type === 'coupon' ? $this->coupons($search) : collect(),
            'campaigns' => $type === 'campaign' ? $this->campaigns($search) : collect(),
            'stats' => $this->stats(),
            'types' => self::COUPON_TYPES,
            'campaign_types' => self::CAMPAIGN_TYPES,
        ];
    }

    public function storeCoupon(array $payload): Coupon
    {
        $shopId = $this->scope->shopId();
        $code = strtoupper(trim((string) $payload['code']));

        return DB::transaction(function () use ($shopId, $code, $payload): Coupon {
            if (Coupon::query()->where('code', $code)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['code' => 'Kode promo sudah digunakan.']);
            }

            $coupon = Coupon::query()->create([
                'shop_id' => $shopId,
                'code' => $code,
                'title' => VendorScope::cleanNullable($payload['title'] ?? $code, 160),
                'coupon_type' => in_array($payload['coupon_type'] ?? 'percentage', array_keys(self::COUPON_TYPES), true)
                    ? $payload['coupon_type']
                    : 'percentage',
                'discount_value' => Money::of($payload['discount_value'] ?? 0)->maxZero()->toDecimal(),
                'min_purchase' => Money::of($payload['min_purchase'] ?? 0)->maxZero()->toDecimal(),
                'max_discount' => empty($payload['max_discount']) ? null : Money::of($payload['max_discount'])->toDecimal(),
                'start_date' => $payload['start_date'] ?? null,
                'end_date' => $payload['end_date'] ?? null,
                'usage_limit' => ! empty($payload['usage_limit']) ? (int) $payload['usage_limit'] : null,
                'usage_per_customer' => max(1, (int) ($payload['usage_per_customer'] ?? 1)),
                'status' => filter_var($payload['status'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ]);

            $this->syncProducts($coupon, $payload['products'] ?? []);

            app(AuditLogger::class)->log('vendor.coupon.created', $coupon, [], [
                'code' => $coupon->code,
                'type' => $coupon->coupon_type,
            ], $this->scope->userId());

            return $coupon->refresh();
        }, 3);
    }

    public function destroyCoupon(int $couponId): void
    {
        $coupon = Coupon::query()
            ->where('shop_id', $this->scope->shopId())
            ->whereKey($couponId)
            ->first();

        abort_if($coupon === null, 404);

        DB::transaction(function () use ($coupon): void {
            $coupon->delete();
            app(AuditLogger::class)->log('vendor.coupon.deleted', 'coupon', ['id' => (int) $coupon->getKey()], [], $this->scope->userId());
        }, 3);
    }

    public function storeCampaign(array $payload): int
    {
        $shopId = $this->scope->shopId();
        $name = VendorScope::clean($payload['name'], 160);

        return DB::transaction(function () use ($shopId, $name, $payload): int {
            $slug = $this->uniqueSlug($name);

            $id = DB::table('campaigns')->insertGetId([
                'name' => $name,
                'slug' => $slug,
                'code' => VendorScope::cleanNullable($payload['code'] ?? null, 60),
                'shop_id' => $shopId,
                'type' => in_array($payload['type'] ?? 'promotion', array_keys(self::CAMPAIGN_TYPES), true)
                    ? $payload['type']
                    : 'promotion',
                'description' => VendorScope::cleanNullable($payload['description'] ?? null, 1000),
                'rules' => json_encode(['type' => $payload['type'] ?? 'promotion', 'audience' => $payload['audience'] ?? null]),
                'budget' => empty($payload['budget']) ? null : Money::of($payload['budget'])->toDecimal(),
                'discount_value' => Money::of($payload['discount_value'] ?? 0)->maxZero()->toDecimal(),
                'discount_type' => in_array($payload['discount_type'] ?? 'percentage', ['percentage', 'fixed'], true)
                    ? $payload['discount_type']
                    : 'percentage',
                'status' => in_array($payload['status'] ?? 'draft', ['draft', 'scheduled', 'active', 'paused', 'ended'], true)
                    ? $payload['status']
                    : 'draft',
                'starts_at' => $payload['starts_at'] ?? null,
                'ends_at' => $payload['ends_at'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $products = array_values(array_filter(array_map('intval', (array) ($payload['products'] ?? []))));

            if ($products !== []) {
                DB::table('campaign_products')->insert(
                    array_map(fn (int $productId): array => [
                        'campaign_id' => $id,
                        'product_id' => $productId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ], $products)
                );
            }

            app(AuditLogger::class)->log('vendor.campaign.created', 'campaign', [], [
                'id' => $id,
                'name' => $name,
            ], $this->scope->userId());

            return $id;
        }, 3);
    }

    public function destroyCampaign(int $campaignId): void
    {
        $exists = DB::table('campaigns')
            ->where('shop_id', $this->scope->shopId())
            ->where('id', $campaignId)
            ->exists();

        abort_if(! $exists, 404);

        DB::transaction(function () use ($campaignId): void {
            DB::table('campaign_products')->where('campaign_id', $campaignId)->delete();
            DB::table('campaigns')->where('id', $campaignId)->delete();
        }, 3);
    }

    public function coupons(string $search = '')
    {
        return Coupon::query()
            ->where('shop_id', $this->scope->shopId())
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('code', 'like', '%'.$search.'%')
                ->orWhere('title', 'like', '%'.$search.'%')))
            ->orderByDesc('created_at')
            ->get();
    }

    public function campaigns(string $search = '')
    {
        return DB::table('campaigns')
            ->where('shop_id', $this->scope->shopId())
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->orderByDesc('created_at')
            ->get();
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        $shopId = $this->scope->shopId();

        return [
            'coupons' => (int) Coupon::query()->where('shop_id', $shopId)->count(),
            'active_coupons' => (int) Coupon::query()->where('shop_id', $shopId)->where('status', true)->count(),
            'campaigns' => (int) DB::table('campaigns')->where('shop_id', $shopId)->count(),
            'active_campaigns' => (int) DB::table('campaigns')->where('shop_id', $shopId)->where('status', 'active')->count(),
        ];
    }

    /** @param  array<int, int|string>  $productIds */
    private function syncProducts(Coupon $coupon, array $productIds): void
    {
        $shopProducts = \App\Models\Product::query()
            ->where('shop_id', $this->scope->shopId())
            ->pluck('id')
            ->map('intval')
            ->all();

        $ids = array_values(array_intersect(array_map('intval', $productIds), $shopProducts));

        DB::table('coupon_product')->where('coupon_id', $coupon->getKey())->delete();

        if ($ids === []) {
            return;
        }

        DB::table('coupon_product')->insert(array_map(fn (int $productId): array => [
            'coupon_id' => $coupon->getKey(),
            'product_id' => $productId,
            'created_at' => now(),
            'updated_at' => now(),
        ], $ids));
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'promo';

        do {
            $slug = $base.'-'.Str::lower(Str::random(4));
        } while (DB::table('campaigns')->where('slug', $slug)->exists());

        return $slug;
    }
}
