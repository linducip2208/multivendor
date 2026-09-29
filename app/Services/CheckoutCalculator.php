<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Provider;
use App\Models\Shop;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\VatTax;
use App\Services\Shipping\ShippingService;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/** Calculates checkout data exclusively from locked database records. */
class CheckoutCalculator
{
    private const RATE_CACHE_TTL = 300;

    public function __construct(private readonly ShippingService $shipping) {}

    public function calculate(User $customer, Collection $cartItems, array $shippingSelections, ?string $couponCode, array $address): array
    {
        $lines = collect();
        foreach ($cartItems as $cartItem) {
            $product = Product::with('shop')->lockForUpdate()->findOrFail($cartItem->product_id);
            $this->assertSaleable($product, (int) $cartItem->quantity);

            $variant = null;
            if ($cartItem->product_variant_id) {
                $variant = ProductVariant::lockForUpdate()->findOrFail($cartItem->product_variant_id);
                if ($variant->product_id !== $product->id) {
                    throw ValidationException::withMessages(['cart' => 'Varian produk tidak valid.']);
                }
                if ((int) $variant->stock < (int) $cartItem->quantity) {
                    throw ValidationException::withMessages(['cart' => "Stok varian {$product->name} tidak mencukupi."]);
                }
            }

            $price = Money::of($variant?->getEffectivePrice() ?? $product->getEffectivePrice());
            $taxRate = $this->taxRateFor($product);

            $lines->push((object) [
                'cart' => $cartItem, 'product' => $product, 'variant' => $variant,
                'quantity' => (int) $cartItem->quantity, 'price' => $price,
                'line_total' => $price->multiply((int) $cartItem->quantity), 'tax_rate' => $taxRate,
            ]);
        }

        $coupon = $couponCode ? Coupon::with(['products:id', 'categories:id'])->where('code', strtoupper(trim($couponCode)))->lockForUpdate()->first() : null;
        if ($coupon && ! $coupon->isValid($customer->id)) {
            throw ValidationException::withMessages(['coupon_code' => 'Kupon tidak aktif, kedaluwarsa, atau kuotanya habis.']);
        }

        $shops = $lines->groupBy(fn ($line) => $line->product->shop_id)->map(function (Collection $shopLines, $shopId) use ($shippingSelections, $coupon) {
            $shop = $shopLines->first()->product->shop;
            $subtotal = Money::sum(array_map(fn ($line) => $line->line_total, $shopLines->all()));
            $tax = Money::sum(array_map(
                fn ($line) => $line->line_total->multiply($line->tax_rate / 100),
                $shopLines->all(),
            ));
            $shipping = $this->shippingFor($shop, $shopLines, $shippingSelections[$shopId] ?? []);
            $eligibleSubtotal = $this->eligibleSubtotal($coupon, $shopLines, $shop);

            return (object) [
                'shop' => $shop,
                'shopLines' => $shopLines,
                'subtotal' => $subtotal->toFloat(),
                'tax' => $tax->toFloat(),
                'shipping' => $shipping->toFloat(),
                'eligibleSubtotal' => $eligibleSubtotal->toFloat(),
            ];
        });

        $this->allocateCoupon($coupon, $shops);

        $grandTotal = Money::zero();

        foreach ($shops as $shop) {
            $shop->total = Money::of($shop->subtotal)
                ->add($shop->tax)
                ->add($shop->shipping)
                ->subtract($shop->couponDiscount)
                ->maxZero()
                ->toFloat();

            $grandTotal = $grandTotal->add($shop->total);
        }

        return [
            'shops' => $shops,
            'subtotal' => (float) $shops->sum('subtotal'),
            'tax' => (float) $shops->sum('tax'),
            'shipping' => (float) $shops->sum('shipping'),
            'discount' => (float) $shops->sum('couponDiscount'),
            'grand_total' => $grandTotal->toFloat(),
            'coupon' => $coupon,
        ];
    }

    private function assertSaleable(Product $product, int $quantity): void
    {
        if ($product->status !== 'approved' || ! $product->published || $product->shop === null || $product->shop->status !== 'active') {
            throw ValidationException::withMessages(['cart' => "Produk {$product->name} tidak tersedia."]);
        }
        if ($product->shop->vacation_mode) {
            throw ValidationException::withMessages(['cart' => "Toko {$product->shop->name} sedang libur: {$product->shop->vacation_message}"]);
        }
        if ($quantity < (int) $product->min_qty || ($product->max_qty && $quantity > (int) $product->max_qty)) {
            throw ValidationException::withMessages(['cart' => "Kuantitas {$product->name} harus antara {$product->min_qty} dan {$product->max_qty}."]);
        }
        if ((int) $product->current_stock < $quantity) {
            throw ValidationException::withMessages(['cart' => "Stok {$product->name} tidak mencukupi."]);
        }
    }

    private function taxRateFor(Product $product): float
    {
        if (! $product->vat_tax_id) {
            return (float) $product->tax;
        }

        return (float) VatTax::whereKey($product->vat_tax_id)->where('is_active', true)->value('rate');
    }

    private function shippingFor(Shop $shop, Collection $lines, array $selection): Money
    {
        if ($lines->every(fn ($line) => $line->product->product_type === 'digital')) {
            return Money::zero();
        }

        $provider = Provider::ofType('shipping')->active()->find($selection['provider_id'] ?? null);
        if (! $provider || empty($selection['courier']) || empty($selection['service']) || empty($selection['destination'])) {
            throw ValidationException::withMessages(["shipping_methods.{$shop->id}" => "Pilih layanan pengiriman yang valid untuk {$shop->name}."]);
        }

        $origin = SystemSetting::get("shop_shipping_origin_{$shop->id}") ?: SystemSetting::get('shipping_origin');
        if (! $origin) {
            throw ValidationException::withMessages(["shipping_methods.{$shop->id}" => "Asal pengiriman toko {$shop->name} belum dikonfigurasi."]);
        }

        $weight = max(1, $lines->sum(fn ($line) => max(1, (int) $line->product->weight) * $line->quantity));
        $cost = $this->cachedRate($provider, $shop, (string) $origin, $selection, $weight);

        if ($cost === null) {
            throw ValidationException::withMessages(["shipping_methods.{$shop->id}" => 'Layanan pengiriman tidak valid.']);
        }

        return Money::of($cost);
    }

    private function cachedRate(Provider $provider, Shop $shop, string $origin, array $selection, int $weight): ?float
    {
        $key = 'checkout:rate:'.sha1(implode('|', [
            $provider->id,
            $shop->id,
            $origin,
            (string) $selection['destination'],
            (string) $selection['courier'],
            (string) $selection['service'],
            $weight,
            $provider->api_format,
        ]));

        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached['cost'] ?? null;
        }

        $rates = $this->shipping->getShippingRates($provider, [
            'origin' => $origin, 'destination' => $selection['destination'], 'weight' => $weight, 'courier' => $selection['courier'],
        ]);

        if (! ($rates['success'] ?? false)) {
            throw ValidationException::withMessages(["shipping_methods.{$shop->id}" => 'Tarif pengiriman tidak dapat diverifikasi.']);
        }

        $rate = collect($rates['rates'] ?? [])->first(fn ($rate) => strcasecmp((string) ($rate['courier'] ?? ''), (string) $selection['courier']) === 0
            && strcasecmp((string) ($rate['service'] ?? ''), (string) $selection['service']) === 0
        );

        if (! $rate || ! is_numeric($rate['cost'] ?? null) || $rate['cost'] < 0) {
            throw ValidationException::withMessages(["shipping_methods.{$shop->id}" => 'Layanan pengiriman tidak valid.']);
        }

        $cost = (float) $rate['cost'];

        Cache::put($key, ['cost' => $cost], self::RATE_CACHE_TTL);

        return $cost;
    }

    private function eligibleSubtotal(?Coupon $coupon, Collection $lines, Shop $shop): Money
    {
        if (! $coupon || ($coupon->shop_id && (int) $coupon->shop_id !== (int) $shop->id)) {
            return Money::zero();
        }

        $eligible = $lines->filter(function ($line) use ($coupon) {
            $productAllowed = $coupon->products->isEmpty() || $coupon->products->contains('id', $line->product->id);
            $categoryAllowed = $coupon->categories->isEmpty() || $coupon->categories->contains('id', $line->product->category_id);

            return $productAllowed && $categoryAllowed;
        });

        return Money::sum(array_map(fn ($line) => $line->line_total, $eligible->all()));
    }

    private function allocateCoupon(?Coupon $coupon, Collection $shops): void
    {
        foreach ($shops as $shop) {
            $shop->couponDiscount = 0.0;
        }

        if (! $coupon) {
            return;
        }

        $eligible = Money::sum(array_map(
            static fn ($shop) => Money::of($shop->eligibleSubtotal),
            $shops->all(),
        ));

        if (! $eligible->isPositive() || $eligible->compare($coupon->min_purchase) < 0) {
            return;
        }

        $targets = $shops->filter(fn ($shop) => Money::of($shop->eligibleSubtotal)->isPositive())->values();

        if ($targets->isEmpty()) {
            return;
        }

        $total = Money::sum(array_map(
            static fn ($shop) => Money::of($shop->subtotal)
                ->add($shop->tax)
                ->add($shop->shipping),
            $targets->all(),
        ));

        $discount = $coupon->coupon_type === 'free_shipping'
            ? $this->freeShippingDiscount($coupon, $targets, $total)
            : Money::of($coupon->calculateDiscount($eligible->toFloat()));

        $discount = $this->capToTotal($discount, $total, $eligible, $targets);

        $this->distribute($targets, $eligible, $discount);

        if ($coupon->coupon_type === 'free_shipping') {
            $this->reconcileShipping($targets, $discount);
        }
    }

    private function freeShippingDiscount(Coupon $coupon, Collection $targets, Money $total): Money
    {
        $value = Money::of($coupon->discount_value);

        if (! $value->isPositive()) {
            $value = Money::sum(array_map(
                static fn ($shop) => Money::of($shop->shipping),
                $targets->all(),
            ));
        }

        return $value->min($total);
    }

    private function capToTotal(Money $discount, Money $total, Money $eligible, Collection $targets): Money
    {
        $discount = $discount->min($total)->maxZero();

        if ($discount->compare($eligible) > 0) {
            $discount = Money::of($eligible->toFloat())->min($total)->maxZero();
        }

        return $discount;
    }

    private function distribute(Collection $targets, Money $eligible, Money $discount): void
    {
        if (! $discount->isPositive()) {
            return;
        }

        $allocated = Money::zero();

        foreach ($targets as $index => $shop) {
            $share = $index === $targets->count() - 1
                ? $discount->subtract($allocated)
                : $discount->multiply(Money::of($shop->eligibleSubtotal)->minor / max(1, $eligible->minor));

            $shop->couponDiscount = $share->toFloat();
            $allocated = $allocated->add($share);
        }
    }

    private function reconcileShipping(Collection $targets, Money $discount): void
    {
        $remaining = $discount;
        $applied = Money::zero();

        foreach ($targets as $shop) {
            $share = Money::of($shop->couponDiscount);
            $covered = $share->min(Money::of($shop->shipping));

            if ($covered->isZero()) {
                $shop->couponDiscount = 0.0;

                continue;
            }

            $allowance = $remaining->subtract($applied);

            if ($allowance->compare($covered) < 0) {
                $covered = $covered->min($allowance);
            }

            $shop->couponDiscount = $covered->toFloat();
            $applied = $applied->add($covered);
        }
    }
}
