<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\FileManagerController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Media library + picker terpusat (self-contained).
 *
 * Memakai RefreshDatabase (migrasi bawaan) + Storage::fake('public')
 * sehingga tidak menyentuh berkas asli. Method JSON controller dipanggil
 * langsung karena route JSON baru belum di-wiring (disengaja).
 */
class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_1PX = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /** @var list<string> berkas nyata yang wajib dibersihkan */
    private array $realFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'super-admin@media.test',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'admin');
    }

    protected function tearDown(): void
    {
        foreach ($this->realFiles as $path) {
            File::delete(storage_path('app/public/'.$path));
        }

        parent::tearDown();
    }

    public function test_library_json_mendukung_cari_dan_filter_tipe(): void
    {
        $disk = Storage::disk('public');
        $disk->put('uploads/foto-pantai.png', (string) base64_decode(self::PNG_1PX));
        $disk->put('uploads/katalog.pdf', "%PDF-1.4\n%uji\n");
        $disk->put('uploads/sub/foto-gunung.png', (string) base64_decode(self::PNG_1PX));

        $controller = new FileManagerController();

        // Semua (rekursif: termasuk sub/).
        $all = $controller->libraryJson(Request::create('/x', 'GET', ['type' => 'all']));
        $this->assertSame(200, $all->getStatusCode());
        $payload = $all->getData(true);
        $this->assertCount(3, $payload['data']);
        $this->assertSame('uploads', $payload['meta']['root']);
        foreach ($payload['data'] as $item) {
            $this->assertStringContainsString('/img/', $item['url']);
            $this->assertStringContainsString('/img/uploads/', $item['url']);
        }

        // Cari nama.
        $found = $controller->libraryJson(Request::create('/x', 'GET', ['q' => 'pantai']));
        $this->assertCount(1, $found->getData(true)['data']);
        $this->assertSame('foto-pantai.png', $found->getData(true)['data'][0]['name']);

        // Filter gambar saja.
        $images = $controller->libraryJson(Request::create('/x', 'GET', ['type' => 'image']));
        $this->assertCount(2, $images->getData(true)['data']);
        foreach ($images->getData(true)['data'] as $item) {
            $this->assertTrue($item['is_image']);
            $this->assertNotNull($item['thumb']);
        }

        // Filter dokumen saja.
        $docs = $controller->libraryJson(Request::create('/x', 'GET', ['type' => 'document']));
        $this->assertCount(1, $docs->getData(true)['data']);
        $this->assertSame('application/pdf', $docs->getData(true)['data'][0]['mime']);
    }

    public function test_library_json_menolak_traversal(): void
    {
        $controller = new FileManagerController();

        foreach (['../rahasia', '..\\rahasia', '/etc', 'uploads/../../x'] as $jahat) {
            $res = $controller->libraryJson(Request::create('/x', 'GET', ['dir' => $jahat]));
            $this->assertSame(400, $res->getStatusCode(), 'dir: '.$jahat);
        }

        // Tipe tak dikenal ditolak validasi.
        $this->expectException(ValidationException::class);
        $controller->libraryJson(Request::create('/x', 'GET', ['type' => 'executable']));
    }

    public function test_make_folder_membuat_dan_memvalidasi_nama(): void
    {
        $controller = new FileManagerController();

        $ok = $controller->makeFolder(Request::create('/x', 'POST', ['name' => 'banner-promo']));
        $this->assertSame(201, $ok->getStatusCode());
        $this->assertTrue(Storage::disk('public')->exists('uploads/banner-promo'));

        // Duplikat → 409.
        $dup = $controller->makeFolder(Request::create('/x', 'POST', ['name' => 'banner-promo']));
        $this->assertSame(409, $dup->getStatusCode());

        foreach (['../kabur', 'a/b', 'x.php', '.hidden', 'con'] as $buruk) {
            $res = $controller->makeFolder(Request::create('/x', 'POST', ['name' => $buruk]));
            $this->assertSame(422, $res->getStatusCode(), 'nama: '.$buruk);
        }

        // Nama kosong ditolak validasi Laravel.
        try {
            $controller->makeFolder(Request::create('/x', 'POST', ['name' => '']));
            $this->fail('Nama kosong seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->errors());
        }

        // Induk traversal ditolak (422 JSON dari controller).
        $induk = $controller->makeFolder(Request::create('/x', 'POST', ['name' => 'ok', 'parent' => '../../x']));
        $this->assertSame(422, $induk->getStatusCode());
    }

    public function test_upload_media_menerima_gambar_valid(): void
    {
        $file = $this->makeUploadedFile('foto-bagus.png', (string) base64_decode(self::PNG_1PX), 'image/png');

        $controller = new FileManagerController();
        $res = $controller->uploadMedia(Request::create('/x', 'POST', [], [], ['file' => $file]));

        $this->assertSame(201, $res->getStatusCode());
        $payload = $res->getData(true);
        $this->assertStringStartsWith('uploads/', $payload['path']);
        $this->assertStringContainsString('/img/uploads/', $payload['url']);

        $this->realFiles[] = $payload['path'];
        $this->assertFileExists(storage_path('app/public/'.str_replace('/', DIRECTORY_SEPARATOR, $payload['path'])));
    }

    public function test_upload_media_menolak_double_extension_dan_svg_aktif(): void
    {
        $controller = new FileManagerController();

        // Double extension: konten PNG valid, nama foto.php.png → 422.
        $ganda = $this->makeUploadedFile('foto.php.png', (string) base64_decode(self::PNG_1PX), 'image/png');
        $res = $controller->uploadMedia(Request::create('/x', 'POST', [], [], ['file' => $ganda]));
        $this->assertSame(422, $res->getStatusCode());
        $this->assertStringContainsString('double-extension', (string) $res->getContent());

        // SVG berisi skrip → 422 di salah satu lapisan.
        $svg = $this->makeUploadedFile('vektor.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'image/svg+xml');
        $buruk = $controller->uploadMedia(Request::create('/x', 'POST', [], [], ['file' => $svg]));
        $this->assertSame(422, $buruk->getStatusCode());
    }

    public function test_upload_media_menolak_berkas_terlalu_besar(): void
    {
        $this->assertSame(5120, FileManagerController::mediaMaxKb());
        $this->assertStringContainsString('max:5120', implode('|', FileManagerController::mediaRules()['file']));

        $besar = UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf');

        $this->expectException(ValidationException::class);
        (new FileManagerController())->uploadMedia(Request::create('/x', 'POST', [], [], ['file' => $besar]));
    }

    public function test_helper_nama_berisiko_folder_dir_dan_url(): void
    {
        // Nama berisiko.
        foreach (['shell.php', 'foto.php.jpg', 'dok.pdf.exe', 'a.html', 'run.sh', 'tanpa-ekstensi', 'a..b.png'] as $nama) {
            $this->assertTrue(FileManagerController::hasRiskyName($nama), $nama);
        }
        foreach (['foto.jpg', 'gambar.PNG', 'animasi.webp', 'vektor.svg', 'katalog.pdf'] as $nama) {
            $this->assertFalse(FileManagerController::hasRiskyName($nama), $nama);
        }

        // Folder.
        $this->assertSame('banner-promo', FileManagerController::sanitizeFolderName('banner-promo'));
        $this->assertNull(FileManagerController::sanitizeFolderName('../kabur'));
        $this->assertNull(FileManagerController::sanitizeFolderName(''));

        // Direktori.
        $this->assertSame('', FileManagerController::sanitizeDir(''));
        $this->assertSame('promo/2026', FileManagerController::sanitizeDir('promo/2026'));
        $this->assertNull(FileManagerController::sanitizeDir('../../etc'));

        // MIME + URL.
        $this->assertTrue(FileManagerController::isImageMime('image/webp'));
        $this->assertFalse(FileManagerController::isImageMime('application/pdf'));
        $this->assertContains('image/jpeg', FileManagerController::mediaAllowedMimes());
        $this->assertContains('application/pdf', FileManagerController::mediaAllowedMimes());
        $this->assertStringContainsString('/img/uploads/foto.png', FileManagerController::toImgUrl('uploads/foto.png'));
    }

    public function test_komponen_picker_terrender_dengan_modal_dan_js_inline(): void
    {
        $html = Blade::render('<x-admin.media-picker target="banner-image" preview="banner-image-preview" />');

        $this->assertStringContainsString('Pilih dari Media', $html);
        $this->assertStringContainsString('media-picker-banner-image', $html);
        $this->assertStringContainsString('banner-image-preview', $html);
        // @json meng-escape slash menjadi \/ — terima kedua bentuk.
        $this->assertStringContainsString('file-manager', $html);
        $this->assertStringContainsString('library', $html);
        $this->assertStringContainsString('data-media-pick', $html);
    }

    public function test_halaman_file_manager_memuat_browser_media(): void
    {
        Storage::disk('public')->put('uploads/contoh.png', (string) base64_decode(self::PNG_1PX));

        $response = $this->get(route('admin.file-manager.index'));

        $response->assertStatus(200);
        $response->assertSee('Pustaka Media', false);
        $response->assertSee('Salin URL', false);
        $response->assertSee('media-search', false);
        $response->assertSee('/img/uploads/contoh.png', false);
    }

    private function makeUploadedFile(string $name, string $bytes, string $mime): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'media-test-');
        file_put_contents($tmp, $bytes);

        return new UploadedFile($tmp, $name, $mime, null, true);
    }
}
