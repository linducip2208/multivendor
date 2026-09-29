<?php

namespace Tests\Feature;

use Database\Seeders\Support\DemoPhotoFetcher;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DemoPhotoFetcherTest extends TestCase
{
    public function test_keyword_terpetakan(): void
    {
        $this->assertSame('sneakers shoes', DemoPhotoFetcher::keywordFor('Sepatu Nike Air', 'fashion-pria', 'Pria'));
        $this->assertSame('smartphone', DemoPhotoFetcher::keywordFor('HP Xiaomi', 'smartphone', 'Smartphone'));
        $this->assertSame('wristwatch', DemoPhotoFetcher::keywordFor('Jam Casio', 'fashion-pria', 'Pria'));
        $this->assertSame('product', DemoPhotoFetcher::keywordFor('Barang Xyz Qqq', 'kategori-aneh', 'Aneh'));
    }

    public function test_fetch_mengunduh_dan_mencatat_atribusi(): void
    {
        $body = str_repeat('x', 20000);

        Http::fake([
            'commons.wikimedia.org/*' => Http::response([
                'query' => ['pages' => [
                    ['index' => 1, 'title' => 'File:Test shoe.jpg', 'imageinfo' => [[
                        'thumburl' => 'https://thumb.wikimedia.org/test-shoe.jpg?x=1',
                        'thumbwidth' => 800, 'user' => 'Tester',
                        'descriptionurl' => 'https://commons.wikimedia.org/wiki/File:Test_shoe.jpg',
                    ]]],
                ]],
            ], 200),
            'thumb.wikimedia.org/*' => Http::response($body, 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $paths = DemoPhotoFetcher::fetch('unit-test-sepatu-xyz', 1);

        $this->assertCount(1, $paths);
        $full = storage_path('app/public/'.$paths[0]);
        $this->assertFileExists($full);
        $this->assertGreaterThan(10240, filesize($full));

        $manifest = json_decode((string) file_get_contents(
            storage_path('app/public/products/photo/attribution.json')
        ), true);
        $this->assertSame('Tester', $manifest[$paths[0]]['author'] ?? null);

        @unlink($full);
    }

    public function test_fetch_gagal_mengembalikan_kosong(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $this->assertSame([], DemoPhotoFetcher::fetch('unit-test-gagal-xyz', 2));
    }
}
