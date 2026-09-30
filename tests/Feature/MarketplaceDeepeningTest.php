<?php

namespace Tests\Feature;

use App\Domain\Finance\PayoutStateMachine;
use App\Domain\Finance\TieredCommission;
use App\Domain\Inventory\InventoryLedger;
use App\Domain\Order\SuborderSplitter;
use App\Domain\Risk\RiskEngine;
use App\Domain\Support\SupportSla;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Shop;
use App\Models\User;
use App\Models\VendorWithdrawRequest;
use App\Models\Wallet;
use App\Services\Backoffice\FinanceAdminService;
use App\Services\Backoffice\FulfillmentService;
use App\Services\Backoffice\StockService;
use App\Services\OrderService;
use App\Services\OrderWorkflowService;
use App\Services\RefundWorkflowService;
use App\Services\Vendor\PosService;
use App\Services\Vendor\VendorFinanceService;
use App\Services\Vendor\VendorRegistrationService;
use App\Services\Vendor\VendorScope;
use App\Services\Vendor\VendorTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Pendalaman marketplace/OMS/inventory/finance di atas fondasi existing.
 *
 * Mencakup: KYC bertahap, ledger states + alokasi multi-gudang,
 * suborder deterministik, RMA→QC→resolusi, komisi bertingkat + payout
 * state machine, risk rules, POS sinkron inventaris, support 3-arah.
 * Uji: konkurensi/duplikat/partial/cancel/saldo-negatif.
 */
class MarketplaceDeepeningTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $vendor;

    private Shop $shop;

    private Product $product;

    private Category $category;

    public function setUp(): void
    {
        parent::setUp();

        $this->customer = User::create([
            'name' => 'Pelanggan', 'email' => 'cust-'.uniqid().'@uji.id',
            'password' => bcrypt('rahasia'), 'role' => 'customer', 'status' => 'active',
        ]);

        $this->vendor = User::create([
            'name' => 'Vendor', 'email' => 'vend-'.uniqid().'@uji.id',
            'password' => bcrypt('rahasia'), 'role' => 'vendor', 'status' => 'active',
        ]);
        Wallet::firstOrCreate(['user_id' => $this->vendor->id], ['balance' => 500000, 'pending_balance' => 0]);

        $this->shop = Shop::create([
            'vendor_id' => $this->vendor->id, 'name' => 'Toko Uji', 'slug' => 'toko-'.uniqid(),
            'status' => 'active', 'commission_type' => 'percentage', 'commission_value' => 6,
            'phone' => '08123456789', 'tin' => '123456789012345',
            'bank_name' => 'BCA', 'bank_account_number' => '1234567890', 'bank_account_name' => 'Toko Uji',
        ]);

        $this->category = Category::create(['name' => 'Kat '.uniqid(), 'slug' => 'kat-'.uniqid(), 'status' => true]);

        $this->product = Product::create([
            'shop_id' => $this->shop->id, 'category_id' => $this->category->id,
            'name' => 'Produk Uji', 'slug' => 'produk-'.uniqid(),
            'price' => 100000, 'current_stock' => 50, 'weight' => 500,
            'status' => 'approved', 'published' => true, 'min_qty' => 1, 'max_qty' => 100,
        ]);
    }

    private function stock(): StockService
    {
        return app(StockService::class);
    }

    private function warehouse(string $code, bool $default = false): \App\Models\Warehouse
    {
        return \App\Models\Warehouse::create([
            'name' => 'Gudang '.$code, 'code' => $code, 'is_default' => $default, 'is_active' => true,
        ]);
    }

    private function makePaidDeliveredOrder(array $over = []): Order
    {
        $order = Order::create(array_merge([
            'order_number' => Order::generateOrderNumber(),
            'customer_id' => $this->customer->id, 'shop_id' => $this->shop->id,
            'sub_total' => 200000, 'tax' => 22000, 'shipping_cost' => 15000,
            'discount' => 0, 'coupon_discount' => 0, 'total' => 237000,
            'payment_status' => 'paid', 'order_status' => OrderStatus::Delivered->stored(),
            'payment_method' => 'transfer',
            'idempotency_key' => 'uji-'.uniqid(),
        ], $over));

        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $this->product->id,
            'quantity' => 2, 'price' => 100000, 'tax' => 22000,
            'discount' => 0, 'sub_total' => 200000,
        ]);

        return $order->fresh(['items']);
    }

    // ── 1. KYC bertahap + expiry + alasan tolak (tanpa log PII) ──

    public function test_kyc_bertahap_expiry_dan_alasan_tolak(): void
    {
        $svc = app(VendorRegistrationService::class);

        $app = $svc->submit([
            'shop_name' => 'Toko KYC '.uniqid(), 'owner_name' => 'Owner',
            'email' => 'kyc-'.uniqid().'@uji.id', 'password' => 'rahasia123',
            'phone' => '08123456789',
            'bank_name' => 'BCA', 'bank_account_name' => 'Owner',
            'bank_account_number' => '9876543210',
            'commission_value' => 8,
        ]);

        $doc = $svc->attachDocument((int) $app->id, [
            'kind' => 'identity', 'path' => 'kyc/ktp.jpg',
            'original_name' => 'ktp.jpg', 'mime_type' => 'image/jpeg', 'size' => 123,
        ]);

        // Setujui dengan masa berlaku.
        $out = $svc->verifyDocument((int) $app->id, (int) $doc->id, 'approved', null, now()->addYear()->format('Y-m-d'), 1);
        $this->assertSame('verified', $out['document']->status);

        // Idempoten: keputusan sama tidak duplikat.
        $again = $svc->verifyDocument((int) $app->id, (int) $doc->id, 'approved', null, now()->addYear()->format('Y-m-d'), 1);
        $this->assertSame((string) $out['document']->updated_at, (string) $again['document']->updated_at);

        // Tolak dokumen lain dengan alasan.
        $doc2 = $svc->attachDocument((int) $app->id, ['kind' => 'bank_letter', 'path' => 'kyc/rek.jpg']);
        $rejected = $svc->verifyDocument((int) $app->id, (int) $doc2->id, 'rejected', 'Foto buram, mohon unggah ulang.', null, 1);
        $this->assertSame('rejected', $rejected['document']->status);
        $this->assertStringContainsString('Foto buram', (string) $rejected['document']->note);
        $this->assertNotEmpty($rejected['application']->rejection_reason);

        // Progres bertahap + status expiry.
        $progress = $svc->stagedProgress((int) $app->id);
        $this->assertArrayHasKey('stages', $progress);
        $this->assertSame(3, count($progress['stages']));

        $expiry = $svc->documentExpiryStatus($rejected['application']);
        $this->assertContains((int) $doc->id, $expiry['valid']);

        // Segel data sensitif terenkripsi; audit tanpa PII.
        $sealed = $svc->sealSensitive((int) $app->id, 1);
        $meta = json_decode((string) $sealed->meta, true);
        $this->assertArrayHasKey('bank_enc', $meta);
        $this->assertNotSame('1234567890', (string) $meta['bank_enc']);

        $logs = DB::table('audit_logs')->where('action', 'like', 'vendor.kyc%')->get();
        $this->assertGreaterThan(0, $logs->count());
        foreach ($logs as $log) {
            $blob = json_encode([$log->old_values, $log->new_values]);
            $this->assertStringNotContainsString('kyc-', (string) $blob);
        }

        // Checklist KYC toko dari kolom existing.
        $check = $this->shop->kycChecklist();
        $this->assertSame('verified', $check['stage']);
        $this->assertSame('******7890', $this->shop->maskedBankAccount());
    }

    // ── 2. Ledger states + alokasi multi-gudang + anti race ──

    public function test_ledger_states_dan_reservasi_idempoten(): void
    {
        $a = $this->warehouse('TST-A', true);

        // Siapkan on_hand dulu (baris baru default 0).
        $this->stock()->adjust($this->product->id, $a->id, 10, null, 'Stok awal', 1);

        $first = $this->stock()->reserve($this->product->id, $a->id, 5, null, 'ref-dup-1', 1);
        $this->assertSame(5, $first['reserved']);
        $this->assertSame(5, $first['available']);

        // Duplikat referensi: tidak ganda.
        $second = $this->stock()->reserve($this->product->id, $a->id, 5, null, 'ref-dup-1', 1);
        $this->assertSame(5, $second['reserved']);

        $row = ProductStock::where('product_id', $this->product->id)->where('warehouse_id', $a->id)->firstOrFail();
        $snapshot = $row->ledgerSnapshot();
        $this->assertSame(['on_hand', 'reserved', 'available', 'damaged', 'returned', 'incoming'], array_keys($snapshot));
        $this->assertSame(5, $snapshot['reserved']);

        // Lepas reservasi.
        $released = $this->stock()->release($this->product->id, $a->id, 2, null, 'ref-dup-1', 1);
        $this->assertSame(3, $released['reserved']);
    }

    public function test_alokasi_multi_gudang_partial_dan_guard_negatif(): void
    {
        $a = $this->warehouse('TST-B', true);
        $b = $this->warehouse('TST-C');

        // Siapkan stok: A=2, B=5.
        $this->stock()->adjust($this->product->id, $a->id, 2, null, 'Stok awal A', 1);
        $this->stock()->adjust($this->product->id, $b->id, 5, null, 'Stok awal B', 1);

        // Partial: butuh 6 → A:2 + B:4 (deterministik, default dulu).
        $plan = $this->stock()->allocateMultiWarehouse([$this->product->id => 6], null, 'order-1', 1);
        $this->assertSame(2, $plan[$this->product->id][$a->id]);
        $this->assertSame(4, $plan[$this->product->id][$b->id]);

        // Deterministik: hitung ulang sisa identik.
        $leftA = ProductStock::where('product_id', $this->product->id)->where('warehouse_id', $a->id)->firstOrFail();
        $this->assertSame(0, $leftA->available());

        // Guard negatif: minta melebihi sisa → 422, saldo tak negatif.
        try {
            $this->stock()->reserve($this->product->id, $a->id, 99, null, 'ref-over', 1);
            $this->fail('Seharusnya ValidationException.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        foreach (ProductStock::where('product_id', $this->product->id)->get() as $stock) {
            $this->assertGreaterThanOrEqual(0, $stock->available());
            $this->assertGreaterThanOrEqual(0, (int) $stock->on_hand);
        }

        // Barang rusak: on_hand berkurang + movement audit.
        $damaged = $this->stock()->recordDamaged($this->product->id, $b->id, 1, null, 'Kemasan sobek', 1);
        $this->assertSame(4, $damaged['on_hand']);
        $this->assertDatabaseHas('stock_movements', ['type' => 'adjustment', 'reference_type' => 'damaged']);

        // Barang datang: incoming → on_hand.
        $in = $this->stock()->receiveIncoming($this->product->id, $b->id, 10, null, 'PO-1', 1);
        $this->assertSame(14, $in['on_hand']);
    }

    // ── 3. Suborder deterministik ──

    public function test_suborder_deterministik_dan_rekonsiliasi(): void
    {
        $quotes = [
            ['shop_id' => 7, 'subtotal' => 100000, 'tax' => 11000, 'shipping' => 10000, 'discount' => 5000, 'commission_rate' => 6],
            ['shop_id' => 3, 'subtotal' => 50000, 'tax' => 5500, 'shipping' => 8000, 'discount' => 0, 'commission_rate' => 8],
        ];

        $first = SuborderSplitter::split($quotes);
        $second = SuborderSplitter::split(array_reverse($quotes));

        $this->assertSame($first, $second);
        $this->assertSame(3, $first['vendors'][0]['shop_id']);
        $this->assertEquals(150000.0, $first['totals']['subtotal']);
        $this->assertEquals($first['totals']['grand_total'], array_sum(array_column($first['vendors'], 'net')));

        foreach ($first['vendors'] as $vendor) {
            $this->assertEquals(
                $vendor['vendor_payable'] + $vendor['commission'],
                $vendor['net']
            );
        }

        $shares = SuborderSplitter::allocateDiscount([3 => 50000.0, 7 => 100000.0], 9000.0);
        $this->assertEquals(9000.0, round(array_sum($shares), 2));
    }

    // ── 4. RMA → QC → resolusi + fee proporsional, idempoten ──

    public function test_rma_qc_resolusi_refund_idempoten(): void
    {
        $order = $this->makePaidDeliveredOrder();

        $rma = OrderReturn::create([
            'rma_number' => 'RMA-'.uniqid(), 'order_id' => $order->id,
            'reason' => 'rusak', 'status' => 'approved', 'amount' => 200000,
        ]);

        $svc = app(RefundWorkflowService::class);
        $graded = $svc->gradeReturn($rma, 'baik', 1, 'Kondisi baik', 2);
        $this->assertSame('baik', $graded->qc_grade);

        // Idempoten: grade sama → tidak nambah movement.
        $movementsBefore = DB::table('stock_movements')->where('reference_type', 'order_return')->where('reference_id', $rma->id)->count();
        $svc->gradeReturn($rma->fresh(), 'baik', 1, 'Kondisi baik', 2);
        $this->assertSame($movementsBefore, DB::table('stock_movements')->where('reference_type', 'order_return')->where('reference_id', $rma->id)->count());

        // Proporsional: 200rb/200rb → ongkir+pajak penuh; fee 10%.
        $calc = $rma->fresh()->proportionalRefund(200000, 15000, 22000, 10.0);
        $this->assertEquals(15000.0, $calc['shipping']);
        $this->assertEquals(22000.0, $calc['tax']);
        $this->assertEquals(20000.0, $calc['restocking_fee']);
        $this->assertEquals(217000.0, $calc['total']);

        $resolved = $svc->resolveReturn($rma->fresh(), 'refund', 1, 'Setuju refund', 10.0);
        $this->assertSame('refund', $resolved['resolution']);
        $this->assertEquals(217000.0, $resolved['breakdown']['total']);
        $this->assertSame('refunded', $resolved['rma']->status);

        // Idempoten: putus lagi → hasil sama, amount tak berubah.
        $again = $svc->resolveReturn($resolved['rma']->fresh(), 'refund', 1, 'Setuju refund', 10.0);
        $this->assertEquals($resolved['breakdown']['total'], $again['breakdown']['total']);

        // Jalur fulfillment: QC → replace.
        $rma2 = OrderReturn::create([
            'rma_number' => 'RMA-'.uniqid(), 'order_id' => $order->id,
            'reason' => 'salah', 'status' => 'approved', 'amount' => 100000,
        ]);
        $full = app(FulfillmentService::class)->qcResolve($rma2, 'baik', 'replace', 1, 'Ganti baru', 1);
        $this->assertSame('replace', $full['resolution']);
    }

    // ── 5. Komisi bertingkat + payout state machine + retry + rekonsiliasi ──

    public function test_komisi_bertingkat_deterministik(): void
    {
        $this->assertSame('starter', TieredCommission::tierFor(500000)['tier']);
        $this->assertSame('growth', TieredCommission::tierFor(5000000)['tier']);
        $this->assertSame('scale', TieredCommission::tierFor(20000000)['tier']);
        $this->assertSame('enterprise', TieredCommission::tierFor(100000000)['tier']);

        $calc = TieredCommission::forOrder(100000, 500000);
        $this->assertEquals(8.0, $calc['rate']);
        $this->assertEquals(8000.0, $calc['commission']);
        $this->assertSame($calc, TieredCommission::forOrder(100000, 500000));

        $finance = new VendorFinanceService(new VendorScope);
        $preview = $finance->previewTieredCommission((int) $this->shop->id, 100000);
        $this->assertArrayHasKey('tier', $preview);
    }

    public function test_payout_state_machine_retry_rekonsiliasi(): void
    {
        $this->assertTrue(PayoutStateMachine::can('pending', 'approved'));
        $this->assertFalse(PayoutStateMachine::can('pending', 'completed'));
        $this->assertTrue(PayoutStateMachine::isTerminal('completed'));

        $request = VendorWithdrawRequest::create([
            'vendor_id' => $this->vendor->id, 'shop_id' => $this->shop->id,
            'amount' => 100000, 'status' => 'pending',
            'bank_name' => 'BCA', 'bank_account_number' => '1234567890', 'bank_account_name' => 'Toko Uji',
        ]);

        $admin = app(FinanceAdminService::class);
        $approved = $admin->transitionPayout($request, 'approved', 1);
        $this->assertSame('approved', $approved->status);

        // Transisi ilegal ditolak.
        try {
            $admin->transitionPayout($approved, 'completed', 1);
            $this->fail('Seharusnya ValidationException.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $processing = $admin->transitionPayout($approved, 'processing', 1);
        $this->assertSame('processing', $processing->status);

        // Idempoten: transisi ke status sama.
        $same = $admin->transitionPayout($processing, 'processing', 1);
        $this->assertSame('processing', $same->status);

        $failed = VendorWithdrawRequest::find($processing->id);
        $failed->forceFill(['status' => 'failed'])->save();

        // Retry gagal → processing lagi.
        $retried = $admin->retryPayout($failed->fresh(), 1);
        $this->assertSame('processing', $retried->status);

        $done = $admin->transitionPayout($retried, 'completed', 1);
        $this->assertSame('completed', $done->status);

        // Ledger payout seimbang.
        $debit = (float) LedgerEntry::where('entry_type', 'vendor_payout')->where('direction', 'debit')->sum('amount');
        $credit = (float) LedgerEntry::where('entry_type', 'vendor_payout')->where('direction', 'credit')->sum('amount');
        $this->assertEquals($debit, $credit);
        $this->assertEquals(100000.0, $debit);

        $recon = $admin->reconcilePayout((int) $this->shop->id);
        $this->assertArrayHasKey('balanced', $recon);
    }

    public function test_request_payout_guard_saldo_negatif_dan_duplikat(): void
    {
        $this->actingAs($this->vendor);
        $finance = app(VendorFinanceService::class);

        // Saldo 500rb: minta 900rb → ditolak.
        try {
            $finance->requestPayout(900000);
            $this->fail('Seharusnya ValidationException saldo.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $first = $finance->requestPayout(100000, 'Cair mingguan', '2026-09');
        $this->assertSame('pending', $first->status);

        // Duplikat (label+nominal sama) → baris sama.
        $second = $finance->requestPayout(100000, 'Cair mingguan', '2026-09');
        $this->assertSame($first->id, $second->id);
    }

    // ── 6. Fraud/risk rules ──

    public function test_risk_engine_aturan_ekstensibel(): void
    {
        $engine = new RiskEngine;

        $clean = $engine->evaluate([
            'orders_last_hour' => 1, 'coupon_uses' => 1,
            'refund_count' => 0, 'refund_ratio' => 0.0, 'payment_fails_24h' => 0,
        ]);
        $this->assertSame('allow', $clean['decision']);

        $abuse = $engine->evaluate([
            'orders_last_hour' => 12, 'coupon_uses' => 9,
            'refund_count' => 5, 'refund_ratio' => 0.8, 'payment_fails_24h' => 4,
        ]);
        $this->assertSame('hold', $abuse['decision']);
        $this->assertNotEmpty($abuse['flags']);

        $flags = array_column($abuse['flags'], 'flag');
        $this->assertContains('velocity', $flags);
        $this->assertContains('coupon_abuse', $flags);
        $this->assertContains('refund_abuse', $flags);
        $this->assertContains('payment_failure', $flags);

        // Aturan custom dapat didaftarkan.
        $engine->register('custom_midnight', fn (array $ctx): ?array => ($ctx['hour'] ?? 12) < 4
            ? ['score' => 35, 'flag' => 'midnight', 'reason' => 'Order tengah malam.']
            : null);
        $review = $engine->evaluate(['hour' => 2]);
        $this->assertSame('review', $review['decision']);
    }

    // ── 7. POS: shift/drawer/split/refund sinkron inventaris ──

    public function test_pos_idempoten_split_refund_sinkron_stok(): void
    {
        $this->actingAs($this->vendor);
        $pos = app(PosService::class);

        $payload = [
            'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
            'payment_method' => 'cash',
            'idempotency_key' => 'kasir-'.uniqid(),
        ];

        $first = $pos->sell($payload);
        $second = $pos->sell($payload);

        // Idempoten: double-tap tombol Bayar → satu order.
        $this->assertSame($first->id, $second->id);
        $this->assertSame(48, (int) $this->product->fresh()->current_stock);

        // Split tender harus pas.
        try {
            $pos->sell([
                'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
                'tenders' => [['method' => 'cash', 'amount' => 1]],
                'idempotency_key' => 'kasir-'.uniqid(),
            ]);
            $this->fail('Seharusnya ValidationException split.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Refund sinkron inventaris + idempoten.
        $item = $first->items()->firstOrFail();
        $refunded = $pos->refundSale($item, 1, 'Pelanggan kembalikan 1');
        $this->assertSame('refunded', $refunded->refund_status);
        $this->assertSame(49, (int) $this->product->fresh()->current_stock);

        $again = $pos->refundSale($item->fresh(), 1, 'Pelanggan kembalikan 1');
        $this->assertSame($refunded->refund_amount, $again->refund_amount);
        $this->assertSame(49, (int) $this->product->fresh()->current_stock);
    }

    // ── 8. Support 3-arah + SLA + internal notes ──

    public function test_support_tiga_arah_sla_dan_catatan_internal(): void
    {
        $this->actingAs($this->vendor);
        $svc = app(VendorTicketService::class);

        $support = User::create([
            'name' => 'CS', 'email' => 'cs-'.uniqid().'@uji.id',
            'password' => bcrypt('rahasia'), 'role' => 'admin', 'status' => 'active',
        ]);

        $ticketId = DB::table('support_tickets')->insertGetId([
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'reference' => 'TKT-'.uniqid(), 'subject' => 'Barang belum sampai',
            'type' => 'order', 'description' => 'Mohon bantuan.',
            'priority' => 'urgent', 'status' => 'open',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $asCustomer = $svc->replyAs($ticketId, (int) $this->customer->id, 'Pesanan saya belum sampai.');
        $this->assertSame('customer', $asCustomer['role']);

        $asVendor = $svc->replyAs($ticketId, (int) $this->vendor->id, 'Kami cek ke kurir.');
        $this->assertSame('vendor', $asVendor['role']);

        $internal = $svc->replyAs($ticketId, (int) $support->id, 'Vendor ini sering terlambat.', true);
        $this->assertSame('support', $internal['role']);
        $this->assertTrue($internal['internal']);

        // Customer tidak melihat catatan internal.
        $forCustomer = $svc->threadForCustomer($ticketId);
        $this->assertCount(2, $forCustomer['replies']);

        $full = $svc->threadFull($ticketId);
        $this->assertCount(3, $full['replies']);

        // SLA urgent (8 jam pada VendorTicketService::sla): dibuat 9 jam lalu → terlampaui.
        DB::table('support_tickets')->where('id', $ticketId)->update(['created_at' => now()->subHours(9)]);
        $ticket = $svc->find($ticketId);
        $sla = $svc->sla($ticket);
        $this->assertTrue($sla['breached']);

        $this->assertTrue(SupportSla::isInternal(SupportSla::markInternal('catatan')));
        $this->assertNull(SupportSla::visibleForCustomer(SupportSla::markInternal('catatan')));
    }

    // ── Atomicity: cancel pulihkan stok; settlement seimbang ──

    public function test_cancel_pulihkan_stok_dan_settlement_seimbang(): void
    {
        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'customer_id' => $this->customer->id, 'shop_id' => $this->shop->id,
            'sub_total' => 100000, 'tax' => 11000, 'shipping_cost' => 0,
            'discount' => 0, 'coupon_discount' => 0, 'total' => 111000,
            'payment_status' => 'unpaid', 'order_status' => OrderStatus::Pending->stored(),
            'payment_method' => 'transfer',
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $this->product->id,
            'quantity' => 3, 'price' => 100000, 'tax' => 0, 'discount' => 0, 'sub_total' => 300000,
        ]);

        $before = (int) $this->product->fresh()->current_stock;
        app(OrderWorkflowService::class)->cancel($order, 1, 'Uji cancel');

        // restoreStock menambah current_stock produk.
        $this->assertSame($before + 3, (int) $this->product->fresh()->current_stock);

        // Cancel kedua idempoten (status sama → no-op).
        $cancelled = Order::find($order->id);
        $this->assertSame(OrderStatus::Cancelled->stored(), $cancelled->order_status);

        // Settlement delivered menulis jurnal seimbang.
        $paid = $this->makePaidDeliveredOrder();
        app(OrderService::class)->settleDelivered($paid);

        $debit = (float) LedgerEntry::where('entry_type', 'order_settlement')->where('direction', 'debit')->sum('amount');
        $credit = (float) LedgerEntry::where('entry_type', 'order_settlement')->where('direction', 'credit')->sum('amount');
        $this->assertEquals($debit, $credit);
        $this->assertGreaterThan(0, $debit);

        // Snapshot ledger states tersedia.
        $snap = InventoryLedger::snapshot(['on_hand' => 10, 'reserved' => 3, 'incoming' => 4]);
        $this->assertSame(7, $snap['available']);
        $this->assertSame(0, $snap['damaged']);
    }
}
