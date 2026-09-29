<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\BlogController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CmsController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CrmController;
use App\Http\Controllers\Admin\CustomerWalletController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DealOfTheDayController;
use App\Http\Controllers\Admin\DeliveryManController;
use App\Http\Controllers\Admin\DeliveryRatingAdminController;
use App\Http\Controllers\Admin\DeveloperController;
use App\Http\Controllers\Admin\DiscountSettingsController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\FeaturedDealController;
use App\Http\Controllers\Admin\FileManagerController;
use App\Http\Controllers\Admin\FlashDealController;
use App\Http\Controllers\Admin\HomepageController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\MarketingController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\MostDemandedController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\OrderSettingsController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductSeoController;
use App\Http\Controllers\Admin\ProviderController;
use App\Http\Controllers\Admin\PushNotificationController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SaasController;
use App\Http\Controllers\Admin\SeoController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ShippingCategoryController;
use App\Http\Controllers\Admin\ShippingController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\SupportTicketController as AdminTicketController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\SystemToolsController;
use App\Http\Controllers\Admin\TaxReportController;
use App\Http\Controllers\Admin\ThemeController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\VendorController;
use App\Http\Controllers\Admin\WithdrawController as AdminWithdrawController;
use App\Http\Controllers\Admin\AiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Admin routes
|--------------------------------------------------------------------------
| Loaded from routes/web.php. Route names are unchanged (prefix admin.).
*/

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

    Route::middleware('admin')->group(function (): void {
        /* ---------------- DASHBOARD ---------------- */
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        /* ---------------- COMMERCE ---------------- */
        Route::resource('vendors', VendorController::class)->parameters(['vendors' => 'shop'])->except(['show']);
        Route::get('vendors/{shop}', [VendorController::class, 'show'])->name('vendors.show');
        Route::put('vendors/{shop}/status', [VendorController::class, 'updateStatus'])->name('vendors.update-status');

        Route::resource('categories', CategoryController::class)->except(['show']);
        Route::get('categories/{category}/show', [CategoryController::class, 'show'])->name('categories.show');

        Route::resource('brands', BrandController::class)->except(['show']);
        Route::get('brands/{brand}/show', [BrandController::class, 'show'])->name('brands.show');

        Route::resource('products', ProductController::class)->only(['index', 'show', 'destroy']);
        Route::put('products/{product}/status', [ProductController::class, 'updateStatus'])->name('products.update-status');

        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('inventory/warehouses', [InventoryController::class, 'warehouses'])->name('inventory.warehouses');
        Route::post('inventory/warehouses', [InventoryController::class, 'storeWarehouse'])->name('inventory.warehouses.store');
        Route::put('inventory/warehouses/{warehouse}', [InventoryController::class, 'updateWarehouse'])->name('inventory.warehouses.update');
        Route::get('inventory/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
        Route::post('inventory/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
        Route::post('inventory/opname', [InventoryController::class, 'opname'])->name('inventory.opname');
        Route::post('inventory/transfers', [InventoryController::class, 'storeTransfer'])->name('inventory.transfers.store');
        Route::put('inventory/transfers/{transfer}/receive', [InventoryController::class, 'receiveTransfer'])->name('inventory.transfers.receive');

        Route::resource('orders', AdminOrderController::class)->only(['index', 'show']);
        Route::put('orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.update-status');
        Route::post('orders/{order}/refund', [PaymentController::class, 'refundOrder'])->name('orders.refund');
        Route::get('orders/export', [AdminOrderController::class, 'export'])->name('orders.export');

        Route::resource('collections', CmsController::class)->parameters(['collections' => 'bundle'])->only(['index', 'store', 'destroy'])
            ->names('collections');
        Route::resource('product-bundles', CmsController::class)->parameters(['product-bundles' => 'bundle'])->only(['index', 'store', 'destroy'])
            ->names('bundles');

        Route::get('reviews', [CrmController::class, 'reviews'])->name('reviews.index');
        Route::put('reviews/{review}', [CrmController::class, 'moderateReview'])->name('reviews.update');
        Route::delete('reviews/{review}', [CrmController::class, 'deleteReview'])->name('reviews.destroy');

        /* ---------------- SELLERS ---------------- */
        Route::get('vendor-applications', [VendorController::class, 'applications'])->name('vendors.applications');
        Route::put('vendor-applications/{shop}', [VendorController::class, 'decideApplication'])->name('vendors.applications.decide');
        Route::resource('commission-rules', VendorController::class)->only(['index', 'update'])->names('commissions');
        Route::resource('settlements', PaymentController::class)->only(['index', 'show'])->names('settlements');

        /* ---------------- CUSTOMERS ---------------- */
        Route::resource('customers', CustomerController::class)->only(['index', 'show']);
        Route::get('customers/wallets', [CustomerWalletController::class, 'index'])->name('customers.wallets');
        Route::get('customers/{user}/wallet', [CustomerWalletController::class, 'show'])->name('customers.wallet-detail');
        Route::post('customers/{user}/wallet/adjust', [CustomerWalletController::class, 'adjust'])->name('customers.wallet-adjust');
        Route::get('segments', [CrmController::class, 'segments'])->name('segments.index');
        Route::post('segments', [CrmController::class, 'storeSegment'])->name('segments.store');
        Route::post('segments/{segment}/sync', [CrmController::class, 'syncSegment'])->name('segments.sync');
        Route::delete('segments/{segment}', [CrmController::class, 'destroySegment'])->name('segments.destroy');
        Route::get('loyalty', [CrmController::class, 'loyalty'])->name('loyalty.index');
        Route::get('customer-360/{user}', [CrmController::class, 'show'])->name('customers.360');
        Route::get('activity-log', [CrmController::class, 'activity'])->name('activity-log');
        Route::get('wishlists', [CrmController::class, 'wishlists'])->name('wishlists.index');

        /* ---------------- MARKETING ---------------- */
        Route::resource('campaigns', MarketingController::class)->except(['show']);
        Route::get('campaigns/{campaign}/show', [MarketingController::class, 'show'])->name('campaigns.show');
        Route::post('campaigns/{campaign}/toggle', [MarketingController::class, 'toggle'])->name('campaigns.toggle');
        Route::get('abandoned-carts', [MarketingController::class, 'abandonedCarts'])->name('abandoned-carts');
        Route::post('abandoned-carts/{cart}/remind', [MarketingController::class, 'sendAbandonedReminder'])->name('abandoned-carts.remind');
        Route::resource('referrals', MarketingController::class)->only(['index'])->names('referrals');
        Route::resource('affiliates', MarketingController::class)->only(['index', 'update'])->names('affiliates');
        Route::get('notifications', [MarketingController::class, 'notifications'])->name('notifications');
        Route::resource('push-notifications', PushNotificationController::class)->only(['index', 'store']);
        Route::post('push-notifications/{notification}/send', [PushNotificationController::class, 'send'])->name('push-notifications.send');
        Route::get('homepage', [HomepageController::class, 'index'])->name('homepage.index');
        Route::put('homepage', [HomepageController::class, 'update'])->name('homepage.update');
        Route::post('homepage/preview', [HomepageController::class, 'preview'])->name('homepage.preview');

        Route::resource('coupons', CouponController::class)->except(['show']);
        Route::resource('flashdeals', FlashDealController::class)->except(['show']);
        Route::resource('banners', BannerController::class)->except(['show']);
        Route::resource('deals', DealOfTheDayController::class)->only(['index', 'store', 'destroy']);
        Route::resource('featured-deals', FeaturedDealController::class)->only(['index', 'store']);
        Route::delete('featured-deals/{product}', [FeaturedDealController::class, 'remove'])->name('featured-deals.remove');
        Route::get('most-demanded', [MostDemandedController::class, 'index'])->name('most-demanded.index');
        Route::get('discount-settings', [DiscountSettingsController::class, 'index'])->name('discount-settings.index');
        Route::put('discount-settings', [DiscountSettingsController::class, 'update'])->name('discount-settings.update');

        /* ---------------- FULFILMENT ---------------- */
        Route::resource('shipments', ShippingController::class)->only(['index', 'show']);
        Route::get('couriers', [ShippingController::class, 'couriers'])->name('couriers.index');
        Route::get('couriers/{provider}', [ShippingController::class, 'courierShow'])->name('couriers.show');
        Route::get('shipments/{shipment}/track', [ShippingController::class, 'track'])->name('shipments.track');
        Route::get('returns', [ShippingController::class, 'returns'])->name('returns.index');
        Route::put('returns/{return}', [ShippingController::class, 'decideReturn'])->name('returns.update');
        Route::resource('delivery-men', DeliveryManController::class);
        Route::get('delivery-men/{delivery_man}/wallet', [DeliveryManController::class, 'wallet'])->name('delivery-men.wallet');
        Route::resource('delivery', DeliveryManController::class)->only(['index'])->names('delivery');
        Route::get('delivery/ratings', [DeliveryRatingAdminController::class, 'index'])->name('delivery.ratings');
        Route::get('delivery/{user}/rating-report', [DeliveryRatingAdminController::class, 'deliveryManReport'])->name('delivery.rating-report');
        Route::resource('delivery-man', DeliveryManController::class)->only(['index'])->names('delivery-man');
        Route::get('shipping-category', [ShippingCategoryController::class, 'index'])->name('shipping-category.index');
        Route::post('shipping-category', [ShippingCategoryController::class, 'store'])->name('shipping-category.store');
        Route::delete('shipping-category', [ShippingCategoryController::class, 'destroy'])->name('shipping-category.destroy');

        /* ---------------- FINANCE ---------------- */
        Route::resource('transactions', TransactionController::class)->only(['index']);
        Route::get('ledger', [PaymentController::class, 'ledger'])->name('ledger.index');
        Route::get('ledger/accounts', [PaymentController::class, 'ledgerAccounts'])->name('ledger.accounts');
        Route::get('refunds', [PaymentController::class, 'refunds'])->name('refunds.index');
        Route::get('refunds/{refund}', [PaymentController::class, 'refundShow'])->name('refunds.show');
        Route::get('payment-reconciliation', [PaymentController::class, 'reconciliation'])->name('payments.reconciliation');
        Route::post('payment-reconciliation/run', [PaymentController::class, 'runReconciliation'])->name('payments.reconciliation.run');
        Route::resource('withdraws', AdminWithdrawController::class)->only(['index', 'update']);
        Route::get('vat', fn () => view('admin.vat.index'))->name('vat.index');
        Route::post('vat', function (Request $request) {
            $request->validate(['name' => 'required|string|max:120', 'rate' => 'required|numeric|min:0|max:100']);
            \App\Models\VatTax::create($request->only(['name', 'rate']) + ['is_active' => true]);

            return back()->with('success', 'Pajak ditambahkan.');
        })->name('vat.store');
        Route::delete('vat/{vatTax}', function (\App\Models\VatTax $vatTax) {
            $vatTax->delete();

            return back()->with('success', 'Pajak dihapus.');
        })->name('vat.destroy');
        Route::get('tax-report', [TaxReportController::class, 'index'])->name('tax-report.index');
        Route::get('tax-report/settings', [TaxReportController::class, 'settings'])->name('tax-report.settings');
        Route::put('tax-report/settings', [TaxReportController::class, 'updateSettings'])->name('tax-report.settings.update');
        Route::get('order-settings', [OrderSettingsController::class, 'index'])->name('order-settings.index');
        Route::put('order-settings', [OrderSettingsController::class, 'update'])->name('order-settings.update');
        Route::get('offline-payment', [CmsController::class, 'offlinePayment'])->name('offline-payment.index');
        Route::put('offline-payment', [CmsController::class, 'updateOfflinePayment'])->name('offline-payment.update');
        Route::get('email-templates', [CmsController::class, 'emailTemplates'])->name('email-templates.index');
        Route::put('email-templates', [CmsController::class, 'updateEmailTemplates'])->name('email-templates.update');

        /* ---------------- CRM ---------------- */
        Route::get('conversations', [CrmController::class, 'conversations'])->name('conversations.index');
        Route::get('conversations/{conversation}', [CrmController::class, 'conversation'])->name('conversations.show');
        Route::post('conversations/{conversation}/reply', [CrmController::class, 'replyConversation'])->name('conversations.reply');
        Route::get('support-tickets', [AdminTicketController::class, 'index'])->name('support-tickets.index');
        Route::get('support-tickets/{ticket}', [AdminTicketController::class, 'show'])->name('support-tickets.show');
        Route::put('support-tickets/{ticket}', [AdminTicketController::class, 'update'])->name('support-tickets.update');

        /* ---------------- AI ---------------- */
        Route::get('ai', [AiController::class, 'index'])->name('ai.index');
        Route::post('ai/generate', [AiController::class, 'generate'])->name('ai.generate');
        Route::get('ai/usage', [AiController::class, 'usage'])->name('ai.usage');
        Route::get('ai/prompts', [AiController::class, 'prompts'])->name('ai.prompts');
        Route::put('ai/prompts', [AiController::class, 'updatePrompts'])->name('ai.prompts.update');

        /* ---------------- ANALYTICS ---------------- */
        Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('analytics/sales', [AnalyticsController::class, 'sales'])->name('analytics.sales');
        Route::get('analytics/customers', [AnalyticsController::class, 'customers'])->name('analytics.customers');
        Route::get('analytics/vendors', [AnalyticsController::class, 'vendors'])->name('analytics.vendors');
        Route::get('analytics/products', [AnalyticsController::class, 'products'])->name('analytics.products');
        Route::get('analytics/marketing', [AnalyticsController::class, 'marketing'])->name('analytics.marketing');
        Route::get('analytics/finance', [AnalyticsController::class, 'finance'])->name('analytics.finance');
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::post('reports/ai', [ReportController::class, 'aiAnalysis'])->name('reports.ai');
        Route::post('reports/fetch-models', [ReportController::class, 'fetchModels'])->name('reports.fetch-models');
        Route::get('stock-report', [AnalyticsController::class, 'stock'])->name('stock-report.index');
        Route::get('vendor-sale-report', [AnalyticsController::class, 'vendorSales'])->name('vendor-sale-report.index');

        /* ---------------- CONTENT ---------------- */
        Route::resource('blog', BlogController::class)->except(['show']);
        Route::get('blog/{blog}/show', [BlogController::class, 'show'])->name('blog.show');
        Route::get('media', [FileManagerController::class, 'index'])->name('media.index');
        Route::get('file-manager', [FileManagerController::class, 'index'])->name('file-manager.index');
        Route::post('file-manager/upload', [FileManagerController::class, 'upload'])->name('file-manager.upload');
        Route::delete('file-manager', [FileManagerController::class, 'destroy'])->name('file-manager.destroy');
        Route::get('pages', [CmsController::class, 'pages'])->name('pages.index');
        Route::put('pages', [CmsController::class, 'updatePages'])->name('pages.update');
        Route::get('menus', [CmsController::class, 'menus'])->name('menus.index');
        Route::put('menus', [CmsController::class, 'updateMenus'])->name('menus.update');
        Route::get('contacts', [CmsController::class, 'contacts'])->name('contacts.index');
        Route::put('contacts', [CmsController::class, 'updateContacts'])->name('contacts.update');
        Route::get('help-topics', [CmsController::class, 'helpTopics'])->name('help-topics.index');
        Route::put('help-topics', [CmsController::class, 'updateHelpTopics'])->name('help-topics.update');

        /* ---------------- SEO / GEO / PSEO ---------------- */
        Route::get('seo', [SeoController::class, 'index'])->name('seo.index');
        Route::put('seo', [SeoController::class, 'update'])->name('seo.update');
        Route::get('seo/sitemaps', [SeoController::class, 'sitemaps'])->name('seo.sitemaps');
        Route::get('seo/redirects', [SeoController::class, 'redirects'])->name('seo.redirects');
        Route::post('seo/redirects', [SeoController::class, 'storeRedirect'])->name('seo.redirects.store');
        Route::delete('seo/redirects/{redirect}', [SeoController::class, 'destroyRedirect'])->name('seo.redirects.destroy');
        Route::get('pseo', [SeoController::class, 'pseo'])->name('pseo.index');
        Route::put('pseo', [SeoController::class, 'updatePseo'])->name('pseo.update');
        Route::post('pseo/generate', [SeoController::class, 'generatePseo'])->name('pseo.generate');
        Route::post('pseo/{page}/review', [SeoController::class, 'reviewPseoPage'])->name('pseo.review');
        Route::post('pseo/{page}/publish', [SeoController::class, 'publishPseoPage'])->name('pseo.publish');
        Route::delete('pseo/{page}', [SeoController::class, 'destroyPseoPage'])->name('pseo.destroy');
        Route::resource('product-seo', ProductSeoController::class)->only(['index', 'update']);

        /* ---------------- DEVELOPERS ---------------- */
        Route::get('api', [DeveloperController::class, 'index'])->name('api.index');
        Route::get('api-keys', [DeveloperController::class, 'apiKeys'])->name('api-keys.index');
        Route::post('api-keys', [DeveloperController::class, 'storeApiKey'])->name('api-keys.store');
        Route::delete('api-keys/{key}', [DeveloperController::class, 'destroyApiKey'])->name('api-keys.destroy');
        Route::get('webhooks', [DeveloperController::class, 'webhooks'])->name('webhooks.index');
        Route::post('webhooks', [DeveloperController::class, 'storeWebhook'])->name('webhooks.store');
        Route::put('webhooks/{webhook}', [DeveloperController::class, 'updateWebhook'])->name('webhooks.update');
        Route::delete('webhooks/{webhook}', [DeveloperController::class, 'destroyWebhook'])->name('webhooks.destroy');
        Route::get('webhooks/{webhook}/deliveries', [DeveloperController::class, 'webhookDeliveries'])->name('webhooks.deliveries');
        Route::post('webhooks/{webhook}/deliveries/{delivery}/replay', [DeveloperController::class, 'replayDelivery'])->name('webhooks.deliveries.replay');
        Route::get('events', [DeveloperController::class, 'events'])->name('events.index');
        Route::get('logs', [DeveloperController::class, 'logs'])->name('logs.index');

        /* ---------------- SAAS ---------------- */
        Route::resource('tenants', SaasController::class)->only(['index', 'store', 'destroy']);
        Route::get('tenants/{tenant}/show', [SaasController::class, 'show'])->name('tenants.show');
        Route::put('tenants/{tenant}', [SaasController::class, 'update'])->name('tenants.update');
        Route::get('saas/plans', [SaasController::class, 'plans'])->name('saas.plans');
        Route::post('saas/plans', [SaasController::class, 'storePlan'])->name('saas.plans.store');
        Route::put('saas/plans/{plan}', [SaasController::class, 'updatePlan'])->name('saas.plans.update');
        Route::delete('saas/plans/{plan}', [SaasController::class, 'destroyPlan'])->name('saas.plans.destroy');
        Route::get('saas/subscriptions', [SaasController::class, 'subscriptions'])->name('saas.subscriptions');
        Route::put('saas/subscriptions/{subscription}/status', [SaasController::class, 'updateSubscriptionStatus'])->name('saas.subscriptions.status');
        Route::get('saas/usage', [SaasController::class, 'usage'])->name('saas.usage');
        Route::get('saas/themes', [SaasController::class, 'themes'])->name('saas.themes');

        /* ---------------- SYSTEM ---------------- */
        Route::resource('providers', ProviderController::class)->except(['show']);
        Route::get('providers/preset', [ProviderController::class, 'getPreset'])->name('providers.preset');
        Route::get('subscriptions/plans', [SubscriptionController::class, 'plans'])->name('subscriptions.plans');
        Route::post('subscriptions/plans', [SubscriptionController::class, 'storePlan'])->name('subscriptions.plans.store');
        Route::put('subscriptions/plans/{plan}', [SubscriptionController::class, 'updatePlan'])->name('subscriptions.plans.update');
        Route::delete('subscriptions/plans/{plan}', [SubscriptionController::class, 'destroyPlan'])->name('subscriptions.plans.destroy');
        Route::get('subscriptions', [SubscriptionController::class, 'subscriptions'])->name('subscriptions.index');
        Route::get('subscriptions/{subscription}/show', [SubscriptionController::class, 'showSubscription'])->name('subscriptions.show');
        Route::put('subscriptions/{subscription}/status', [SubscriptionController::class, 'updateSubscriptionStatus'])->name('subscriptions.update-status');

        Route::resource('users', EmployeeController::class)->except(['show']);
        Route::get('users/{user}/show', [EmployeeController::class, 'show'])->name('users.show');
        Route::get('roles', [CmsController::class, 'roles'])->name('roles.index');
        Route::put('roles', [CmsController::class, 'updateRoles'])->name('roles.update');
        Route::get('permissions', [CmsController::class, 'permissions'])->name('permissions.index');
        Route::put('permissions', [CmsController::class, 'updatePermissions'])->name('permissions.update');

        Route::get('settings', [SettingsController::class, 'index'])->name('settings');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('theme', [ThemeController::class, 'index'])->name('theme.index');
        Route::put('theme', [ThemeController::class, 'update'])->name('theme.update');
        Route::get('language', [CmsController::class, 'language'])->name('language.index');
        Route::put('language', [CmsController::class, 'updateLanguage'])->name('language.update');
        Route::get('currency', [CmsController::class, 'currency'])->name('currency.index');
        Route::put('currency', [CmsController::class, 'updateCurrency'])->name('currency.update');
        Route::get('translation', [CmsController::class, 'translation'])->name('translation.index');
        Route::put('translation', [CmsController::class, 'updateTranslation'])->name('translation.update');
        Route::get('inhouse-shop', [CmsController::class, 'inhouseShop'])->name('inhouse-shop.index');
        Route::put('inhouse-shop', [CmsController::class, 'updateInhouseShop'])->name('inhouse-shop.update');
        Route::get('vendor-settings', [CmsController::class, 'vendorSettings'])->name('vendor-settings.index');
        Route::put('vendor-settings', [CmsController::class, 'updateVendorSettings'])->name('vendor-settings.update');
        Route::get('sms-gateway', [CmsController::class, 'smsGateway'])->name('sms-gateway.index');
        Route::put('sms-gateway', [CmsController::class, 'updateSmsGateway'])->name('sms-gateway.update');
        Route::get('third-party', [CmsController::class, 'thirdParty'])->name('third-party.index');
        Route::put('third-party', [CmsController::class, 'updateThirdParty'])->name('third-party.update');
        Route::get('maintenance', [SystemHealthController::class, 'maintenance'])->name('maintenance.index');
        Route::post('maintenance/toggle', [SystemHealthController::class, 'toggleMaintenance'])->name('maintenance.toggle');
        Route::post('maintenance/cache', [SystemHealthController::class, 'clearCache'])->name('maintenance.cache');
        Route::get('modules', [ModuleController::class, 'index'])->name('modules.index');
        Route::post('modules/toggle', [ModuleController::class, 'toggle'])->name('modules.toggle');

        /* ---------------- OBSERVABILITY ---------------- */
        Route::get('system/health', [SystemHealthController::class, 'index'])->name('system.health');
        Route::get('system/logs', [SystemHealthController::class, 'logs'])->name('system.logs');
        Route::post('system/logs/clear', [SystemHealthController::class, 'clearLogs'])->name('system.logs.clear');
        Route::get('system/queue', [SystemHealthController::class, 'queue'])->name('system.queue');
        Route::get('system/error-logs', [SystemToolsController::class, 'errorLogs'])->name('system.error-logs');
        Route::post('system/error-logs/clear', [SystemToolsController::class, 'clearErrorLogs'])->name('system.error-logs-clear');
        Route::get('system/env-settings', [SystemToolsController::class, 'envSettings'])->name('system.env-settings');
        Route::put('system/env-settings', [SystemToolsController::class, 'updateEnvSettings'])->name('system.env-settings-update');
        Route::get('system/db-settings', [SystemToolsController::class, 'dbSettings'])->name('system.db-settings');
        Route::post('system/db-optimize', [SystemToolsController::class, 'optimizeDb'])->name('system.db-optimize');
        Route::get('system/software-update', [SystemToolsController::class, 'softwareUpdate'])->name('system.software-update');
        Route::post('system/check-update', [SystemToolsController::class, 'checkUpdate'])->name('system.check-update');

        /* ---------------- EXPORTS ---------------- */
        Route::get('export', fn () => view('admin.export.index'))->name('export.index');
        Route::get('export/products', [AnalyticsController::class, 'exportProducts'])->name('export.products');
        Route::get('export/orders', [AnalyticsController::class, 'exportOrders'])->name('export.orders');
        Route::get('export/customers', [AnalyticsController::class, 'exportCustomers'])->name('export.customers');
        Route::get('export/transactions', [AnalyticsController::class, 'exportTransactions'])->name('export.transactions');
    });
});
