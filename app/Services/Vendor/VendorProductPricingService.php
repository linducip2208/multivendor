<?php

declare(strict_types=1);

namespace App\Services\Vendor;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

/**
 * Catalogue pricing operations that touch many rows at once.
 *
 * A bulk reprice is a money mutation, so every affected product is locked,
 * validated against its own price and written with a stock movement style
 * audit row. A run that cannot be completed in full is rolled back entirely.
 */
final class VendorProductPricingService
{
    public function __construct(private readonly VendorScope $scope) {}

    /**
     * @param  list<int>  $productIds
     * @return array{updated: int, before: \App\Support\Money, after: \App\Support\Money, changes: list<array<string, mixed>>}
     */
    public function bulkReprice(array $productIds, string $mode, float|int|string $value, array $scope = []): array
    {
        $ids = array_values(array_unique(array_map('intval', $productIds)));

        if ($ids === []) {
            throw ValidationException::withMessages(['products' => 'Pilih minimal satu produk.']);
        }

        if (! in_array($mode, ['increase', 'decrease', 'set', 'margin'], true)) {
            throw ValidationException::withMessages(['mode' => 'Metode harga tidak dikenali.']);
        }

        $rate = Money::of($value);

        if ($mode !== 'set' && ! $rate->isPositive()) {
            throw ValidationException::withMessages(['value' => 'Nilai perubahan harus lebih besar dari nol.']);
        }

        return DB::transaction(function () use ($ids, $mode, $rate, $scope): array {
            $products = Product::query()
                ->where('shop_id', $this->scope->shopId())
                ->whereIn('id', $ids)
                ->when($scope['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->lockForUpdate()
                ->get();

            if ($products->isEmpty()) {
                throw ValidationException::withMessages(['products' => 'Produk tidak ditemukan pada toko Anda.']);
            }

            $changes = [];
            $before = Money::zero();
            $after = Money::zero();

            foreach ($products as $product) {
                $current = Money::of($product->effective_price)->maxZero();
                $next = $this->apply($mode, $current, $rate);

                if ($next->compare($current) < 0) {
                    throw ValidationException::withMessages([
                        'products' => 'Harga '.$product->name.' tidak boleh lebih rendah dari harga saat ini.',
                    ]);
                }

                $before = $before->add($current);
                $after = $after->add($next);

                $product->forceFill([
                    'price' => $next->toDecimal(),
                    'special_price' => null,
                ])->save();

                $changes[] = [
                    'id' => (int) $product->getKey(),
                    'name' => (string) $product->name,
                    'before' => $current,
                    'after' => $next,
                ];
            }

            app(\App\Services\AuditLogger::class)->log('vendor.products.repriced', null, [], [
                'count' => count($changes),
                'mode' => $mode,
                'value' => $rate->toDecimal(),
                'before' => $before->toDecimal(),
                'after' => $after->toDecimal(),
            ], $this->scope->userId());

            return [
                'updated' => count($changes),
                'before' => $before,
                'after' => $after,
                'changes' => $changes,
            ];
        }, 3);
    }

    private function apply(string $mode, Money $current, Money $rate): Money
    {
        return match ($mode) {
            'increase' => $current->add($rate),
            'decrease' => $current->subtract($rate)->maxZero(),
            'margin' => $this->fromMargin($current, $rate),
            default => $rate,
        };
    }

    private function fromMargin(Money $cost, Money $marginPercent): Money
    {
        $rate = $marginPercent->toFloat();

        if ($rate >= 100.0) {
            throw ValidationException::withMessages(['value' => 'Margin harus di bawah 100%.']);
        }

        return Money::of($cost->toFloat() / (1 - ($rate / 100)));
    }

    /**
     * Tarif komisi bertingkat per kategori.
     * Sumber: system_settings `commission_category_rates` (JSON {category_id: persen}).
     * Fallback ke komisi toko bila kategori tidak punya tarif khusus.
     *
     * @return array{default_type: string, default_value: float, rates: array<int, float>}
     */
    public function categoryRates(): array
    {
        $shop = $this->scope->shop();
        $rates = [];
        try {
            $raw = \App\Models\SystemSetting::get('commission_category_rates', '');
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            if (is_array($decoded)) {
                foreach ($decoded as $catId => $pct) {
                    $rates[(int) $catId] = max(0.0, min(100.0, (float) $pct));
                }
            }
        } catch (\Throwable) {
            $rates = [];
        }

        return [
            'default_type' => (string) ($shop->commission_type ?? 'percentage'),
            'default_value' => (float) ($shop->commission_value ?? 0),
            'rates' => $rates,
        ];
    }

    /**
     * Pratinjau hitungan komisi untuk satu harga + kategori.
     *
     * @return array{price: Money, rate: float, commission: Money, net: Money, source: string}
     */
    public function previewCommission(float $price, ?int $categoryId = null): array
    {
        $tiers = $this->categoryRates();
        $rate = $categoryId !== null && isset($tiers['rates'][$categoryId])
            ? $tiers['rates'][$categoryId]
            : (strtolower($tiers['default_type']) === 'fixed' ? -1.0 : (float) $tiers['default_value']);
        $priceMoney = Money::of($price)->maxZero();
        if ($rate < 0) {
            $commission = Money::of((float) $tiers['default_value']);
            $source = 'Tetap per transaksi (toko)';
        } else {
            $commission = Money::of($priceMoney->toFloat() * ($rate / 100));
            $source = $categoryId !== null && isset($tiers['rates'][$categoryId])
                ? 'Bertingkat kategori #'.$categoryId
                : 'Default toko';
        }

        return [
            'price' => $priceMoney,
            'rate' => $rate < 0 ? (float) $tiers['default_value'] : $rate,
            'commission' => $commission,
            'net' => $priceMoney->subtract($commission)->maxZero(),
            'source' => $source,
        ];
    }
}
