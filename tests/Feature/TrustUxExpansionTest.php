<?php

namespace Tests\Feature;

use App\Services\Kepercayaan\SkorToko;
use App\Services\Vendor\VendorRegistrationService;
use Tests\TestCase;

/**
 * Perdalaman kepercayaan & UX existing (tanpa migrasi/route baru).
 *
 * Mencakup: KYC bertahap, skor toko publik, galeri video, normalisasi Q&A,
 * kelengkapan lang id/en, dan keberadaan switcher bahasa di header.
 */
class TrustUxExpansionTest extends TestCase
{
    private SkorToko $skor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skor = new SkorToko();
    }

    public function test_kyc_bertahap_email_identitas_rekening_verifikasi(): void
    {
        $dasar = (object) ['id' => null, 'email' => 'toko@uji.id', 'status' => 'pending', 'bank_name' => null, 'bank_account_number' => null, 'bank_account_name' => null];
        $r = $this->skor->kyc($dasar, []);
        $this->assertSame('identitas', $r['level']);
        $this->assertSame(25, $r['percent']);
        $this->assertCount(4, $r['steps']);

        $lengkap = (object) ['id' => null, 'email' => 'toko@uji.id', 'status' => 'approved', 'bank_name' => 'BCA', 'bank_account_number' => '123456', 'bank_account_name' => 'Toko'];
        $r2 = $this->skor->kyc($lengkap, [['kind' => 'identity']]);
        $this->assertSame(100, $r2['percent']);
        $this->assertSame('verifikasi', $r2['level']);
        $this->assertTrue(collect($r2['steps'])->every(fn ($s) => $s['done']));
    }

    public function test_kyc_dokumen_identitas_menaikkan_level(): void
    {
        $app = (object) ['id' => null, 'email' => 't@uji.id', 'status' => 'pending', 'bank_name' => null, 'bank_account_number' => null, 'bank_account_name' => null];
        $tanpa = $this->skor->kyc($app, []);
        $dengan = $this->skor->kyc($app, [['kind' => 'selfie']]);
        $this->assertFalse($tanpa['steps'][1]['done']);
        $this->assertTrue($dengan['steps'][1]['done']);
        $this->assertSame(50, $dengan['percent']);
    }

    public function test_skor_toko_terbobot_dan_terbatas_0_100(): void
    {
        $penuh = $this->skor->skor(['rating' => 5.0, 'fulfillment_rate' => 100, 'response_rate' => 100, 'umur_bulan' => 24]);
        $this->assertSame(100, $penuh['skor']);
        $this->assertSame('Sangat Terpercaya', $penuh['label']);
        $this->assertSame('success', $penuh['badge']);
        $this->assertSame(30.0, $penuh['rincian']['rating']);

        $kosong = $this->skor->skor([]);
        $this->assertSame(0, $kosong['skor']);
        $this->assertSame('Toko Baru', $kosong['label']);

        // Bobot: rating 30 + fulfillment 30 + respons 25 + umur 15.
        $parsial = $this->skor->skor(['rating' => 4.0, 'fulfillment_rate' => 80, 'response_rate' => 60, 'umur_bulan' => 6]);
        $this->assertEqualsWithDelta(24.0, $parsial['rincian']['rating'], 0.01);
        $this->assertEqualsWithDelta(24.0, $parsial['rincian']['fulfillment'], 0.01);
        $this->assertEqualsWithDelta(15.0, $parsial['rincian']['respons'], 0.01);
        $this->assertEqualsWithDelta(7.5, $parsial['rincian']['umur_toko'], 0.01);
        $this->assertSame(71, $parsial['skor']); // 24+24+15+7.5 = 70.5 → 71
        $this->assertSame('Terpercaya', $parsial['label']);
    }

    public function test_video_url_tunggal_multi_dan_youtube(): void
    {
        $tunggal = (object) ['video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'];
        $v = SkorToko::videos($tunggal);
        $this->assertCount(1, $v);
        $this->assertSame('youtube', $v[0]['kind']);
        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', $v[0]['embed']);

        $multi = (object) ['video_url' => "https://youtu.be/dQw4w9WgXcQ, videos/demo.mp4\nvideos/demo.mp4"];
        $m = SkorToko::videos($multi);
        $this->assertCount(2, $m); // duplikat path dibuang

        $json = (object) ['video_url' => json_encode(['https://youtu.be/abc123XYZ-_', 'videos/a.mp4'])];
        $this->assertCount(2, SkorToko::videos($json));

        $kosong = (object) ['video_url' => null];
        $this->assertSame([], SkorToko::videos($kosong));
    }

    public function test_normalisasi_qa_voting_dan_terjawab(): void
    {
        $items = [
            ['q' => 'Apakah ori?', 'a' => 'Ya, garansi resmi.', 'votes' => 3],
            ['question' => 'Stok?', 'answer' => null, 'helpful' => 0],
            ['q' => '', 'a' => 'tanpa pertanyaan dilewat'],
        ];
        $r = SkorToko::normalisasiQa($items);
        $this->assertCount(2, $r);
        $this->assertTrue($r[0]['answered']);
        $this->assertTrue($r[0]['seller']);
        $this->assertSame(3, $r[0]['votes']);
        $this->assertFalse($r[1]['answered']);
        $this->assertSame('qa-2', $r[1]['id']);
    }

    public function test_vendor_registration_service_menyediakan_kyc(): void
    {
        $this->assertTrue(method_exists(VendorRegistrationService::class, 'kycProgress'));
        $svc = app(VendorRegistrationService::class);
        $app = (object) ['id' => null, 'email' => 'x@uji.id', 'status' => 'pending', 'bank_name' => null, 'bank_account_number' => null, 'bank_account_name' => null];
        $r = $svc->kycProgress($app, []);
        $this->assertArrayHasKey('percent', $r);
        $this->assertArrayHasKey('steps', $r);
    }

    public function test_lang_id_en_lengkap_untuk_kunci_kepercayaan(): void
    {
        $id = json_decode((string) file_get_contents(lang_path('id.json')), true);
        $en = json_decode((string) file_get_contents(lang_path('en.json')), true);
        foreach (['Trust score', 'Answered by seller', 'Awaiting answer', 'Helpful', 'Product videos', 'Step-by-step verification (KYC)'] as $key) {
            $this->assertArrayHasKey($key, $id, "id.json kurang: {$key}");
            $this->assertArrayHasKey($key, $en, "en.json kurang: {$key}");
        }
        // Copy BI: en.json wajib berbahasa Inggris (bukan salinan Indonesia).
        $this->assertNotSame($id['Trust score'], $en['Trust score']);
        $this->assertSame('Trust score', $en['Trust score']);
    }

    public function test_header_memiliki_switcher_bahasa(): void
    {
        $html = (string) file_get_contents(resource_path('views/components/storefront/header.blade.php'));
        $this->assertStringContainsString('language-switcher', $html);

        $head = (string) file_get_contents(resource_path('views/components/seo/head.blade.php'));
        $this->assertStringContainsString('hreflang', $head);
    }
}
