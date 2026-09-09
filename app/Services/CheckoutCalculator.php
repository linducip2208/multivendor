<?php

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
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Calculates checkout data exclusively from locked database records. */
class CheckoutCalculator
{
    public function __construct(private readonly ShippingService $shipping) {}

    /**
     * @return array{shops: Collection, subtotal: float, tax: float, shipping: float, discount: float, grand_total: float, coupon: ?Coupon}
     */
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

            $price = $variant?->getEffectivePrice() ?? $product->getEffectivePrice();
            $taxRate = $this->taxRateFor($product);
            $lines->push((object) [
                'cart' => $cartItem, 'product' => $product, 'variant' => $variant,
                'quantity' => (int) $cartItem->quantity, 'price' => $price,
                'line_total' => $price * $cartItem->quantity, 'tax_rate' => $taxRate,
            ]);
        }

        $coupon = $couponCode ? Coupon::with(['products:id', 'categories:id'])->where('code', strtoupper(trim($couponCode)))->lockForUpdate()->first() : null;
        if ($coupon && ! $coupon->isValid($customer->id)) {
            throw ValidationException::withMessages(['coupon_code' => 'Kupon tidak aktif, kedaluwarsa, atau kuotanya habis.']);
        }

        $shops = $lines->groupBy(fn ($line) => $line->product->shop_id)->map(function (Collection $shopLines, $shopId) use ($shippingSelections, $coupon) {
            $shop = $shopLines->first()->product->shop;
            $subtotal = (float) $shopLines->sum('line_total');
            $tax = (float) $shopLines->sum(fn ($line) => $line->line_total * ($line->tax_rate / 100));
            $shipping = $this->shippingFor($shop, $shopLines, $shippingSelections[$shopId] ?? []);
            $eligibleSubtotal = $this->eligibleSubtotal($coupon, $shopLines, $shop);

            return (object) compact('shop', 'shopLines', 'subtotal', 'tax', 'shipping', 'eligibleSubtotal');
        });

        $this->allocateCoupon($coupon, $shops);

        return [
            'shops' => $shops,
            'subtotal' => (float) $shops->sum('subtotal'),
            'tax' => (float) $shops->sum('tax'),
            'shipping' => (float) $shops->sum('shipping'),
            'discount' => (float) $shops->sum('couponDiscount'),
            'grand_total' => max(0, (float) $shops->sum(fn ($shop) => $shop->subtotal + $shop->tax + $shop->shipping - $shop->couponDiscount)),
            'coupon' => $coupon,
        ];
    }

    private function assertSaleable(Product $product, int $quantity): void
    {
        if ($product->status !== 'approved' || ! $product->published || $product->shop->status !== 'active') {
            throw ValidationException::withMessages(['cart' => "Produk {$product->name} tidak tersedia."]);
        }
        if ($product->shop->vacation_mode) {
            throw ValidationException::withMessages(['cart' => "Toko {$product->shop->name} sedang libur: {$product->shop->vacation_message}"]);
        }
        if ($quantity < (int) $product->min_qty || ($product->max_qty && $quantity > (int) $product->max_qty)) {
            throw ValidationException::withMessages(['cart' => "Kuantitas {$product->name} harus antara {$product->min_qty} dan {$product->max_qty}."]);
        }
        // A selected variant is the stock authority. Parent stock applies when no variant is selected.
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

    private function shippingFor(Shop $shop, Collection $lines, array $selection): float
    {
        if ($lines->every(fn ($line) => $line->product->product_type === 'digital')) {
            return 0;
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

        return (float) $rate['cost'];
    }

    private function eligibleSubtotal(?Coupon $coupon, Collection $lines, Shop $shop): float
    {
        if (! $coupon || ($coupon->shop_id && (int) $coupon->shop_id !== (int) $shop->id)) {
            return 0;
        }

        return (float) $lines->filter(function ($line) use ($coupon) {
            if (! $coupon) {
                return false;
            }
            $productAllowed = ! $coupon->products->isNotEmpty() || $coupon->products->contains('id', $line->product->id);
            $categoryAllowed = ! $coupon->categories->isNotEmpty() || $coupon->categories->contains('id', $line->product->category_id);

            return $productAllowed && $categoryAllowed;
        })->sum('line_total');
    }

    private function allocateCoupon(?Coupon $coupon, Collection $shops): void
    {
        foreach ($shops as $shop) {
            $shop->couponDiscount = 0.0;
        }
        if (! $coupon) {
            return;
        }
        $eligible = (float) $shops->sum('eligibleSubtotal');
        if ($eligible < (float) $coupon->min_purchase) {
            return;
        }

        if ($coupon->coupon_type === 'free_shipping') {
            foreach ($shops->filter(fn ($shop) => $shop->eligibleSubtotal > 0) as $shop) {
                $shop->couponDiscount = min($shop->shipping, $shop->shipping);
            }

            return;
        }
        $discount = $coupon->calculateDiscount($eligible);
        foreach ($shops->filter(fn ($shop) => $shop->eligibleSubtotal > 0) as $shop) {
            $shop->couponDiscount = round($discount * ($shop->eligibleSubtotal / $eligible), 2);
        }
        $last = $shops->filter(fn ($shop) => $shop->eligibleSubtotal > 0)->last();
        if ($last) {
            $last->couponDiscount += round($discount - $shops->sum('couponDiscount'), 2);
        }
    }
}
