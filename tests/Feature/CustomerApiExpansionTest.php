<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ApiController;
use App\Models\CustomerAddress;
use App\Models\LoyaltyPoint;
use App\Models\LoyaltyTransaction;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Api\OpenApiSpec;
use App\Services\Api\PersonalAccessTokenIssuer;
use App\Services\Crm\Customer360Service;
use App\Services\Seo\PseoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Perdalaman fitur pelanggan existing (tanpa migrasi, tanpa route baru).
 *
 * Self-contained: membangun tabel minimal di sqlite :memory:.
 */
class CustomerApiExpansionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->buatTabel();
    }

    protected function tearDown(): void
    {
        foreach (['personal_access_tokens', 'categories', 'system_settings', 'products', 'orders', 'support_ticket_replies', 'support_tickets', 'loyalty_transactions', 'loyalty_points', 'customer_addresses', 'users'] as $tabel) {
            Schema::dropIfExists($tabel);
        }
        parent::tearDown();
    }

    private function buatTabel(): void
    {
        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $t): void {
            $t->increments('id');
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->string('phone')->nullable();
            $t->string('role')->default('customer');
            $t->string('status')->default('active');
            $t->string('referral_code')->nullable();
            $t->integer('referred_by')->nullable();
            $t->timestamps();
        });
        Schema::dropIfExists('loyalty_points');
        Schema::create('loyalty_points', function (Blueprint $t): void {
            $t->increments('id');
            $t->integer('customer_id');
            $t->integer('points')->default(0);
            $t->timestamps();
        });
        Schema::dropIfExists('loyalty_transactions');
        Schema::create('loyalty_transactions', function (Blueprint $t): void {
            $t->increments('id');
            $t->integer('customer_id');
            $t->integer('points');
            $t->string('type');
            $t->string('description')->nullable();
            $t->string('reference_type')->nullable();
            $t->integer('reference_id')->nullable();
            $t->timestamps();
        });
        Schema::dropIfExists('customer_addresses');
        Schema::create('customer_addresses', function (Blueprint $t): void {
            $t->increments('id');
            $t->integer('customer_id');
            $t->string('label')->nullable();
            $t->string('receiver_name');
            $t->string('receiver_phone');
            $t->string('address');
            $t->string('city');
            $t->string('province');
            $t->string('postal_code')->nullable();
            $t->string('shipping_destination_id')->nullable();
            $t->boolean('is_default')->default(false);
            $t->timestamps();
        });
        Schema::dropIfExists('support_tickets');
        Schema::create('support_tickets', function (Blueprint $t): void {
            $t->increments('id');
            $t->integer('customer_id');
            $t->string('subject');
            $t->string('type')->default('other');
            $t->string('priority')->default('medium');
            $t->text('description')->nullable();
            $t->string('status')->default('open');
            $t->timestamp('first_response_at')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();
        });
        Schema::dropIfExists('support_ticket_replies');
        Schema::create('support_ticket_replies', function (Blueprint $t): void {
            $t->increments('id');
            $t->integer('support_ticket_id');
            $t->integer('user_id');
            $t->text('message');
            $t->timestamps();
        });
        Schema::dropIfExists('orders');
        Schema::create('orders', function (Blueprint $t): void {
            $t->increments('id');
            $t->integer('customer_id');
            $t->string('order_number')->nullable();
            $t->decimal('total', 15, 2)->default(0);
            $t->string('order_status')->default('delivered');
            $t->string('payment_status')->default('paid');
            $t->timestamps();
        });
        Schema::dropIfExists('products');
        Schema::create('products', function (Blueprint $t): void {
            $t->increments('id');
            $t->integer('shop_id')->nullable();
            $t->integer('category_id')->nullable();
            $t->string('name');
            $t->string('slug');
            $t->decimal('price', 15, 2)->default(0);
            $t->integer('current_stock')->default(0);
            $t->string('status')->default('approved');
            $t->boolean('published')->default(true);
            $t->timestamps();
        });
        Schema::dropIfExists('categories');
        Schema::create('categories', function (Blueprint $t): void {
            $t->increments('id');
            $t->string('name');
            $t->string('slug')->nullable();
            $t->boolean('status')->default(true);
            $t->timestamps();
        });
        Schema::dropIfExists('system_settings');
        Schema::create('system_settings', function (Blueprint $t): void {
            $t->increments('id');
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->string('type')->default('string');
            $t->timestamps();
        });
        Schema::dropIfExists('personal_access_tokens');
        Schema::create('personal_access_tokens', function (Blueprint $t): void {
            $t->increments('id');
            $t->string('tokenable_type');
            $t->integer('tokenable_id');
            $t->string('name');
            $t->string('token', 64)->unique();
            $t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
    }

    private function buatUser(string $email = 'pelanggan@example.com'): User
    {
        return User::create([
            'name' => 'Pelanggan', 'email' => $email, 'password' => Hash::make('rahasia123'),
            'role' => 'customer', 'status' => 'active', 'referral_code' => 'REF'.random_int(1000, 9999),
        ]);
    }

    public function test_loyalty_tier_expiry_dan_referral(): void
    {
        $user = $this->buatUser();
        LoyaltyPoint::create(['customer_id' => $user->id, 'points' => 6000]);
        LoyaltyTransaction::create(['customer_id' => $user->id, 'points' => 6000, 'type' => 'earn', 'description' => 'Belanja']);
        LoyaltyTransaction::create(['customer_id' => $user->id, 'points' => 500, 'type' => 'earn', 'description' => 'Bonus referral', 'reference_type' => 'referral']);

        $lp = LoyaltyPoint::where('customer_id', $user->id)->first();
        $this->assertSame('gold', $lp->tier()['code']);
        $this->assertSame(12, $lp->expiringSoon()['expiry_months']);

        $teman = $this->buatUser('teman@example.com');
        $teman->forceFill(['referred_by' => $user->id])->save();
        $ref = $lp->referralHistory();
        $this->assertSame(1, $ref['count']);
        $this->assertSame(500, $ref['points_earned']);
        $this->assertNotEmpty(LoyaltyPoint::referralLeaderboard(5));
    }

    public function test_alamat_normalisasi_label_dan_utama(): void
    {
        $data = CustomerAddress::normalize([
            'label' => '', 'receiver_name' => '  Budi   Santoso ',
            'receiver_phone' => '0812-345 6789', 'address' => 'Jl. Mawar  No. 10',
            'city' => 'Bandung', 'province' => 'Jawa Barat', 'postal_code' => '40 123',
        ]);
        $this->assertSame('Rumah', $data['label']);
        $this->assertSame('+628123456789', $data['receiver_phone']);
        $this->assertSame('40123', $data['postal_code']);
        $this->assertContains('Rumah', CustomerAddress::LABELS);
    }

    public function test_tiket_sla_csat_dan_makro(): void
    {
        $user = $this->buatUser('tiket@example.com');
        $ticket = SupportTicket::create([
            'customer_id' => $user->id, 'subject' => 'Barang belum datang',
            'type' => 'order', 'priority' => 'high', 'description' => 'Mohon bantuan', 'status' => 'open',
        ]);
        $ticket->forceFill(['created_at' => now()->subHours(30)])->save();
        $ticket->refresh();
        $ticket->replies()->create(['user_id' => $user->id, 'message' => 'Saya sangat puas, terima kasih!']);

        $sla = $ticket->sla();
        $this->assertSame(24, $sla['target_hours']);
        $this->assertTrue($sla['breached']);
        $this->assertSame(5, $ticket->csat()['score']);
        $this->assertNotEmpty($ticket->macroHistory());
    }

    public function test_token_scopes_granular_expiry_dan_rotasi(): void
    {
        $issuer = new PersonalAccessTokenIssuer();
        $this->assertContains('loyalty:read', $issuer->expandScopes(['read']));
        $this->assertNotNull($issuer->defaultExpiry());

        $user = $this->buatUser('token@example.com');
        $issued = $issuer->issue($user, 'uji', ['read', 'loyalty:read'], now()->addDays(7)->toDateTimeImmutable());
        $this->assertStringContainsString('|', $issued['token']);
        $rotated = $issuer->rotate($user, (string) $issued['id'], 'uji-rotasi');
        $this->assertSame((string) $issued['id'], $rotated['rotated_from']);
    }

    public function test_openapi_tetap_sinkron_dengan_route(): void
    {
        $spec = new OpenApiSpec();
        $doc = $spec->build();
        $this->assertSame('3.1.0', $doc['openapi']);
        $this->assertGreaterThan(0, $spec->endpointCount());
        $this->assertSame($spec->endpointCount(), count($doc['paths'] ?? []) > 0 ? array_sum(array_map('count', $doc['paths'])) : 0);
    }

    public function test_header_deprecation_versi_lama(): void
    {
        $controller = new class extends ApiController {
            public function expose(Request $request): array
            {
                return $this->deprecatedVersionHeaders($request);
            }
        };
        $v1 = Request::create('/api/v1/account', 'GET');
        $this->assertSame('true', $controller->expose($v1)['Deprecation']);
        $v4 = Request::create('/api/v4/products', 'GET');
        $this->assertSame([], $controller->expose($v4));
    }

    public function test_seo_saran_faq_audit_dan_skor(): void
    {
        $cat = \DB::table('categories')->insertGetId(['name' => 'Sepatu', 'slug' => 'sepatu', 'status' => true, 'created_at' => now(), 'updated_at' => now()]);
        \App\Models\Product::create(['shop_id' => 1, 'category_id' => $cat, 'name' => 'Sepatu Lari', 'slug' => 'sepatu-lari', 'price' => 250000, 'current_stock' => 5, 'status' => 'approved', 'published' => true]);
        $seo = new PseoService();
        $this->assertNotEmpty($seo->suggestionsFromSearchAnalytics(5));
        $faq = $seo->faqForCategory(['category' => 'Sepatu', 'brand' => 'Nike']);
        $this->assertCount(3, $faq);
        $this->assertIsArray($seo->auditImageAlts(5));
        $page = new \App\Models\PseoPage([
            'quality_score' => 40,
            'quality_breakdown' => ['unique_body' => ['score' => 0, 'max' => 14, 'note' => 'pendek']],
        ]);
        $this->assertNotEmpty($seo->seoSuggestions($page));
        $this->assertNotEmpty($seo->internalLinks('category_top_products', ['category_id' => 1]));
    }

    public function test_analitik_rfm_cohort_funnel_readonly(): void
    {
        $user = $this->buatUser('rfm@example.com');
        \DB::table('orders')->insert([
            'customer_id' => $user->id, 'order_number' => 'INV-1', 'total' => 500000,
            'order_status' => 'delivered', 'payment_status' => 'paid', 'created_at' => now()->subDays(5), 'updated_at' => now()->subDays(5),
        ]);
        $crm = new Customer360Service();
        $rfm = $crm->rfm((int) $user->id);
        $this->assertSame(1, $rfm['frequency']);
        $this->assertNotEmpty($rfm['segment']);
        $this->assertNotEmpty($crm->cohort((int) $user->id, 3));
        $this->assertSame('Keranjang', $crm->funnel((int) $user->id)[0]['stage']);
    }
}
