<?php

declare(strict_types=1);

namespace App\Services\B2b;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

/**
 * Guard MOQ untuk keranjang B2B.
 *
 * Berbasis kolom existing `products.min_qty`/`max_qty` + tier grosir:
 * - qty < min_qty existing → selalu ditolak (aturan existing dipertahankan);
 * - produk bertier: qty di bawah tier terkecil → ditolak dengan pesan
 *   yang menyarankan tier termurah (copy BI);
 * - max_qty existing tetap dihormati; stok tetap dihormati.
 *
 * Guard ini berdiri sendiri (tidak menyentuh CheckoutCalculator /
 * CartController) — integrator memanggilnya sebelum checkout.
 */
final class B2bCartGuard
{
    public function __construct(private readonly B2bPricingService $pricing) {}

    /**
     * @param  list<array{product: Product, quantity: int}>  $lines
     * @return list<array{product: Product, quantity: int, unit_price: float, subtotal: float}>
     */
    public function validateLines(array $lines): array
    {
        $hasil = [];
        foreach ($lines as $i => $line) {
            $hasil[] = $this->validateLine($line['product'], (int) ($line['quantity'] ?? 0), "cart.{$i}");
        }

        return $hasil;
    }

    /** @return array{product: Product, quantity: int, unit_price: float, subtotal: float} */
    public function validateLine(Product $product, int $qty, string $key = 'cart'): array
    {
        $min = max(1, (int) ($product->min_qty ?? 1));
        $max = (int) ($product->max_qty ?? 0);

        if ($qty < $min) {
            throw ValidationException::withMessages([
                $key => "Jumlah {$product->name} minimal {$min} {$product->unit}.",
            ]);
        }

        if ($max > 0 && $qty > $max) {
            throw ValidationException::withMessages([
                $key => "Jumlah {$product->name} maksimal {$max} {$product->unit}.",
            ]);
        }

        // Tier = diskon volume, BUKAN blokir ritel: qty di bawah tier
        // terkecil tetap boleh checkout dengan harga ecer efektif.
        // (unitPriceFor menangani fallback-nya.)

        if ((int) ($product->current_stock ?? 0) < $qty) {
            throw ValidationException::withMessages([
                $key => "Stok {$product->name} tidak mencukupi (tersisa {$product->current_stock}).",
            ]);
        }

        $unit = $this->pricing->unitPriceFor($product, $qty);

        return [
            'product' => $product,
            'quantity' => $qty,
            'unit_price' => $unit,
            'subtotal' => round($unit * $qty, 2),
        ];
    }

    /** MOQ efektif: tier terkecil bila ada, selain itu min_qty existing. */
    public function effectiveMoq(Product $product): int
    {
        $tiers = $this->pricing->tiersFor($product);

        return $tiers->isNotEmpty()
            ? max((int) $tiers->min('min_qty'), 1)
            : max(1, (int) ($product->min_qty ?? 1));
    }
}
