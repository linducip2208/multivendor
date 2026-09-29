<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliateClick;
use App\Models\Category;
use App\Models\LoyaltyPoint;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\SocialFeed;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Wishlist;
use App\Services\Loyalitas\MisiHarian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SocialExpansionTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $vendor;

    private Shop $shop;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::create([
            'name' => 'Pelanggan Sosial', 'email' => 'sosial-'.uniqid().'@uji.id',
            'password' => Hash::make('password'), 'role' => 'customer', 'status' => 'active',
            'referral_code' => 'REF'.strtoupper(uniqid()),
        ]);
        Wallet::create(['user_id' => $this->customer->id, 'balance' => 0]);

        $this->vendor = User::create([
            'name' => 'Vendor Sosial', 'email' => 'vendor-sosial-'.uniqid().'@uji.id',
            'password' => Hash::make('password'), 'role' => 'vendor', 'status' => 'active',
        ]);

        $this->shop = Shop::create([
            'vendor_id' => $this->vendor->id, 'name' => 'Toko Sosial',
            'slug' => 'toko-sosial-'.uniqid(), 'status' => 'active',
        ]);

        $category = Category::create(['name' => 'Kat Sosial', 'slug' => 'kat-sosial-'.uniqid(), 'status' => true]);

        $this->product = Product::create([
            'shop_id' => $this->shop->id, 'category_id' => $category->id,
            'name' => 'Produk Sosial', 'slug' => 'produk-sosial-'.uniqid(),
            'price' => 120000, 'special_price' => 99000, 'current_stock' => 25,
            'status' => 'approved', 'published' => true,
        ]);
    }

    public function test_misi_harian_klaim_masuk_transaksi_loyalitas(): void
    {
        $misi = app(MisiHarian::class);

        $status = $misi->statusFor($this->customer);
        $this->assertCount(5, $status);
        $keys = array_column($status, 'key');
        $this->assertContains('login', $keys);
        $this->assertContains('checkin', $keys);
        $this->assertContains('review', $keys);
        $this->assertContains('share', $keys);
        $this->assertContains('belanja', $keys);

        $hasil = $misi->claim($this->customer, 'login');
        $this->assertTrue($hasil['ok']);
        $this->assertSame(5, $hasil['poin']);
        $this->assertSame(5, (int) LoyaltyPoint::where('customer_id', $this->customer->id)->value('points'));
        $this->assertDatabaseHas('loyalty_transactions', [
            'customer_id' => $this->customer->id,
            'reference_type' => 'misi_harian:login',
        ]);

        // Klaim kedua di hari yang sama ditolak (idempoten).
        $ulang = $misi->claim($this->customer, 'login');
        $this->assertFalse($ulang['ok']);
        $this->assertSame(5, (int) LoyaltyPoint::where('customer_id', $this->customer->id)->value('points'));
    }

    public function test_checkin_streak_bertambah_dan_bonus_naik(): void
    {
        $misi = app(MisiHarian::class);

        // Simulasi check-in 2 hari lalu + kemarin via transaksi existing.
        foreach ([2, 1] as $mundur) {
            $tgl = now()->subDays($mundur);
            LoyaltyPoint::earn($this->customer, 10, 'Check-in simulasi', 'checkin', (int) $tgl->format('Ymd'));
            LoyaltyTransaction::where('customer_id', $this->customer->id)
                ->where('reference_type', 'checkin')->latest('id')->first()
                ?->update(['created_at' => $tgl, 'updated_at' => $tgl]);
        }

        $this->assertSame(2, $misi->streak($this->customer));

        $hasil = $misi->checkin($this->customer);
        $this->assertTrue($hasil['ok']);
        $this->assertSame(3, $hasil['streak']);
        // Basis 10 + bonus (3-1)*2 = 14.
        $this->assertSame(14, $hasil['poin']);

        $dobel = $misi->checkin($this->customer);
        $this->assertFalse($dobel['ok']);
    }

    public function test_misi_belanja_butuh_order_dan_review_butuh_ulasan(): void
    {
        $misi = app(MisiHarian::class);

        $belanja = $misi->claim($this->customer, 'belanja');
        $this->assertFalse($belanja['ok']);

        Order::create([
            'order_number' => Order::generateOrderNumber(), 'customer_id' => $this->customer->id,
            'shop_id' => $this->shop->id, 'sub_total' => 99000, 'total' => 99000,
            'payment_status' => 'paid', 'order_status' => 'paid',
        ]);

        $belanjaOk = $misi->claim($this->customer, 'belanja');
        $this->assertTrue($belanjaOk['ok']);
        $this->assertSame(25, $belanjaOk['poin']);

        $review = $misi->claim($this->customer, 'review');
        $this->assertFalse($review['ok']);

        \App\Models\ProductReview::create([
            'product_id' => $this->product->id, 'customer_id' => $this->customer->id,
            'rating' => 5, 'comment' => 'Bagus!', 'status' => true,
        ]);

        $reviewOk = $misi->claim($this->customer, 'review');
        $this->assertTrue($reviewOk['ok']);
        $this->assertSame(15, $reviewOk['poin']);
    }

    public function test_afiliasi_klik_komisi_dan_payout_wallet(): void
    {
        $pemilik = User::create([
            'name' => 'Afiliasi', 'email' => 'afiliasi-'.uniqid().'@uji.id',
            'password' => Hash::make('password'), 'role' => 'customer', 'status' => 'active',
        ]);
        Wallet::create(['user_id' => $pemilik->id, 'balance' => 0]);

        $afiliasi = Affiliate::create([
            'user_id' => $pemilik->id, 'code' => 'SOS'.strtoupper(uniqid()),
            'name' => 'Afiliasi Uji', 'email' => $pemilik->email,
            'status' => 'active', 'commission_rate' => 10,
        ]);

        // 1. Tautan + klik tracking.
        $this->assertStringContainsString($afiliasi->code, $afiliasi->referralLink());
        $klik = $afiliasi->recordClick(['customer_id' => $this->customer->id, 'landing_path' => '/products', 'ip_address' => '127.0.0.1']);
        $this->assertInstanceOf(AffiliateClick::class, $klik);
        $this->assertSame($afiliasi->id, (int) $klik->affiliate_id);

        // 2. Komisi otomatis dari order via referral_code (dibawa kolom coupon_code).
        $order = Order::create([
            'order_number' => Order::generateOrderNumber(), 'customer_id' => $this->customer->id,
            'shop_id' => $this->shop->id, 'coupon_code' => $afiliasi->code,
            'sub_total' => 100000, 'total' => 100000,
            'payment_status' => 'paid', 'order_status' => 'paid',
        ]);

        $this->assertTrue($afiliasi->attributeOrder($order));
        $afiliasi->refresh();
        $this->assertSame(1, (int) $afiliasi->total_orders);
        $this->assertEquals(100000, (float) $afiliasi->total_revenue);
        $this->assertEquals(10000, (float) $afiliasi->total_commission);
        $this->assertTrue($klik->fresh()->isConverted());

        // Idempoten: order yang sama tidak dihitung dua kali.
        $this->assertFalse($afiliasi->fresh()->attributeOrder($order));

        // 3. Payout via wallet existing (idempoten via reference_key).
        $tx = $afiliasi->fresh()->payoutToWallet(10000, 'uji-payout-'.$afiliasi->id);
        $this->assertNotNull($tx);
        $this->assertEquals(10000, (float) Wallet::where('user_id', $pemilik->id)->value('balance'));
        $dobel = $afiliasi->fresh()->payoutToWallet(10000, 'uji-payout-'.$afiliasi->id);
        $this->assertSame($tx->id, $dobel->id);

        // 4. Leaderboard memuat afiliasi.
        $papan = Affiliate::leaderboard(5);
        $this->assertNotEmpty($papan);
        $this->assertContains($afiliasi->code, array_column($papan, 'code'));
    }

    public function test_feed_shoppable_menampilkan_harga_dan_tombol_beli(): void
    {
        SocialFeed::create([
            'product_id' => $this->product->id, 'shop_id' => $this->shop->id,
            'caption' => 'Racun belanja hari ini!', 'is_active' => true, 'views' => 10, 'likes' => 3,
        ]);

        $response = $this->actingAs($this->customer)->get(route('feed'));
        $response->assertStatus(200);
        $response->assertSee('Racun belanja hari ini!');
        $response->assertSee('Produk Sosial');
        // Harga efektif tampil + tombol beli (form cart.add).
        $response->assertSee('Rp 99,000', false);
        $response->assertSee('Rp 120,000', false);
        $response->assertSee('Stok 25');
        $response->assertSee(route('cart.add'), false);
    }

    public function test_koleksi_wishlist_folder_dan_tautan_berbagi(): void
    {
        $this->actingAs($this->customer);
        Wishlist::create(['customer_id' => $this->customer->id, 'product_id' => $this->product->id]);

        $kontrol = app(\App\Http\Controllers\Storefront\AccountController::class);
        $koleksi = $kontrol->koleksiBerbagiData();
        $this->assertNotEmpty($koleksi);
        $this->assertArrayHasKey('share_url', $koleksi[0]);
        $this->assertStringContainsString('koleksi=', $koleksi[0]['share_url']);

        $misi = $kontrol->misiHarianData();
        $this->assertArrayHasKey('missions', $misi);
        $this->assertArrayHasKey('streak', $misi);
        $this->assertCount(5, $misi['missions']);

        $response = $this->get(route('account.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Misi harian');
        $response->assertSee('Bagikan koleksi wishlist');
    }
}
