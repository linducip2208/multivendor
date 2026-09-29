<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentGroup;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Shop;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VatTax;
use App\Models\Wallet;
use App\Services\Backoffice\FinanceAdminService;
use App\Services\Finance\LedgerService;
use App\Services\Vendor\VendorFinanceService;
use App\Services\Vendor\VendorScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Perdalaman keuangan existing: e-Faktur, settlement terjadwal,
 * held vs tersedia, L/R + CSV. Self-contained memakai RefreshDatabase.
 */
class FinanceExpansionTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(): User
    {
        return User::create([
            'name' => 'Pelanggan Uji', 'email' => 'pelanggan'.uniqid().'@uji.id',
            'password' => bcrypt('rahasia'), 'role' => 'customer', 'status' => 'active',
        ]);
    }

    private function makeVendor(): User
    {
        $vendor = User::create([
            'name' => 'Vendor Uji', 'email' => 'vendor'.uniqid().'@uji.id',
            'password' => bcrypt('rahasia'), 'role' => 'vendor', 'status' => 'active',
        ]);

        Shop::create([
            'vendor_id' => $vendor->id, 'name' => 'Toko Uji', 'slug' => 'toko-uji-'.uniqid(),
            'status' => 'active', 'commission_type' => 'percentage', 'commission_value' => 10,
        ]);

        Wallet::firstOrCreate(['user_id' => $vendor->id], ['balance' => 0, 'pending_balance' => 0]);

        return $vendor->fresh();
    }

    private function makeOrder(User $customer, Shop $shop, array $over = []): Order
    {
        $category = Category::create(['name' => 'Kat '.uniqid(), 'slug' => 'kat-'.uniqid(), 'status' => true]);
        $product = Product::create([
            'shop_id' => $shop->id, 'category_id' => $category->id,
            'name' => 'Produk Uji', 'slug' => 'produk-'.uniqid(),
            'price' => 100000, 'current_stock' => 100, 'weight' => 1000,
            'status' => 'approved', 'published' => true, 'min_qty' => 1, 'max_qty' => 10,
        ]);

        $provider = Provider::create([
            'name' => 'Gateway Uji', 'type' => 'payment',
            'api_format' => 'format-tidak-didukung-uji', 'is_active' => true, 'sort_order' => 1,
        ]);

        $group = PaymentGroup::create([
            'payment_number' => PaymentGroup::generateNumber(),
            'customer_id' => $customer->id, 'provider_id' => $provider->id,
            'subtotal' => 100000, 'tax' => 11000, 'shipping_cost' => 0,
            'discount' => 0, 'grand_total' => 111000,
            'status' => 'paid', 'paid_at' => now(), 'expired_at' => now()->addDay(),
        ]);

        $order = Order::create(array_merge([
            'payment_group_id' => $group->id,
            'order_number' => Order::generateOrderNumber(),
            'customer_id' => $customer->id, 'shop_id' => $shop->id,
            'sub_total' => 100000, 'tax' => 11000, 'shipping_cost' => 0,
            'discount' => 0, 'coupon_discount' => 0, 'total' => 111000,
            'payment_status' => 'paid', 'order_status' => OrderStatus::Delivered->stored(),
            'payment_method' => 'transfer',
            'idempotency_key' => 'uji-'.uniqid(),
        ], $over));

        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'quantity' => 1, 'price' => 100000, 'tax' => 11000,
            'discount' => 0, 'sub_total' => 100000,
        ]);

        $group->orders()->attach($order->id, ['amount' => $order->total]);

        Transaction::create([
            'transaction_id' => 'TRX-'.$order->order_number,
            'payment_group_id' => $group->id, 'order_id' => $order->id,
            'customer_id' => $customer->id, 'shop_id' => $shop->id,
            'amount' => $order->total, 'admin_commission' => 10000, 'vendor_amount' => 101000,
            'payment_method' => 'transfer', 'status' => 'success', 'paid_at' => now(),
        ]);

        return $order->fresh(['items', 'paymentGroup', 'shop']);
    }

    private function finance(): VendorFinanceService
    {
        return new VendorFinanceService(new VendorScope);
    }

    public function test_efaktur_otomatis_nomor_seri_dpp_ppn(): void
    {
        VatTax::create(['name' => 'PPN', 'rate' => 11, 'is_active' => true]);
        SystemSetting::set('tax_include_in_price', null);

        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $order = $this->makeOrder($customer, $vendor->shop);

        $first = $this->finance()->issueTaxInvoice($order);
        $second = $this->finance()->issueTaxInvoice($order->fresh());

        $this->assertStringStartsWith('INV-', $first['invoice_number']);
        $this->assertMatchesRegularExpression('/^010\.\d{3}-\d{2}\.\d{8}$/', $first['tax_serial']);
        $this->assertSame($first['tax_serial'], $second['tax_serial']);
        $this->assertEquals(100000, $first['dpp']);
        $this->assertEquals(11000, $first['ppn']);
        $this->assertEquals(1, DB::table('tax_invoices')->where('order_id', $order->id)->count());

        $debit = (float) LedgerEntry::where('entry_type', 'tax_invoice')->where('direction', 'debit')->sum('amount');
        $credit = (float) LedgerEntry::where('entry_type', 'tax_invoice')->where('direction', 'credit')->sum('amount');
        $this->assertEquals($debit, $credit);
        $this->assertEquals(11000.0, $debit);
    }

    public function test_saldo_tertahan_rilis_otomatis_saat_completed(): void
    {
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $order = $this->makeOrder($customer, $vendor->shop, ['order_status' => OrderStatus::Shipped->stored()]);

        $wallet = Wallet::where('user_id', $vendor->id)->firstOrFail();
        $this->assertEquals(0.0, (float) $wallet->balance);
        $this->assertEquals(0.0, (float) $wallet->pending_balance);

        $this->finance()->holdForOrder($order->fresh(), 101000);

        $wallet->refresh();
        $this->assertEquals(0.0, (float) $wallet->balance);
        $this->assertEquals(101000.0, (float) $wallet->pending_balance);

        // Belum completed: tidak ada rilis.
        $this->assertNull($this->finance()->releaseOnCompleted($order->fresh()));
        $this->assertEquals(101000.0, (float) $wallet->fresh()->pending_balance);

        $order->forceFill(['order_status' => OrderStatus::Completed->stored()])->save();

        $release = $this->finance()->releaseOnCompleted($order->fresh());
        $this->assertNotNull($release);
        $this->assertSame('release', $release->operation);

        $wallet->refresh();
        $this->assertEquals(101000.0, (float) $wallet->balance);
        $this->assertEquals(0.0, (float) $wallet->pending_balance);

        // Idempoten: rilis kedua mengembalikan transaksi yang sama.
        $again = $this->finance()->releaseOnCompleted($order->fresh());
        $this->assertSame($release->id, $again->id);
        $this->assertEquals(101000.0, (float) $wallet->fresh()->balance);

        // Riwayat hold + release tercatat.
        $ops = $wallet->transactions()->whereIn('operation', ['hold', 'release'])
            ->pluck('operation')->all();
        $this->assertContains('hold', $ops);
        $this->assertContains('release', $ops);
    }

    public function test_settlement_terjadwal_dan_riwayat_per_periode(): void
    {
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $this->makeOrder($customer, $vendor->shop);

        $service = app(FinanceAdminService::class);
        $from = now()->startOfMonth()->format('Y-m-d').' 00:00:00';
        $to = now()->endOfMonth()->format('Y-m-d').' 23:59:59';

        $first = $service->runScheduledSettlement($from, $to);
        $this->assertSame(1, $first['shops']);
        $this->assertCount(1, $first['batches']);
        $this->assertEquals(10000.0, $first['batches'][0]['commission']);
        $this->assertEquals(101000.0, $first['batches'][0]['net_payable']);

        // Idempoten: jalan kedua tidak menambah batch.
        $second = $service->runScheduledSettlement($from, $to);
        $this->assertSame(1, DB::table('settlement_batches')->count());
        $this->assertSame($first['batches'][0]['id'], $second['batches'][0]['id']);

        // Jurnal settlement seimbang.
        $debit = (float) LedgerEntry::where('entry_type', 'settlement_batch')->where('direction', 'debit')->sum('amount');
        $credit = (float) LedgerEntry::where('entry_type', 'settlement_batch')->where('direction', 'credit')->sum('amount');
        $this->assertEquals($debit, $credit);
        $this->assertGreaterThan(0, $debit);

        // Riwayat per periode tersedia untuk view.
        $history = $service->settlementBatches();
        $this->assertCount(1, $history);
        $this->assertSame(date('Y-m'), $history[0]['period_label']);
        $this->assertArrayHasKey('net_payable_formatted', $history[0]);
    }

    public function test_laba_rugi_per_toko_dan_csv_akuntansi(): void
    {
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $this->makeOrder($customer, $vendor->shop);

        $from = now()->startOfMonth()->format('Y-m-d').' 00:00:00';
        $to = now()->endOfMonth()->format('Y-m-d').' 23:59:59';

        $pl = $this->finance()->profitLoss((int) $vendor->shop->id, $from, $to);

        $this->assertSame(1, $pl['orders']);
        $this->assertEquals(100000.0, $pl['gross']);
        $this->assertEquals(11000.0, $pl['tax']);
        $this->assertEquals(10000.0, $pl['commission']);
        $this->assertEquals(101000.0, $pl['net']);
        $this->assertCount(1, $pl['rows']);

        $csv = $this->finance()->accountingCsv((int) $vendor->shop->id, $from, $to);
        $lines = explode("\n", trim($csv));
        $this->assertStringContainsString('Nomor Pesanan', $lines[0]);
        $this->assertStringContainsString('TOTAL', $csv);
        $this->assertStringContainsString('101000', $csv);

        // L/R admin memakai hitungan yang sama.
        $adminPl = app(FinanceAdminService::class)->shopProfitLoss((int) $vendor->shop->id, $from, $to);
        $this->assertEquals($pl['net'], $adminPl['net']);
    }

    public function test_command_kirim_settlement_otomatis(): void
    {
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $this->makeOrder($customer, $vendor->shop);

        $this->artisan('finance:kirim-settlement', [
            '--from' => now()->startOfMonth()->format('Y-m-d'),
            '--to' => now()->endOfMonth()->format('Y-m-d'),
        ])->assertSuccessful();

        $this->assertSame(1, DB::table('settlement_batches')->count());

        $this->artisan('finance:kirim-settlement', ['--dry-run' => true])->assertSuccessful();
    }
}
