<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\OrderShippedMail;
use App\Mail\WelcomeMail;
use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Redirect;
use App\Models\Shop;
use App\Models\User;
use App\Services\Localization\Translatable;
use App\Services\Localization\TranslationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CmsMultilingualTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_translations_fallback_and_slug_redirect(): void
    {
        foreach (['product_translations', 'category_translations', 'brand_translations', 'shop_translations', 'blog_post_translations', 'content_translations'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "tabel {$table} hilang");
        }

        $user = User::query()->create([
            'name' => 'Vendor', 'email' => 'v@example.com', 'password' => 'secret123',
        ]);
        $shop = Shop::query()->create(['vendor_id' => $user->id, 'name' => 'Toko ID', 'slug' => 'toko-id', 'status' => 'active']);
        $category = Category::query()->create(['name' => 'Kopi', 'slug' => 'kopi']);
        $brand = Brand::query()->create(['name' => 'Kapal', 'slug' => 'kapal']);
        $product = Product::query()->create([
            'shop_id' => $shop->id, 'category_id' => $category->id, 'brand_id' => $brand->id,
            'name' => 'Kopi Tubruk', 'slug' => 'kopi-tubruk', 'price' => 20000,
            'status' => 'approved', 'published' => true,
        ]);

        DB::table('product_translations')->insert([
            'product_id' => $product->id, 'locale' => 'en', 'name' => 'Ground Coffee',
            'slug' => 'ground-coffee', 'description' => 'EN desc',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('category_translations')->insert([
            'category_id' => $category->id, 'locale' => 'en', 'name' => 'Coffee',
            'slug' => 'coffee', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // EN overlay.
        $product->refresh();
        Translatable::applyToModel($product, 'en', Translatable::fieldsFor('product'));
        $this->assertSame('Ground Coffee', $product->name);
        $this->assertSame('ground-coffee', $product->slug);

        // Fallback ID bila EN kosong (brand tanpa terjemahan).
        $brand->refresh();
        Translatable::applyToModel($brand, 'en', Translatable::fieldsFor('brand'));
        $this->assertSame('Kapal', $brand->name);

        // Slug lokal resolve + canonical + redirect slug lama via Redirect existing.
        [$found, $canonical] = Translatable::resolveBySlug('product', 'ground-coffee', 'en');
        $this->assertNotNull($found);
        $this->assertSame('kopi-tubruk', $canonical);

        $this->assertTrue(Translatable::rememberSlugRedirect('/products/kopi-lama', '/products/kopi-tubruk'));
        $this->assertDatabaseHas('redirects', ['from_path' => '/products/kopi-lama', 'to_path' => '/products/kopi-tubruk']);
        $redirect = Redirect::query()->where('from_path', '/products/kopi-lama')->first();
        $this->assertTrue($redirect->is_active);

        // Chain fallback.
        $this->assertSame(['id-ID', 'id', 'en'], Translatable::fallbackChain('id-ID'));
    }

    public function test_blog_page_status_per_locale(): void
    {
        $author = User::query()->create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'secret123']);
        $post = BlogPost::query()->create([
            'author_id' => $author->id, 'title' => 'Judul ID', 'slug' => 'judul-id',
            'content' => 'Isi ID', 'is_published' => true, 'published_at' => now()->subDay(),
        ]);
        DB::table('blog_post_translations')->insert([
            'blog_post_id' => $post->id, 'locale' => 'en', 'title' => 'EN Title',
            'slug' => 'en-title', 'content' => 'EN body', 'status' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('content_translations')->insert([
            'subject_type' => 'page', 'subject_id' => 0, 'subject_key' => 'about',
            'locale' => 'en', 'title' => 'About Us', 'body' => 'EN about', 'status' => 'published',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('content_translations')->insert([
            'subject_type' => 'page', 'subject_id' => 0, 'subject_key' => 'terms',
            'locale' => 'en', 'title' => 'Terms', 'body' => 'EN terms', 'status' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame('draft', Translatable::contentStatus('blog', (int) $post->id, 'en'));
        $this->assertSame('published', Translatable::contentStatus('blog', (int) $post->id, 'id'));
        $this->assertSame('published', Translatable::contentStatus('page', 'about', 'en'));
        $this->assertSame('draft', Translatable::contentStatus('page', 'terms', 'en'));

        $page = Translatable::pageContent('about', 'en');
        $this->assertNotNull($page);
        $this->assertSame('About Us', $page['title']);
        $this->assertNull(Translatable::pageContent('missing', 'en'));

        // Overlay blog EN (walau draft, overlay tetap terbaca; controller yang fallback).
        $post->refresh();
        Translatable::applyToModel($post, 'en', Translatable::fieldsFor('blog'));
        $this->assertSame('EN Title', $post->title);
    }

    public function test_mail_locale_preference_with_fallback_id(): void
    {
        $idUser = User::query()->create(['name' => 'Budi', 'email' => 'budi@example.com', 'password' => 'secret123']);
        $enUser = User::query()->create(['name' => 'John', 'email' => 'john@example.com', 'password' => 'secret123']);
        $nullUser = User::query()->create(['name' => 'Anon', 'email' => 'anon@example.com', 'password' => 'secret123']);
        // users.locale tidak fillable di model induk (aditif via migrasi) — set langsung.
        $idUser->forceFill(['locale' => 'id'])->save();
        $enUser->forceFill(['locale' => 'en'])->save();
        $idUser->refresh();
        $enUser->refresh();

        $this->assertSame('id', Translatable::recipientLocale($idUser));
        $this->assertSame('en', Translatable::recipientLocale($enUser));
        $this->assertSame('id', Translatable::recipientLocale($nullUser));
        $this->assertSame('id', Translatable::recipientLocale(null));

        // WelcomeMail subject per locale + render BI/EN.
        $welcomeEn = (new WelcomeMail($enUser))->build();
        $this->assertStringContainsString('Welcome', $welcomeEn->subject);
        $htmlEn = (string) $welcomeEn->render();
        $this->assertStringContainsString('Hello', $htmlEn);

        app()->setLocale('id');
        $welcomeId = (new WelcomeMail($idUser))->build();
        $this->assertStringContainsString('Selamat Datang', $welcomeId->subject);
        $htmlId = (string) $welcomeId->render();
        $this->assertStringContainsString('Halo', $htmlId);

        // OrderShippedMail fallback id bila locale kosong.
        $order = new \App\Models\Order(['order_number' => 'ORD-1', 'total' => 50000]);
        $order->setRelation('customer', $nullUser);
        $shipped = (new OrderShippedMail($order))->build();
        $this->assertStringContainsString('Dikirim', $shipped->subject);
        app()->setLocale('id');
        $htmlShipId = (string) $shipped->render();
        $this->assertStringContainsString('dalam perjalanan', $htmlShipId);

        // Versi EN via penerima EN.
        $orderEn = new \App\Models\Order(['order_number' => 'ORD-2', 'total' => 75000]);
        $orderEn->setRelation('customer', $enUser);
        $shippedEn = (new OrderShippedMail($orderEn))->build();
        $this->assertStringContainsString('Shipped', $shippedEn->subject);
        app()->setLocale('en');
        $htmlShipEn = (string) $shippedEn->render();
        $this->assertStringContainsString('on its way', $htmlShipEn);
        app()->setLocale('id');
    }

    public function test_translation_import_export_and_coverage(): void
    {
        $repo = app(TranslationRepository::class);
        $repo->set('en', 'common', 'save', 'Save');
        $repo->set('id', 'common', 'save', 'Simpan');

        // Export/import JSON/CSV via repository existing.
        $json = $repo->exportJson('id');
        $this->assertSame('Simpan', $json['common.save']);
        $this->assertSame(1, $repo->importJson('en', ['common.hello' => 'Hello']));
        $csv = $repo->exportCsv('id');
        $this->assertStringContainsString('common,save,id,Simpan', $csv);

        $coverage = $repo->coverage('id');
        $this->assertArrayHasKey('per_namespace', $coverage);
        $this->assertGreaterThanOrEqual(0, $coverage['percent']);

        // Coverage konten + export/import konten.
        $user = User::query()->create(['name' => 'V', 'email' => 'v2@example.com', 'password' => 'secret123']);
        $shop = Shop::query()->create(['vendor_id' => $user->id, 'name' => 'T', 'slug' => 't-1', 'status' => 'active']);
        $cat = Category::query()->create(['name' => 'C', 'slug' => 'c-1']);
        $product = Product::query()->create([
            'shop_id' => $shop->id, 'category_id' => $cat->id, 'name' => 'P', 'slug' => 'p-1',
            'price' => 1000, 'status' => 'approved', 'published' => true,
        ]);
        DB::table('product_translations')->insert([
            'product_id' => $product->id, 'locale' => 'en', 'name' => 'P EN',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $contentCoverage = Translatable::contentCoverage('en');
        $this->assertArrayHasKey('product', $contentCoverage['per_type']);
        $this->assertGreaterThanOrEqual(0, $contentCoverage['percent']);

        $exported = Translatable::exportContentJson('en');
        $this->assertArrayHasKey('product.'.$product->id, $exported);
        DB::table('product_translations')->where('product_id', $product->id)->delete();
        $this->assertSame(1, Translatable::importContentJson('en', $exported));
        $this->assertDatabaseHas('product_translations', ['product_id' => $product->id, 'locale' => 'en']);
    }
}
