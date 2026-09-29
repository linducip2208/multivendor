<?php

declare(strict_types=1);

namespace App\Services\B2b;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

/**
 * Penawaran (quote) grosir — integrator yang wiring ke checkout.
 *
 * Alur yang disarankan integrator (tanpa mengubah CheckoutCalculator):
 *
 *   $quote = app(B2bQuoteService::class)->build([
 *       ['product' => $produkA, 'quantity' => 100],
 *       ['product' => $produkB, 'quantity' => 50],
 *   ]);
 *   // $quote['lines'] → umpan ke keranjang/checkout existing sebagai
 *   // cart lines biasa; harga satuan sudah harga tier.
 *   $checkoutLines = app(B2bQuoteService::class)->toCheckoutLines($quote);
 *
 * Murni kalkulasi + guard MOQ; tidak membuat order/produk apa pun.
 */
final class B2bQuoteService
{
    public function __construct(
        private readonly B2bPricingService $pricing,
        private readonly B2bCartGuard $guard,
    ) {}

    /**
     * @param  list<array{product: Product, quantity: int}>  $lines
     * @return array{lines: list<array{product_id: int, name: string, unit: ?string, quantity: int, unit_price: float, base_price: float, subtotal: float, savings: float}>, total_qty: int, subtotal: float, total_savings: float}
     */
    public function build(array $lines): array
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['cart' => 'Keranjang grosir masih kosong.']);
        }

        $divalidasi = $this->guard->validateLines($lines);

        $baris = [];
        foreach ($divalidasi as $row) {
            /** @var Product $produk */
            $produk = $row['product'];

            try {
                $ecer = (float) $produk->getEffectivePrice();
            } catch (\Throwable) {
                $ecer = (float) ($produk->price ?? 0);
            }

            $hemat = max(0.0, round(($ecer - $row['unit_price']) * $row['quantity'], 2));

            $baris[] = [
                'product_id' => (int) $produk->getKey(),
                'name' => (string) $produk->name,
                'unit' => $produk->unit,
                'quantity' => $row['quantity'],
                'unit_price' => $row['unit_price'],
                'base_price' => $ecer,
                'subtotal' => $row['subtotal'],
                'savings' => $hemat,
            ];
        }

        return [
            'lines' => $baris,
            'total_qty' => array_sum(array_column($baris, 'quantity')),
            'subtotal' => round(array_sum(array_column($baris, 'subtotal')), 2),
            'total_savings' => round(array_sum(array_column($baris, 'savings')), 2),
        ];
    }

    /**
     * Wiring ke checkout existing: kembalikan cart lines generik
     * (product_id, quantity, unit_price) yang bisa diumpan ke alur
     * keranjang/checkout tanpa perubahan logika di sisi sana.
     *
     * @param  array{lines: list<array<string, mixed>>}  $quote
     * @return list<array{product_id: int, quantity: int, unit_price: float}>
     */
    public function toCheckoutLines(array $quote): array
    {
        return array_map(fn (array $l) => [
            'product_id' => (int) $l['product_id'],
            'quantity' => (int) $l['quantity'],
            'unit_price' => (float) $l['unit_price'],
        ], $quote['lines'] ?? []);
    }
}
