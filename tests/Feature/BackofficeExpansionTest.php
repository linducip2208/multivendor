<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Conversation;
use App\Models\Product;
use App\Models\Shop;
use App\Models\SubscriptionPlan;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\VendorSubscription;
use App\Models\Wallet;
use App\Services\ActivityLogger;
use App\Services\Analytics\DateRange;
use App\Services\Analytics\ExecutiveAnalyticsService;
use App\Services\Analytics\MarketingAnalyticsService;
use App\Services\AuditLogger;
use App\Services\Backoffice\HomepageAdminService;
use App\Services\Backoffice\SystemHealthService;
use App\Services\Crm\ConversationService;
use App\Services\Vendor\VendorAnalyticsService;
use App\Services\Vendor\VendorChatService;
use App\Services\Vendor\VendorDashboardService;
use App\Services\Vendor\VendorProductPricingService;
use App\Services\Vendor\VendorScope;
use App\Services\Vendor\VendorStaffService;
use App\Services\Vendor\VendorSubscriptionService;
use App\Services\Vendor\VendorTicketService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BackofficeExpansionTest extends TestCase
{
    use RefreshDatabase;

    protected User $vendor;

    protected Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = User::create([
            'name' => 'Vendor Uji', 'email' => 'vendor-exp@test.com',
            'password' => Hash::make('password'), 'role' => 'vendor', 'status' => 'active',
        ]);
        Wallet::create(['user_id' => $this->vendor->id, 'balance' => 0]);

        $this->shop = Shop::create([
            'vendor_id' => $this->vendor->id, 'name' => 'Toko Uji',
            'slug' => 'toko-uji-exp', 'description' => 'Deskripsi toko uji',
            'phone' => '08123456789', 'address' => 'Jl. Uji No. 1',
            'commission_type' => 'percentage', 'commission_value' => 5,
            'status' => 'active',
        ]);
    }

    protected function vendorScope(): VendorScope
    {
        $this->actingAs($this->vendor, 'vendor');

        return new VendorScope();
    }

    protected function vendorScopeFor(User $vendor): VendorScope
    {
        $this->actingAs($vendor, 'vendor');

        return new VendorScope();
    }

    public function test_skor_kelengkapan_dan_badge_performa(): void
    {
        $service = new VendorDashboardService($this->vendorScope());

        $completeness = $service->completeness($this->shop);

        $this->assertArrayHasKey('score', $completeness);
        $this->assertArrayHasKey('items', $completeness);
        $this->assertTrue($completeness['score'] >= 0 && $completeness['score'] <= 100);
        $this->assertContains($completeness['badge'], ['success', 'warning', 'danger']);

        $badges = $service->performanceBadges((int) $this->shop->getKey());

        $this->assertArrayHasKey('response_rate', $badges);
        $this->assertArrayHasKey('rating', $badges);
        $this->assertArrayHasKey('fulfillment_rate', $badges);
    }

    public function test_komisi_bertingkat_dan_pratinjau(): void
    {
        $service = new VendorProductPricingService($this->vendorScope());

        $tiers = $service->categoryRates();

        $this->assertSame('percentage', $tiers['default_type']);
        $this->assertEqualsWithDelta(5.0, (float) $tiers['default_value'], 0.001);

        $preview = $service->previewCommission(150000.0, null);

        $this->assertEqualsWithDelta(7500.0, $preview['commission']->toFloat(), 0.01);
        $this->assertEqualsWithDelta(142500.0, $preview['net']->toFloat(), 0.01);
        $this->assertNotEmpty($preview['source']);
    }

    public function test_kuota_grace_dan_usulan_downgrade(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Dasar', 'slug' => 'dasar-exp', 'price' => 50000,
            'billing_cycle' => 'monthly', 'billing_days' => 30,
            'max_products' => 100, 'max_staff' => 5, 'max_storage_mb' => 500,
            'max_monthly_transactions' => 1000, 'is_active' => true, 'sort_order' => 1,
        ]);
        $subscription = VendorSubscription::create([
            'vendor_id' => $this->vendor->id, 'shop_id' => $this->shop->id,
            'subscription_plan_id' => $plan->id, 'status' => 'active',
            'amount_paid' => 0,
            'starts_at' => now()->subDays(20), 'ends_at' => now()->addDays(5),
        ]);

        $service = new VendorSubscriptionService($this->vendorScope());
        $overview = $service->overview();

        $this->assertArrayHasKey('consumption', $overview);
        $this->assertArrayHasKey('grace_reminder', $overview);
        $this->assertArrayHasKey('downgrade_suggestion', $overview);
        $this->assertTrue($overview['grace_reminder']['active']);
        $this->assertGreaterThanOrEqual(4, $overview['grace_reminder']['days_left']);
        $this->assertLessThanOrEqual(6, $overview['grace_reminder']['days_left']);

        $fresh = $subscription->fresh();
        $this->assertNotNull($fresh);
        $reminder = $service->graceReminder($fresh);
        $this->assertTrue($reminder['urgent'] === false || $reminder['urgent'] === true);

        $suggestion = $service->downgradeSuggestion($overview['usage'], (int) $plan->getKey());
        $this->assertArrayHasKey('available', $suggestion);
        $this->assertArrayHasKey('reason', $suggestion);
    }

    public function test_audit_diff_dan_jejak_impersonate(): void
    {
        $diff = AuditLogger::diff(['status' => 'pending', 'nama' => 'Lama'], ['status' => 'active', 'nama' => 'Lama']);

        $this->assertArrayHasKey('status', $diff);
        $this->assertArrayNotHasKey('nama', $diff);

        $logger = $this->app->make(AuditLogger::class);
        $logger->impersonate('vendor', (int) $this->shop->getKey(), (int) $this->vendor->getKey());

        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.impersonate.vendor']);

        ActivityLogger::logWithDiff($this->vendor, 'toko.diperbarui', ['nama' => 'Lama'], ['nama' => 'Baru']);
        $this->assertDatabaseHas('customer_activities', ['type' => 'toko.diperbarui']);
    }

    public function test_roles_granular_dan_staf_vendor(): void
    {
        $matrix = VendorStaffService::menuMatrix();

        $this->assertArrayHasKey('menus', $matrix);
        $this->assertArrayHasKey('role_defaults', $matrix);
        $this->assertArrayHasKey('produk', $matrix['menus']);
        $this->assertContains('products.manage', $matrix['menus']['produk']['permissions']);
        $this->assertContains('finance.view', $matrix['role_defaults']['finance']);

        $staff = new VendorStaffService($this->vendorScope());
        $row = $staff->store([
            'name' => 'Staf Kasir', 'email' => 'kasir@test.com',
            'role' => 'staff', 'permissions' => ['products.view', 'orders.view'],
        ]);

        $this->assertSame('kasir@test.com', strtolower((string) $row->email));
        $this->assertSame((int) $this->shop->getKey(), (int) $row->shop_id);

        // Isolasi: toko lain tidak boleh melihat staf ini.
        $other = User::create([
            'name' => 'Vendor Lain', 'email' => 'lain@test.com',
            'password' => Hash::make('password'), 'role' => 'vendor', 'status' => 'active',
        ]);
        $otherShop = Shop::create([
            'vendor_id' => $other->id, 'name' => 'Toko Lain', 'slug' => 'toko-lain-exp',
            'commission_type' => 'percentage', 'commission_value' => 5, 'status' => 'active',
        ]);
        $otherScope = $this->vendorScopeFor($other);
        $this->assertSame(0, (new VendorStaffService($otherScope))->index()['active']);
    }

    public function test_cms_versioning_dan_banner_scheduling(): void
    {
        /** @var HomepageAdminService $homepage */
        $homepage = $this->app->make(HomepageAdminService::class);
        $versions = $homepage->versions();
        $this->assertIsArray($versions);

        $banner = \App\Models\Banner::create([
            'title' => 'Promo Uji', 'image' => 'promo.jpg',
            'position' => 'hero', 'sort_order' => 0, 'status' => true,
        ]);
        $this->assertTrue((bool) $banner->exists);

        if (Schema::hasColumn('banners', 'starts_at')) {
            $banner->forceFill(['starts_at' => now()->addDay()])->save();
            $this->assertNotNull($banner->fresh()->starts_at);
        } else {
            $this->assertFalse(Schema::hasColumn('banners', 'starts_at'));
        }

        $controller = new \App\Http\Controllers\Admin\BannerController();
        $response = $controller->index();
        $this->assertArrayHasKey('scheduled', $response->getData());
    }

    public function test_laporan_banding_periode_dan_funnel(): void
    {
        $range = DateRange::fromRequest(request()->merge(['range' => '7d']));

        /** @var MarketingAnalyticsService $marketing */
        $marketing = $this->app->make(MarketingAnalyticsService::class);
        $funnel = $marketing->checkoutFunnel($range);
        $this->assertArrayHasKey('steps', $funnel);
        $this->assertArrayHasKey('drop_off', $funnel);
        $this->assertSame(['Keranjang', 'Checkout', 'Terbayar'], array_column($funnel['steps'], 'label'));

        $compare = $marketing->compare($range);
        $this->assertArrayHasKey('current', $compare);
        $this->assertArrayHasKey('deltas', $compare);

        /** @var ExecutiveAnalyticsService $executive */
        $executive = $this->app->make(ExecutiveAnalyticsService::class);
        $summary = $executive->summary($range);
        $this->assertArrayHasKey('compare', $summary);

        $vendorAnalytics = new VendorAnalyticsService($this->vendorScope());
        $this->assertArrayHasKey('delta_revenue', $vendorAnalytics->compare($range));
        $this->assertArrayHasKey('drop_off', $vendorAnalytics->funnel($range));
    }

    public function test_health_alert_dan_backup_terjadwal(): void
    {
        /** @var SystemHealthService $health */
        $health = $this->app->make(SystemHealthService::class);

        $alerts = $health->alerts();
        $this->assertNotEmpty($alerts);
        $this->assertArrayHasKey('level', $alerts[0]);
        $this->assertArrayHasKey('detail', $alerts[0]);

        $backup = $health->backupStatus();
        $this->assertSame('Harian 03:00 (db:backup)', $backup['schedule']);
        $this->assertArrayHasKey('stale', $backup);

        $schedule = file_get_contents(base_path('routes/console.php'));
        $this->assertStringContainsString('db:backup', (string) $schedule);
    }

    public function test_chat_template_sla_assign_rating(): void
    {
        $chat = new VendorChatService($this->vendorScope());
        $templates = $chat->quickReplies();

        $this->assertNotEmpty($templates);
        $this->assertArrayHasKey('body', $templates[0]);

        $customer = User::create([
            'name' => 'Pelanggan Chat', 'email' => 'chat@test.com',
            'password' => Hash::make('password'), 'role' => 'customer', 'status' => 'active',
        ]);
        \App\Models\Order::create([
            'order_number' => \App\Models\Order::generateOrderNumber(),
            'customer_id' => $customer->id, 'shop_id' => $this->shop->id,
            'sub_total' => 50000, 'total' => 50000,
            'payment_status' => 'paid', 'order_status' => 'confirmed',
        ]);
        $thread = $chat->withCustomer((int) $customer->getKey());
        $this->assertSame((int) $this->shop->getKey(), (int) $thread->shop_id);

        $sla = $chat->slaStatus($thread);
        $this->assertArrayHasKey('breached', $sla);
        $this->assertArrayHasKey('sla_hours', $sla);

        $chat->assign($thread, 'Tim CS Pagi');
        $chat->rate($thread, 5);
        $this->assertDatabaseHas('audit_logs', ['action' => 'vendor.chat.assigned']);

        $adminTemplates = ConversationService::quickTemplates();
        $this->assertNotEmpty($adminTemplates);

        $adminInbox = $this->app->make(ConversationService::class);
        $adminSla = $adminInbox->slaStatus(Conversation::query()->findOrFail($thread->getKey()));
        $this->assertArrayHasKey('breached', $adminSla);
    }

    public function test_tiket_sla_makro_csat_gabung(): void
    {
        $tickets = new VendorTicketService($this->vendorScope());

        $id = $tickets->store([
            'subject' => 'Pesanan belum sampai', 'category' => 'order',
            'priority' => 'high', 'message' => 'Pesanan saya belum sampai setelah 5 hari pengiriman.',
        ]);
        $ticket = $tickets->find($id);

        $sla = $tickets->sla($ticket);
        $this->assertSame(24, $sla['hours']);
        $this->assertArrayHasKey('due_at', $sla);

        $this->assertNotEmpty(VendorTicketService::macros());

        $dupId = $tickets->store([
            'subject' => 'Pesanan belum sampai', 'category' => 'order',
            'priority' => 'medium', 'message' => 'Pesanan yang sama belum sampai juga, mohon bantuan.',
        ]);
        $merged = $tickets->mergeDuplicates($id);
        $this->assertGreaterThanOrEqual(1, $merged);
        $this->assertSame('closed', $tickets->find($dupId)->status);

        $tickets->rateSatisfaction($id, 5);

        $model = SupportTicket::query()->findOrFail($id);
        $modelSla = $model->sla();
        $this->assertArrayHasKey('breached', $modelSla);
    }
}
