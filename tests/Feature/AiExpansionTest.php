<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Search\QueryParser;
use App\Search\SynonymRepository;
use App\Services\Ai\AdminPrompts;
use App\Services\Ai\BalasanChat;
use App\Services\Ai\DeskripsiProduk;
use App\Services\Ai\PencarianCerdas;
use App\Services\Ai\RingkasanUlasan;
use App\Services\Backoffice\CopilotService;
use App\Services\Crm\ConversationService;
use App\Services\Vendor\VendorAiService;
use App\Services\Vendor\VendorChatService;
use App\Services\Vendor\VendorScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Otomatisasi AI di atas layanan existing: deskripsi produk, saran balasan
 * chat, ringkasan ulasan, pencarian cerdas. Semua harus aman (fallback lokal)
 * bila kredensial AI kosong.
 */
class AiExpansionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deskripsi_produk_fallback_tanpa_provider(): void
    {
        $hasil = (new DeskripsiProduk)->generate('Sepatu Lari Velocity', [
            'kategori' => 'Sepatu Olahraga',
            'warna' => 'Hitam',
            'bahan' => 'Mesh',
        ], null);

        $this->assertSame('fallback', $hasil['source']);
        $this->assertStringContainsString('Sepatu Lari Velocity', $hasil['judul_seo']);
        $this->assertLessThanOrEqual(60, mb_strlen($hasil['judul_seo']));
        $this->assertStringContainsString('Sepatu Lari Velocity', $hasil['deskripsi']);
        $this->assertLessThanOrEqual(160, mb_strlen($hasil['meta_description']));
        $this->assertNotSame('', trim($hasil['deskripsi']));
    }

    public function test_balasan_chat_tiga_opsi_dari_konteks_order(): void
    {
        $hasil = (new BalasanChat)->suggest('Kapan paket saya dikirim?', [
            'nama_pelanggan' => 'Budi',
            'nomor_pesanan' => 'ORD-123',
            'status_pesanan' => 'processing',
        ], null);

        $this->assertSame('fallback', $hasil['source']);
        $this->assertSame('pengiriman', $hasil['topik']);
        $this->assertCount(3, $hasil['options']);
        $this->assertStringContainsString('ORD-123', $hasil['options'][0]);
    }

    public function test_ringkasan_ulasan_agregat_pro_kontra(): void
    {
        $hasil = (new RingkasanUlasan)->summarize([
            ['rating' => 5, 'comment' => 'Barang bagus, pengiriman cepat, kemasan rapi. Puas!'],
            ['rating' => 4, 'comment' => 'Kualitas awet dan harga murah, recommended.'],
            ['rating' => 2, 'comment' => 'Kecewa, pengiriman lama dan kemasan penyek.'],
        ], 'Tas Ransel', null);

        $this->assertSame('fallback', $hasil['source']);
        $this->assertSame(3, $hasil['total']);
        $this->assertEqualsWithDelta(3.7, $hasil['rata_rata'], 0.1);
        $this->assertSame(1, $hasil['distribusi'][5]);
        $this->assertSame(1, $hasil['distribusi'][4]);
        $this->assertSame(1, $hasil['distribusi'][2]);
        $this->assertNotEmpty($hasil['pro']);
        $this->assertNotEmpty($hasil['kontra']);
        $this->assertStringContainsString('3 ulasan', $hasil['ringkasan']);
    }

    public function test_ringkasan_ulasan_kosong_tetap_aman(): void
    {
        $hasil = (new RingkasanUlasan)->summarize([], 'Produk X', null);

        $this->assertSame(0, $hasil['total']);
        $this->assertSame(0.0, $hasil['rata_rata']);
        $this->assertSame([], $hasil['pro']);
        $this->assertStringContainsString('Belum ada ulasan', $hasil['ringkasan']);
    }

    public function test_pencarian_cerdas_koreksi_dan_ekspansi(): void
    {
        $this->assertSame('sepatu', QueryParser::koreksiEjaan('seaptu'));
        $this->assertSame('handphone', QueryParser::koreksiEjaan('handpone'));

        $smart = QueryParser::parseSmart('seaptu');
        $this->assertSame('sepatu', $smart['corrected']);
        $this->assertNotEmpty($smart['koreksi']);
        $this->assertContains('sepatu', $smart['expanded']);
        $this->assertContains('sneaker', $smart['expanded']);

        $ekspansi = SynonymRepository::expandSmart('hp');
        $this->assertContains('handphone', $ekspansi);

        $hasil = (new PencarianCerdas)->enrich('seaptu hp');
        $this->assertTrue($hasil['dikoreksi']);
        $this->assertContains('handphone', $hasil['expanded']);
        $this->assertNotEmpty((new PencarianCerdas)->suggestions('seaptu'));
    }

    public function test_vendor_ai_generate_fallback_tanpa_provider(): void
    {
        [$vendor] = $this->seedVendorShop();

        $this->actingAs($vendor);

        $ai = app(VendorAiService::class);

        $deskripsi = $ai->generate('product_description', ['tone' => 'ramah']);
        $this->assertStringContainsString('Produk', $deskripsi);

        $balasan = $ai->generate('customer_reply', ['message' => 'Stok masih ada?']);
        $this->assertNotSame('', trim($balasan));

        $saran = $ai->suggestChatReplies('Paket kapan sampai?', null);
        $this->assertCount(3, $saran['options']);
        $this->assertSame('fallback', $saran['source']);

        $copy = $ai->describeProduct(null, ['nama' => 'Kopi Gayo']);
        $this->assertSame('fallback', $copy['source']);
        $this->assertStringContainsString('Kopi Gayo', $copy['judul_seo']);
    }

    public function test_saran_balasan_chat_tidak_mengubah_pesan(): void
    {
        [$vendor, $shop, $customer] = $this->seedVendorShop();

        $percakapan = Conversation::create([
            'uuid' => (string) Str::uuid(),
            'type' => 'support',
            'shop_id' => $shop->id,
            'subject' => 'Tanya kiriman',
            'status' => 'open',
        ]);

        ConversationParticipant::create(['conversation_id' => $percakapan->id, 'user_id' => $vendor->id, 'role' => 'vendor']);
        ConversationParticipant::create(['conversation_id' => $percakapan->id, 'user_id' => $customer->id, 'role' => 'customer']);

        Message::create([
            'uuid' => (string) Str::uuid(),
            'conversation_id' => $percakapan->id,
            'user_id' => $customer->id,
            'body' => 'Kak, resi pengiriman mana ya?',
        ]);

        $this->actingAs($vendor);

        $sebelum = Message::where('conversation_id', $percakapan->id)->count();

        $hasil = app(VendorChatService::class)->saranBalasan($percakapan->fresh());
        $this->assertCount(3, $hasil['options']);
        $this->assertSame('pengiriman', $hasil['topik']);
        $this->assertSame($sebelum, Message::where('conversation_id', $percakapan->id)->count());

        $admin = app(ConversationService::class)->saranBalasan($percakapan->fresh());
        $this->assertCount(3, $admin['options']);
        $this->assertSame($sebelum, Message::where('conversation_id', $percakapan->id)->count());
    }

    public function test_copilot_dan_prompts_mendukung_fitur_baru(): void
    {
        $katalog = AdminPrompts::catalogue();

        foreach (['product_copy', 'chat_assist', 'review_summary', 'smart_search'] as $tugas) {
            $this->assertArrayHasKey($tugas, $katalog);
            $this->assertStringContainsString('tidak pernah memindahkan uang', $katalog[$tugas]['system']);
        }

        $copilot = app(CopilotService::class);

        $this->assertFalse($copilot->isAiReady());
        $this->assertNull($copilot->defaultProvider());

        $copy = $copilot->productCopy('Kopi Gayo', ['kategori' => 'Minuman']);
        $this->assertSame('fallback', $copy['source']);

        $ringkasan = $copilot->reviewSummary([['rating' => 5, 'comment' => 'Bagus dan cepat']], 'Kopi');
        $this->assertSame(1, $ringkasan['total']);

        $cari = $copilot->smartSearch('seaptu');
        $this->assertSame('sepatu', $cari['corrected']);
    }

    /**
     * @return array{0: User, 1: Shop, 2: User}
     */
    private function seedVendorShop(): array
    {
        $vendor = User::create([
            'name' => 'Vendor AI', 'email' => 'vendor-ai@t.co',
            'password' => Hash::make('x'), 'role' => 'vendor', 'status' => 'active',
        ]);

        $shop = Shop::create([
            'vendor_id' => $vendor->id, 'name' => 'Toko AI', 'slug' => 'toko-ai',
            'commission_type' => 'percentage', 'commission_value' => 5, 'status' => 'active',
        ]);

        $customer = User::create([
            'name' => 'Pelanggan', 'email' => 'customer-ai@t.co',
            'password' => Hash::make('x'), 'role' => 'customer', 'status' => 'active',
        ]);

        Category::create(['name' => 'Umum AI', 'slug' => 'umum-ai', 'status' => true]);

        Product::create([
            'shop_id' => $shop->id,
            'category_id' => Category::where('slug', 'umum-ai')->first()->id,
            'name' => 'Produk AI', 'slug' => 'produk-ai',
            'price' => 50000, 'current_stock' => 10,
            'status' => 'approved', 'published' => true,
        ]);

        return [$vendor, $shop, $customer];
    }
}
