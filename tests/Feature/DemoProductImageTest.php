<?php

namespace Tests\Feature;

use Database\Seeders\Support\DemoProductImage;
use Tests\TestCase;

class DemoProductImageTest extends TestCase
{
    public function test_resolve_memetakan_kategori_dan_nama(): void
    {
        $this->assertSame('shoe', DemoProductImage::resolve('Sepatu Nike Air Max', 'fashion-pria', 'Pria')[0]);
        $this->assertSame('phone', DemoProductImage::resolve(' apa pun', 'smartphone', 'Smartphone')[0]);
        $this->assertSame('laptop', DemoProductImage::resolve('Asus Vivobook', 'laptop', 'Laptop')[0]);
        $this->assertSame('book', DemoProductImage::resolve('Novel Best Seller', 'buku', 'Buku')[0]);
        $this->assertSame('ball', DemoProductImage::resolve('Bola Futsal', 'olahraga', 'Olahraga')[0]);
        $this->assertSame('box', DemoProductImage::resolve('Barang Misterius Xyz', 'kategori-aneh', 'Aneh')[0]);
    }

    public function test_generate_menulis_svg_valid(): void
    {
        $path = DemoProductImage::forProduct(
            'Nike Air Zoom Pegasus',
            'Nike',
            'fashion-pria',
            'Pria',
            'test-sepatu-nike',
            0
        );

        $full = storage_path('app/public/'.$path);
        $this->assertFileExists($full);

        $svg = (string) file_get_contents($full);
        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('Nike Air Zoom', $svg);
        $this->assertStringContainsString('Nike', $svg);
        $this->assertStringNotContainsString('<script', strtolower($svg));

        // Bersihkan artefak test.
        @unlink($full);
    }

    public function test_varian_menghasilkan_berkas_berbeda(): void
    {
        $a = DemoProductImage::forProduct('Kopi Robusta', 'Kapal Api', 'makanan', 'Makanan', 'test-kopi', 0);
        $b = DemoProductImage::forProduct('Kopi Robusta', 'Kapal Api', 'makanan', 'Makanan', 'test-kopi', 1);
        $this->assertNotSame($a, $b);

        @unlink(storage_path('app/public/'.$a));
        @unlink(storage_path('app/public/'.$b));
    }
}
