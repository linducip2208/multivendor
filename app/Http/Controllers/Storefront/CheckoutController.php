<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CouponUsage;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentGroup;
use App\Models\Provider;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Services\CheckoutCalculator;
use App\Services\OrderService;
use App\Services\Payment\PaymentGatewayService;
use App\Services\Payment\PaymentLog;
use App\Services\Shipping\ShippingService;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CheckoutController extends Controller
{
    public function index()
    {
        $cartItems = Cart::where('customer_id', auth()->id())->with(['product.shop', 'variant'])->get()->filter(fn ($item) => $item->product !== null);
        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Keranjang kosong.');
        }

        $shops = $cartItems->groupBy(fn ($item) => $item->product->shop_id)->map(fn ($items) => [
            'shop' => $items->first()->product->shop, 'items' => $items,
            'subtotal' => $items->sum(fn ($item) => Money::of($item->product->getEffectivePrice())->multiply((int) $item->quantity)->toFloat()),
        ]);
        $total = Money::sum(array_map(static fn (array $group) => Money::of($group['subtotal']), $shops->all()))->toFloat();
        $addresses = auth()->user()->addresses;
        $paymentGateways = Provider::ofType('payment')->active()->orderBy('sort_order')->get();
        $shippingProviders = Provider::ofType('shipping')->active()->orderBy('sort_order')->get();

        return view('storefront.checkout.index', compact('shops', 'total', 'addresses', 'paymentGateways', 'shippingProviders'));
    }

    public function process(Request $request, CheckoutCalculator $calculator, PaymentGatewayService $payments)
    {
        $validated = $request->validate([
            'address_id' => 'nullable|exists:customer_addresses,id',
            'new_label' => 'nullable|string|max:100',
            'new_receiver_name' => 'nullable|required_without:address_id|string|max:255',
            'new_receiver_phone' => 'nullable|required_without:address_id|string|max:20',
            'new_address' => 'nullable|required_without:address_id|string|max:500',
            'new_city' => 'nullable|required_without:address_id|string|max:100',
            'new_province' => 'nullable|required_without:address_id|string|max:100',
            'new_postal_code' => 'nullable|string|max:12',
            'new_shipping_destination_id' => 'nullable|required_without:address_id|string|max:100',
            'shipping_methods' => 'present|array',
            'shipping_methods.*.provider_id' => 'nullable|integer|exists:providers,id',
            'shipping_methods.*.courier' => 'nullable|string|max:50',
            'shipping_methods.*.service' => 'nullable|string|max:100',
            'shipping_methods.*.destination' => 'nullable|string|max:100',
            'shipping_methods.*.insurance' => 'nullable|boolean',
            'shipping_methods.*.zone_id' => 'nullable|integer',
            'shipping_methods.*.pickup' => 'nullable|boolean',
            'shipping_methods.*.pickup_warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'shipping_methods.*.length' => 'nullable|numeric|min:0|max:500',
            'shipping_methods.*.width' => 'nullable|numeric|min:0|max:500',
            'shipping_methods.*.height' => 'nullable|numeric|min:0|max:500',
            'payment_provider_id' => 'required|integer|exists:providers,id',
            'payment_channel' => 'nullable|array',
            'idempotency_key' => 'nullable|string|max:80',
            'note' => 'nullable|string|max:2000',
            'shop_notes' => 'nullable|array',
            'shop_notes.*' => 'nullable|string|max:1000',
            'insurance' => 'nullable|boolean',
            'coupon_code' => 'nullable|string|max:50',
            'dropship_enabled' => 'nullable|boolean',
            'dropship_sender_name' => 'nullable|string|max:255',
            'dropship_sender_store' => 'nullable|string|max:255',
            'dropship_hide_price' => 'nullable|boolean',
            'gift_wrap' => 'nullable|boolean',
            'gift_message' => 'nullable|string|max:500',
            'referral_code' => 'nullable|string|max:50',
            // ADITIF slot jadwal pengiriman: pilihan hari (H s.d. H+14) + jam.
            'delivery_slot_date' => 'nullable|date|after_or_equal:today',
            'delivery_slot_time' => 'nullable|string|in:08:00-11:00,11:00-14:00,14:00-17:00,17:00-20:00',
            'delivery_slot_label' => 'nullable|string|max:120',
        ]);

        $customer = $request->user();
        $shippingMethods = $request->input('shipping_methods', []);
        if (! is_array($shippingMethods)) {
            $shippingMethods = [];
        }
        // ADITIF slot: validasi jendela H s.d. H+14 + konsistensi tanggal/jam
        // sebelum transaksi dibuka (gagal cepat, tanpa menulis apa pun).
        $deliverySlot = $this->resolveDeliverySlot($validated);
        $idempotencyKey = $this->idempotencyKey($request, $validated);
        if (isset($validated['coupon_code']) && is_string($validated['coupon_code'])) {
            $validated['coupon_code'] = strtoupper(trim($validated['coupon_code'])) ?: null;
        }

        $provider = Provider::ofType('payment')->active()->find($validated['payment_provider_id']);
        if (! $provider) {
            return back()->withInput()->with('error', 'Metode pembayaran tidak tersedia.');
        }

        if ($idempotencyKey !== null) {
            $replay = Order::where('customer_id', $customer->id)
                ->where('idempotency_key', $idempotencyKey)
                ->with('paymentGroup.provider')
                ->first();

            if ($replay) {
                // Resume pembayaran gagal tanpa order baru: jika grup masih
                // retryable, buatkan ulang transaksi gateway untuk grup yang sama.
                $resumed = $this->retryFailedGroup($replay, $provider, $payments, $customer);

                if ($resumed !== null) {
                    return $resumed;
                }

                return $this->resumeOrder($replay);
            }
        }

        try {
            $created = DB::transaction(function () use ($validated, $shippingMethods, $customer, $provider, $calculator, $idempotencyKey, $deliverySlot) {
                $address = $this->resolveAddress($validated, (int) $customer->id);
                $cartItems = Cart::where('customer_id', $customer->id)->with(['product.shop', 'variant'])->lockForUpdate()->get();

                if ($cartItems->isEmpty()) {
                    throw ValidationException::withMessages(['cart' => 'Keranjang kosong.']);
                }

                foreach ($shippingMethods as $shopId => $selection) {
                    if (! empty($selection['destination']) && (! $address->shipping_destination_id || $selection['destination'] !== $address->shipping_destination_id)) {
                        throw ValidationException::withMessages(["shipping_methods.{$shopId}.destination" => 'Tujuan pengiriman harus sesuai alamat yang dipilih.']);
                    }
                }

                $quote = $calculator->calculate($customer, $cartItems, $shippingMethods, $validated['coupon_code'] ?? null, $address->toArray(), [
                    'insurance' => (bool) ($validated['insurance'] ?? false),
                    'gift' => [
                        'wrap' => (bool) ($validated['gift_wrap'] ?? false),
                        'message' => isset($validated['gift_message']) ? (string) $validated['gift_message'] : null,
                    ],
                ]);

                // ── ADITIF pickup (click & collect): kalkulasi existing di atas
                // tidak diubah. Untuk toko yang dipilih ambil di toko, ongkir +
                // asuransi dinolkan dari hasil quote sebelum grup pembayaran
                // dibuat — tetap di dalam satu DB::transaction yang sama.
                $pickupMap = $this->pickupSelections($shippingMethods);

                if ($pickupMap !== []) {
                    $quote = $this->applyPickupQuote($quote, $pickupMap);
                }

                $amountDueNow = (float) ($quote['amount_due_now'] ?? $quote['grand_total']);
                $hasPreorder = (bool) ($quote['preorder']['has_preorder'] ?? false);

                $group = PaymentGroup::create([
                    'payment_number' => PaymentGroup::generateNumber(), 'customer_id' => $customer->id, 'provider_id' => $provider->id,
                    'subtotal' => $quote['subtotal'], 'tax' => $quote['tax'], 'shipping_cost' => $quote['shipping'],
                    'discount' => $quote['discount'], 'grand_total' => $hasPreorder ? $amountDueNow : $quote['grand_total'], 'status' => PaymentStatus::Pending->value,
                    'expired_at' => now()->addMinutes($this->expiryMinutes()),
                ]);

                $dropshipEnabled = (bool) ($validated['dropship_enabled'] ?? false);
                $dropshipName = $dropshipEnabled && isset($validated['dropship_sender_name']) ? trim((string) $validated['dropship_sender_name']) : null;
                $dropshipStore = $dropshipEnabled && isset($validated['dropship_sender_store']) ? trim((string) $validated['dropship_sender_store']) : null;
                $dropshipHidePrice = $dropshipEnabled && ! empty($validated['dropship_hide_price']);
                $giftWrap = (bool) ($validated['gift_wrap'] ?? false);
                $giftMessage = $giftWrap && isset($validated['gift_message']) ? trim((string) $validated['gift_message']) : null;
                if ($giftMessage === '') {
                    $giftMessage = null;
                }

                $orders = collect();
                $first = true;

                foreach ($quote['shops'] as $shopQuote) {
                    $selection = $shippingMethods[$shopQuote->shop->id] ?? [];
                    // ADITIF pickup: gudang ambil untuk toko ini (null bila dikirim).
                    $pickupWarehouseId = $pickupMap[(int) $shopQuote->shop->id] ?? null;
                    $pickupWarehouse = $pickupWarehouseId !== null
                        ? $this->resolvePickupWarehouse((int) $shopQuote->shop->id, $pickupWarehouseId)
                        : null;
                    $giftFee = (float) ($shopQuote->giftFee ?? 0.0);
                    $total = Money::of($shopQuote->subtotal)
                        ->add($shopQuote->tax)
                        ->add($shopQuote->shipping)
                        ->add($giftFee)
                        ->subtract($shopQuote->couponDiscount)
                        ->maxZero();
                    $shopIsPreorder = (bool) ($shopQuote->isPreorder ?? false);
                    $shopPreorderRemaining = $shopIsPreorder ? (float) ($shopQuote->preorderRemaining ?? 0.0) : 0.0;
                    $shopPreorderDp = $shopIsPreorder ? (float) ($shopQuote->preorderDp ?? 0.0) : 0.0;

                    $shopNote = Order::formatShopNote(
                        (string) $shopQuote->shop->name,
                        isset($validated['shop_notes'][$shopQuote->shop->id]) ? (string) $validated['shop_notes'][$shopQuote->shop->id] : null,
                    );
                    $combinedNote = trim(implode("\n", array_filter([
                        $validated['note'] ?? null,
                        $shopNote,
                        // ADITIF slot: ditempel ke note agar langsung tampil di
                        // fulfillment existing tanpa mengubah view fulfillment.
                        $deliverySlot !== null ? '[Slot pengiriman: '.$deliverySlot['label'].']' : null,
                        ($shopQuote->insurance ?? 0) > 0 ? '[Asuransi pengiriman: Rp'.number_format((float) $shopQuote->insurance, 0, ',', '.').']' : null,
                    ]))) ?: null;

                    $order = Order::create([
                        'payment_group_id' => $group->id, 'order_number' => Order::generateOrderNumber(), 'customer_id' => $customer->id,
                        'shop_id' => $shopQuote->shop->id, 'coupon_code' => $quote['coupon']?->code,
                        'referral_code' => isset($validated['referral_code']) && is_string($validated['referral_code']) ? strtoupper(trim($validated['referral_code'])) ?: null : null,
                        'coupon_discount' => $shopQuote->couponDiscount, 'sub_total' => $shopQuote->subtotal, 'tax' => $shopQuote->tax,
                        'shipping_cost' => $shopQuote->shipping, 'discount' => 0, 'total' => $total->toDecimal(),
                        'idempotency_key' => $first ? $idempotencyKey : null,
                        'shipping_method' => $pickupWarehouse !== null ? 'PICKUP' : ($selection['courier'] ?? null),
                        'shipping_service' => $pickupWarehouse !== null ? 'Ambil di Toko' : ($selection['service'] ?? null),
                        'shipping_address' => $address->only(['label', 'receiver_name', 'receiver_phone', 'address', 'city', 'province', 'postal_code']),
                        'payment_method' => $provider->api_format, 'payment_status' => $shopPreorderRemaining > 0 ? PaymentStatus::Partial->value : PaymentStatus::Unpaid->value,
                        'order_status' => OrderStatus::Pending->stored(), 'note' => $combinedNote,
                        'is_dropship' => $dropshipEnabled,
                        'dropship_sender_name' => $dropshipName ?: null,
                        'dropship_sender_store' => $dropshipStore ?: null,
                        'hide_price_in_package' => $dropshipHidePrice,
                        'is_preorder' => $shopIsPreorder,
                        'preorder_eta' => $shopIsPreorder ? ($quote['preorder']['eta'] ?? null) : null,
                        'preorder_dp_amount' => $shopPreorderDp,
                        'preorder_remaining' => $shopPreorderRemaining,
                        'is_gift' => $giftWrap,
                        'gift_wrap' => $giftWrap,
                        'gift_message' => $giftMessage,
                        'gift_fee' => $giftFee,
                        // ADITIF UTM: tulis penanda kampanye dari session ke
                        // order tanpa mengubah kalkulasi apa pun.
                        ...$this->utmOrderAttributes(),
                    ]);

                    $first = false;
                    $group->orders()->attach($order->id, ['amount' => $total->toDecimal()]);

                    // ADITIF slot: kolom jadwal ditulis dalam transaksi yang
                    // sama (atomicity terjaga), dijaga hasColumn agar
                    // backward-compatible saat migrasi belum jalan.
                    if ($deliverySlot !== null) {
                        $this->persistDeliverySlot($order, $deliverySlot);
                    }

                    $order->statusHistory()->create(['status' => OrderStatus::Pending->stored(), 'changed_by' => $customer->id, 'note' => 'Pesanan dibuat.']);

                    foreach ($shopQuote->shopLines as $line) {
                        OrderItem::create([
                            'order_id' => $order->id, 'product_id' => $line->product->id, 'product_variant_id' => $line->variant?->id,
                            'quantity' => $line->quantity, 'price' => $line->price->toDecimal(),
                            'tax' => $line->line_total->multiply($line->tax_rate / 100)->toDecimal(),
                            'discount' => 0, 'sub_total' => $line->line_total->toDecimal(), 'variant_detail' => $line->variant?->variant,
                        ]);

                        if ($line->variant) {
                            $line->variant->decrement('stock', (int) $line->quantity);
                        } else {
                            $line->product->decrement('current_stock', (int) $line->quantity);
                        }
                    }

                    Transaction::create([
                        'transaction_id' => 'TRX-'.$order->order_number, 'payment_group_id' => $group->id, 'order_id' => $order->id,
                        'customer_id' => $customer->id, 'shop_id' => $order->shop_id, 'amount' => $order->total, 'admin_commission' => 0,
                        'vendor_amount' => 0, 'payment_method' => $this->transactionPaymentMethod($provider), 'status' => 'pending',
                    ]);

                    // ADITIF pickup: kiriman ambil di toko (tanpa ongkir + kode
                    // ambil) ditulis dalam transaksi yang sama — gagal di sini
                    // membatalkan seluruh checkout toko ini.
                    if ($pickupWarehouse !== null) {
                        $this->createPickupShipment($order, $pickupWarehouse);
                    }

                    $orders->push($order);
                }

                if ($quote['coupon']) {
                    $quote['coupon']->increment('usage_count');
                    CouponUsage::create([
                        'coupon_id' => $quote['coupon']->id, 'customer_id' => $customer->id,
                        'order_id' => $orders->first()->id, 'discount_amount' => $quote['discount'],
                    ]);
                }

                return ['group' => $group, 'orders' => $orders];
            }, 3);
        } catch (QueryException $e) {
            PaymentLog::channel('warning', 'Checkout lost an idempotency race', [
                'customer_id' => $customer->id,
                'exception' => $e::class,
            ]);

            $replay = $idempotencyKey === null
                ? null
                : Order::where('customer_id', $customer->id)->where('idempotency_key', $idempotencyKey)->first();

            if ($replay) {
                return $this->resumeOrder($replay);
            }

            return back()->withInput()->with('error', 'Checkout tidak dapat diproses. Silakan coba kembali.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            PaymentLog::channel('error', 'Checkout failed', [
                'customer_id' => $customer->id,
                'exception' => $e::class,
            ]);

            return back()->withInput()->with('error', 'Checkout tidak dapat diproses. Silakan coba kembali.');
        }

        $group = $created['group'];
        // $created['orders'] is a base Support collection; re-query as
        // Eloquent so items.product is eager-loaded without N+1.
        $orders = Order::query()
            ->whereIn('id', collect($created['orders'])->map(fn ($o) => $o->getKey())->all())
            ->with('items.product')
            ->get();

        $payment = $payments->createPayment($provider, [
            'order_id' => $group->payment_number, 'amount' => $group->grand_total,
            'channel' => is_array($validated['payment_channel'] ?? null) ? ($validated['payment_channel'][$provider->id] ?? 'default') : 'default',
            'customer' => ['name' => $customer->name, 'email' => $customer->email, 'phone' => $customer->phone],
            'items' => $this->paymentItems($orders), 'success_url' => route('orders.index'),
            'callback_url' => route('webhook.payment', $provider),
        ]);

        if (! ($payment['success'] ?? false)) {
            $this->abortPayment($group);

            return back()->withInput()->with('error', 'Gateway pembayaran tidak dapat membuat transaksi. Silakan pilih metode lain atau coba kembali.');
        }

        $group->forceFill([
            'gateway_reference' => $payment['transaction_id'] ?? $payment['invoice_id'] ?? $payment['reference'] ?? $group->gateway_reference,
            'gateway_response' => is_array($payment['raw'] ?? null) ? $payment['raw'] : null,
        ])->save();

        Cart::where('customer_id', $customer->id)->delete();

        return ! empty($payment['redirect_url'])
            ? redirect()->away($payment['redirect_url'])
            : redirect()->route('orders.index')->with('success', 'Pesanan dibuat. Selesaikan pembayaran Anda.');
    }

    public function shippingCost(Request $request, ShippingService $shipping)
    {
        $data = $request->validate([
            'shop_id' => 'required|integer|exists:shops,id', 'destination' => 'required|string|max:100',
            'courier' => 'required|string|max:50', 'provider_id' => 'required|integer',
            'insurance' => 'nullable|boolean', 'goods_value' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0|max:500', 'width' => 'nullable|numeric|min:0|max:500',
            'height' => 'nullable|numeric|min:0|max:500', 'zone_id' => 'nullable|integer',
            'pickup' => 'nullable|boolean',
        ]);

        // ADITIF pickup: ambil di toko selalu gratis, tanpa memanggil kurir.
        if (! empty($data['pickup'])) {
            return response()->json([
                'success' => true,
                'rates' => [[
                    'courier' => 'PICKUP', 'service' => 'Ambil di Toko',
                    'description' => 'Ambil di toko — gratis ongkir', 'cost' => 0, 'etd' => '',
                ]],
                'courier' => 'pickup',
                'tried' => [],
                'weight_actual' => 0,
                'weight_billable' => 0,
                'weight_volumetric' => 0,
                'insurance_fee' => 0.0,
            ]);
        }

        $provider = Provider::ofType('shipping')->active()->find($data['provider_id']);
        if (! $provider) {
            return response()->json(['success' => false, 'message' => 'Provider pengiriman tidak tersedia.'], 422);
        }

        $cart = Cart::where('customer_id', $request->user()->id)->whereHas('product', fn ($q) => $q->where('shop_id', $data['shop_id']))->with('product')->get();
        $origin = SystemSetting::get("shop_shipping_origin_{$data['shop_id']}") ?: SystemSetting::get('shipping_origin');
        if (! $origin || $cart->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Data pengiriman belum lengkap.'], 422);
        }

        $actual = $cart->sum(fn ($item) => max(1, (int) $item->product->weight) * $item->quantity);
        $weight = $shipping->billableWeight($actual, $data);

        $quote = $shipping->fallbackQuote($provider, [
            'origin' => $origin, 'destination' => $data['destination'], 'courier' => $data['courier'],
            'weight' => $weight,
        ]);

        if (! ($quote['success'] ?? false)) {
            $zoneCost = $shipping->zoneTableQuote(
                (float) $cart->sum(fn ($item) => Money::of($item->product->getEffectivePrice())->multiply((int) $item->quantity)->toFloat()),
                $data['zone_id'] ?? null,
            );

            if ($zoneCost === null) {
                return response()->json(['success' => false, 'message' => 'Tarif pengiriman tidak tersedia, coba kurir lain.'], 422);
            }

            $quote = ['success' => true, 'rates' => [[
                'courier' => 'ZONA', 'service' => 'Tabel zona', 'description' => 'Tarif tabel zona',
                'cost' => $zoneCost, 'etd' => '',
            ]], 'courier' => 'zona', 'tried' => []];
        }

        $goods = isset($data['goods_value'])
            ? (float) $data['goods_value']
            : (float) $cart->sum(fn ($item) => Money::of($item->product->getEffectivePrice())->multiply((int) $item->quantity)->toFloat());

        return response()->json(array_merge($quote, [
            'weight_actual' => $actual,
            'weight_billable' => $weight,
            'weight_volumetric' => $shipping->volumetricWeight(
                isset($data['length']) ? (float) $data['length'] : null,
                isset($data['width']) ? (float) $data['width'] : null,
                isset($data['height']) ? (float) $data['height'] : null,
            ),
            'insurance_fee' => ! empty($data['insurance']) ? $shipping->insuranceFee($goods) : 0.0,
        ]));
    }

    /**
     * Opsi ambil di toko per toko: shop_id => warehouse_id.
     * Validasi ringan di sini; validasi gudang penuh saat transaksi berjalan
     * agar gagal tepat sebelum tulis (atomicity terjaga).
     *
     * @return array<int, int>
     */
    private function pickupSelections(array $shippingMethods): array
    {
        $map = [];

        foreach ($shippingMethods as $shopId => $selection) {
            if (! is_array($selection) || empty($selection['pickup'])) {
                continue;
            }

            $warehouseId = (int) ($selection['pickup_warehouse_id'] ?? 0);

            if ((int) $shopId > 0 && $warehouseId > 0) {
                $map[(int) $shopId] = $warehouseId;
            }
        }

        return $map;
    }

    /**
     * Nolkan ongkir + asuransi hasil quote untuk toko pickup.
     * Kalkulasi existing (calculator) tidak diubah — hanya menyesuaikan
     * angka quote sebelum grup pembayaran dibuat.
     */
    private function applyPickupQuote(array $quote, array $pickupMap): array
    {
        foreach ($quote['shops'] as $shopQuote) {
            if (! isset($pickupMap[(int) $shopQuote->shop->id])) {
                continue;
            }

            $shopQuote->shipping = 0.0;
            $shopQuote->shippingBase = 0.0;
            $shopQuote->insurance = 0.0;
            $shopQuote->total = Money::of($shopQuote->subtotal)
                ->add($shopQuote->tax)
                ->add(0.0)
                ->add($shopQuote->giftFee ?? 0.0)
                ->subtract($shopQuote->couponDiscount)
                ->maxZero()
                ->toFloat();
        }

        $quote['shipping'] = (float) collect($quote['shops'])->sum('shipping');
        $quote['insurance'] = (float) collect($quote['shops'])->sum('insurance');
        $quote['grand_total'] = (float) collect($quote['shops'])->sum('total');
        $quote['amount_due_now'] = max(0.0, $quote['grand_total'] - (float) ($quote['preorder']['remaining'] ?? 0));

        return $quote;
    }

    /** Validasi gudang ambil: aktif + melayani pickup. Melempar 422 bila tidak. */
    private function resolvePickupWarehouse(int $shopId, int $warehouseId): \App\Models\Warehouse
    {
        $warehouse = \App\Models\Warehouse::query()
            ->whereKey($warehouseId)
            ->where('is_active', true)
            ->first();

        if ($warehouse === null) {
            throw ValidationException::withMessages([
                "shipping_methods.{$shopId}.pickup_warehouse_id" => 'Gudang pengambilan tidak tersedia.',
            ]);
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('warehouses', 'allow_pickup')
            && ! (bool) $warehouse->allow_pickup) {
            throw ValidationException::withMessages([
                "shipping_methods.{$shopId}.pickup_warehouse_id" => 'Gudang '.$warehouse->name.' tidak melayani pengambilan di tempat.',
            ]);
        }

        return $warehouse;
    }

    /**
     * Tulis kiriman pickup + tanda pada order dalam transaksi checkout yang
     * sedang berjalan (tanpa transaksi baru agar atomicity terjaga).
     */
    private function createPickupShipment(Order $order, \App\Models\Warehouse $warehouse): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('order_shipments', 'is_pickup')) {
            return;
        }

        $code = \App\Services\Backoffice\FulfillmentService::generatePickupCode();

        \App\Models\OrderShipment::query()->create([
            'order_id' => $order->getKey(),
            'provider_id' => null,
            'courier' => 'PICKUP',
            'service' => 'Ambil di Toko',
            'tracking_number' => null,
            'label_url' => null,
            'weight' => null,
            'cost' => 0,
            'status' => 'pending',
            'tracking_history' => [[
                'description' => 'Menunggu diambil di '.$warehouse->name.'. Kode ambil: '.$code,
                'at' => now()->toDateTimeString(),
            ]],
            'warehouse_id' => $warehouse->id,
            'is_pickup' => true,
            'pickup_code' => $code,
        ]);

        if (\Illuminate\Support\Facades\Schema::hasColumn('orders', 'is_pickup')) {
            $order->forceFill([
                'is_pickup' => true,
                'warehouse_id' => $warehouse->id,
                'pickup_warehouse_id' => $warehouse->id,
                'pickup_code_hash' => \Illuminate\Support\Facades\Hash::make($code),
            ])->save();
        }

        $order->statusHistory()->create([
            'status' => 'pickup_created',
            'changed_by' => $order->customer_id,
            'note' => 'Ambil di toko '.$warehouse->name.'. Tanpa ongkir.',
        ]);
    }

    /**
     * Validasi slot jadwal pengiriman (aditif checkout).
     * Jam tanpa tanggal ditolak; jendela H s.d. H+14 ditegakkan di
     * OrderWorkflowService agar satu sumber kebenaran dengan penjadwalan
     * ulang. Mengembalikan null bila pelanggan tidak memilih slot.
     *
     * @return array{date: string, time: string|null, label: string}|null
     */
    private function resolveDeliverySlot(array $validated): ?array
    {
        $date = isset($validated['delivery_slot_date']) ? trim((string) $validated['delivery_slot_date']) : '';
        $time = isset($validated['delivery_slot_time']) ? trim((string) $validated['delivery_slot_time']) : '';
        $label = isset($validated['delivery_slot_label']) ? trim((string) $validated['delivery_slot_label']) : '';

        if ($date === '' && $time === '' && $label === '') {
            return null;
        }

        if ($date === '') {
            throw ValidationException::withMessages(['delivery_slot_date' => 'Pilih hari pengiriman terlebih dahulu.']);
        }

        [$cleanDate, $cleanTime] = \App\Services\OrderWorkflowService::cleanDeliverySlot(
            $date,
            $time !== '' ? $time : null,
        );

        return [
            'date' => $cleanDate,
            'time' => $cleanTime,
            'label' => $label !== '' ? mb_substr($label, 0, 120) : ($cleanTime !== null ? $cleanDate.', '.$cleanTime : $cleanDate),
        ];
    }

    /**
     * Tulis kolom slot pada order dalam transaksi checkout yang sedang
     * berjalan (tanpa transaksi baru agar atomicity terjaga).
     *
     * @param  array{date: string, time: string|null, label: string}  $slot
     */
    private function persistDeliverySlot(Order $order, array $slot): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('orders', 'delivery_slot_date')) {
            return;
        }

        $order->forceFill([
            'delivery_slot_date' => $slot['date'],
            'delivery_slot_time' => $slot['time'],
            'delivery_slot_label' => $slot['label'],
            'slot_scheduled_at' => now(),
        ])->save();
    }

    /**
     * Atribut UTM order dari session (ditulis saat checkout).
     * Kosong bila kolom belum ada (backward-compatible, kalkulasi tetap).
     *
     * @return array{utm_source?: string|null, utm_medium?: string|null, utm_campaign?: string|null}
     */
    private function utmOrderAttributes(): array
    {
        try {
            $out = [];
            foreach (['source' => 'utm_source', 'medium' => 'utm_medium', 'campaign' => 'utm_campaign'] as $sessionKey => $column) {
                if (! \Illuminate\Support\Facades\Schema::hasColumn('orders', $column)) {
                    continue;
                }
                $value = session('utm.'.$sessionKey);
                $out[$column] = is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, 120) : null;
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    private function abortPayment(PaymentGroup $group): void
    {
        DB::transaction(function () use ($group): void {
            $locked = PaymentGroup::whereKey($group->getKey())->lockForUpdate()->first();

            if (! $locked) {
                return;
            }

            $locked->forceFill([
                'status' => PaymentStatus::Failed->value,
                'expired_at' => now(),
            ])->save();

            foreach ($locked->orders()->lockForUpdate()->get() as $order) {
                $order->forceFill([
                    'order_status' => OrderStatus::Failed->stored(),
                    'payment_status' => PaymentStatus::Unpaid->value,
                    'cancel_reason' => 'Gateway pembayaran tidak dapat membuat transaksi.',
                ])->save();

                $order->statusHistory()->create([
                    'status' => OrderStatus::Failed->stored(),
                    'note' => 'Checkout gagal karena gateway menolak permintaan pembayaran.',
                ]);

                app(OrderService::class)->restoreStock($order);
            }
        }, 3);
    }

    private function resumeOrder(Order $order)
    {
        $order->loadMissing('paymentGroup');

        // Jika grup masih retryable, coba buatkan ulang pembayaran gateway
        // untuk grup yang sama (tanpa order baru) sebelum menyerah.
        if ($order->isPaymentRetryable() && $order->paymentGroup?->provider) {
            $retry = $this->recreateGatewayPayment($order->paymentGroup);

            if ($retry !== null) {
                return $retry;
            }
        }

        $response = $order->paymentGroup?->gateway_response;
        $response = is_array($response) ? $response : [];

        foreach (['redirect_url', 'invoice_url', 'payment_url', 'checkout_url', 'redirect'] as $key) {
            $candidate = $response[$key] ?? null;

            if (is_string($candidate) && $candidate !== '') {
                return redirect()->away($candidate);
            }
        }

        return redirect()->route('orders.show', $order)->with('info', 'Pesanan ini sudah pernah dibuat.');
    }

    /**
     * Resume pembayaran gagal: pakai idempotency_key existing, tanpa order baru.
     * Mengembalikan redirect gateway baru bila berhasil, null bila tidak bisa.
     */
    private function retryFailedGroup(Order $replay, Provider $provider, PaymentGatewayService $payments, $customer)
    {
        $replay->loadMissing('paymentGroup.orders.items.product');

        $group = $replay->paymentGroup;

        if (! $group || ! $group->isRetryable()) {
            return null;
        }

        // Samakan provider bila pelanggan memilih gateway berbeda saat retry.
        if ((int) $group->provider_id !== (int) $provider->id) {
            $group->forceFill(['provider_id' => $provider->id])->save();
            $group->refresh();
        }

        return $this->recreateGatewayPayment($group, $payments, $customer);
    }

    private function recreateGatewayPayment(PaymentGroup $group, ?PaymentGatewayService $payments = null, $customer = null)
    {
        $payments ??= app(PaymentGatewayService::class);
        $customer ??= auth()->user();

        try {
            $locked = DB::transaction(function () use ($group) {
                $inner = PaymentGroup::whereKey($group->getKey())->lockForUpdate()->firstOrFail();

                if (! $inner->isRetryable()) {
                    return null;
                }

                $inner->forceFill([
                    'status' => PaymentStatus::Pending->value,
                    'expired_at' => now()->addMinutes($this->expiryMinutes()),
                ])->save();

                return $inner->fresh(['orders.items.product', 'provider']);
            }, 3);

            if ($locked === null) {
                return null;
            }

            $provider = $locked->provider;

            if (! $provider) {
                return null;
            }

            $payment = $payments->createPayment($provider, [
                'order_id' => $locked->payment_number, 'amount' => $locked->grand_total,
                'channel' => 'default',
                'customer' => ['name' => $customer->name ?? '', 'email' => $customer->email ?? '', 'phone' => $customer->phone ?? ''],
                'items' => $this->paymentItems($locked->orders),
                'success_url' => route('orders.index'),
                'callback_url' => route('webhook.payment', $provider),
            ]);

            if (! ($payment['success'] ?? false)) {
                return null;
            }

            $locked->forceFill([
                'gateway_reference' => $payment['transaction_id'] ?? $payment['invoice_id'] ?? $payment['reference'] ?? $locked->gateway_reference,
                'gateway_response' => is_array($payment['raw'] ?? null) ? $payment['raw'] : null,
            ])->save();

            if (! empty($payment['redirect_url'])) {
                return redirect()->away($payment['redirect_url'])->with('success', 'Pembayaran sebelumnya gagal. Silakan lanjutkan pembayaran baru tanpa membuat pesanan baru.');
            }

            return redirect()->route('orders.index')->with('success', 'Tautan pembayaran baru telah dibuat untuk pesanan Anda.');
        } catch (Throwable) {
            return null;
        }
    }

    private function idempotencyKey(Request $request, array $validated): ?string
    {
        $candidate = $validated['idempotency_key'] ?? $request->header('Idempotency-Key');
        $candidate = is_string($candidate) ? trim($candidate) : '';

        if ($candidate === '' || mb_strlen($candidate) > 80) {
            return null;
        }

        return $candidate;
    }

    private function expiryMinutes(): int
    {
        return max(15, (int) (SystemSetting::get('payment_expiry_minutes', '1440') ?: 1440));
    }

    private function resolveAddress(array $data, int $customerId): CustomerAddress
    {
        if (! empty($data['address_id'])) {
            return CustomerAddress::where('customer_id', $customerId)->findOrFail($data['address_id']);
        }

        return CustomerAddress::create([
            'customer_id' => $customerId, 'label' => $data['new_label'] ?? 'Rumah', 'receiver_name' => $data['new_receiver_name'],
            'receiver_phone' => $data['new_receiver_phone'], 'address' => $data['new_address'], 'city' => $data['new_city'],
            'province' => $data['new_province'], 'postal_code' => $data['new_postal_code'] ?? null,
            'shipping_destination_id' => $data['new_shipping_destination_id'],
            'is_default' => ! CustomerAddress::where('customer_id', $customerId)->exists(),
        ]);
    }

    private function transactionPaymentMethod(Provider $provider): string
    {
        return match (true) {
            str_contains($provider->api_format, 'midtrans') => 'midtrans', str_contains($provider->api_format, 'xendit') => 'xendit',
            default => 'transfer',
        };
    }

    private function paymentItems($orders): array
    {
        $items = [];

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $productName = $item->product?->name ?? ('Produk #'.$item->product_id);
                $items[] = [
                    'id' => 'ITEM-'.$item->id,
                    'name' => mb_substr($productName, 0, 50),
                    'price' => (int) Money::of($item->price)->toDecimal(),
                    'quantity' => (int) $item->quantity,
                ];
            }

            $shipping = Money::of($order->shipping_cost);
            if ($shipping->isPositive()) {
                $items[] = ['id' => 'SHIP-'.$order->id, 'name' => 'Biaya pengiriman', 'price' => (int) $shipping->toDecimal(), 'quantity' => 1];
            }

            $tax = Money::of($order->tax);
            if ($tax->isPositive()) {
                $items[] = ['id' => 'TAX-'.$order->id, 'name' => 'Pajak', 'price' => (int) $tax->toDecimal(), 'quantity' => 1];
            }

            $discount = Money::of($order->coupon_discount);
            if ($discount->isPositive()) {
                $items[] = ['id' => 'DISC-'.$order->id, 'name' => 'Diskon', 'price' => -(int) $discount->toDecimal(), 'quantity' => 1];
            }

            $gift = Money::of($order->getAttribute('gift_fee') ?? 0);
            if ($gift->isPositive()) {
                $items[] = ['id' => 'GIFT-'.$order->id, 'name' => 'Bungkus kado', 'price' => (int) $gift->toDecimal(), 'quantity' => 1];
            }

            $remaining = Money::of($order->getAttribute('preorder_remaining') ?? 0);
            if ($remaining->isPositive()) {
                $items[] = ['id' => 'PREDP-'.$order->id, 'name' => 'Pelunasan pre-order (dibayar sebelum kirim)', 'price' => -(int) $remaining->toDecimal(), 'quantity' => 1];
            }
        }

        return $items;
    }
}
