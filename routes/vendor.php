<?php

declare(strict_types=1);

use App\Http\Controllers\Delivery\CashCollectController as DeliveryCashCollectController;
use App\Http\Controllers\Vendor\AuthController as VendorAuthController;
use App\Http\Controllers\Vendor\BarcodeController;
use App\Http\Controllers\Vendor\BulkImportController;
use App\Http\Controllers\Vendor\CashCollectController;
use App\Http\Controllers\Vendor\ChatController;
use App\Http\Controllers\Vendor\ClearanceSaleController;
use App\Http\Controllers\Vendor\CouponController as VendorCouponController;
use App\Http\Controllers\Vendor\CustomerController as VendorCustomerController;
use App\Http\Controllers\Vendor\DigitalProductController;
use App\Http\Controllers\Vendor\GalleryController;
use App\Http\Controllers\Vendor\InventoryController as VendorInventoryController;
use App\Http\Controllers\Vendor\InvoiceController;
use App\Http\Controllers\Vendor\LimitedStockController;
use App\Http\Controllers\Vendor\OnboardingController;
use App\Http\Controllers\Vendor\OrderController as VendorOrderController;
use App\Http\Controllers\Vendor\OrderEditController;
use App\Http\Controllers\Vendor\PosController;
use App\Http\Controllers\Vendor\ProductController as VendorProductController;
use App\Http\Controllers\Vendor\PromotionController as VendorPromotionController;
use App\Http\Controllers\Vendor\RefundController;
use App\Http\Controllers\Vendor\ReportController as VendorReportController;
use App\Http\Controllers\Vendor\ReviewController as VendorReviewController;
use App\Http\Controllers\Vendor\SettingsController as VendorSettingsController;
use App\Http\Controllers\Vendor\ShopController as VendorShopController;
use App\Http\Controllers\Vendor\StaffController;
use App\Http\Controllers\Vendor\SubscriptionController as VendorSubscriptionController;
use App\Http\Controllers\Vendor\AiController as VendorAiController;
use App\Http\Controllers\Vendor\TicketController as VendorTicketController;
use App\Http\Controllers\Vendor\WalletController as VendorWalletController;
use App\Http\Controllers\Vendor\DashboardController as VendorDashboardController;
use App\Http\Controllers\Vendor\VendorRegistrationController as VendorRegistrationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Vendor routes
|--------------------------------------------------------------------------
| The seller operating system. Route names unchanged (prefix vendor.).
*/

Route::prefix('vendor')->name('vendor.')->group(function (): void {
    Route::get('/login', [VendorAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [VendorAuthController::class, 'login']);
    Route::post('/logout', [VendorAuthController::class, 'logout'])->name('logout');
    Route::get('/register', [VendorAuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [VendorAuthController::class, 'register'])->name('register.store');
    Route::get('/register/status', [VendorRegistrationController::class, 'status'])->name('register.status');

    Route::middleware('vendor')->group(function (): void {
        /* ---------------- DASHBOARD ---------------- */
        Route::get('/dashboard', [VendorDashboardController::class, 'index'])->name('dashboard');

        /* ---------------- STORE ---------------- */
        Route::get('shop/settings', [VendorShopController::class, 'settings'])->name('shop.settings');
        Route::put('shop/settings', [VendorShopController::class, 'updateSettings'])->name('shop.update');
        Route::put('shop/vacation', [VendorShopController::class, 'toggleVacation'])->name('shop.vacation');
        Route::get('products/low-stock', [VendorProductController::class, 'lowStock'])->name('products.low-stock');
        Route::patch('products/bulk-price', [VendorProductController::class, 'bulkPriceUpdate'])->name('products.bulk-price');
        Route::post('products/{product}/varian/{varian}/gambar', [VendorProductController::class, 'simpanGambarVarian'])->name('products.varian.gambar.simpan');
        Route::delete('products/{product}/varian/{varian}/gambar', [VendorProductController::class, 'hapusGambarVarian'])->name('products.varian.gambar.hapus');
        Route::post('products/{product}/lisensi', [VendorProductController::class, 'terbitkanLisensiDigital'])->name('products.lisensi.terbit');
        Route::post('koleksi', [VendorProductController::class, 'simpanKoleksiTematik'])->name('koleksi.simpan');
        Route::post('koleksi/{product}/tambah', [VendorProductController::class, 'tambahProdukKeKoleksi'])->name('koleksi.tambah');
        Route::delete('koleksi/{product}/lepas', [VendorProductController::class, 'lepasProdukDariKoleksi'])->name('koleksi.lepas');
        Route::resource('products', VendorProductController::class);
        Route::get('inventory', [VendorInventoryController::class, 'index'])->name('inventory.index');
        Route::get('inventory/movements', [VendorInventoryController::class, 'movements'])->name('inventory.movements');
        Route::post('inventory/adjust', [VendorInventoryController::class, 'adjust'])->name('inventory.adjust');
        Route::get('limited-stock', [LimitedStockController::class, 'index'])->name('limited-stock.index');
        Route::get('gallery', [GalleryController::class, 'index'])->name('gallery.index');
        Route::get('reviews', [VendorReviewController::class, 'index'])->name('reviews.index');
        Route::put('reviews/{review}', [VendorReviewController::class, 'update'])->name('reviews.update');
        Route::get('customers', [VendorCustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{user}', [VendorCustomerController::class, 'show'])->name('customers.show');

        /* ---------------- ORDERS ---------------- */
        Route::resource('orders', VendorOrderController::class)->only(['index', 'show']);
        Route::put('orders/{order}/status', [VendorOrderController::class, 'updateStatus'])->name('orders.update-status');
        Route::get('orders/{order}/edit', [OrderEditController::class, 'edit'])->name('orders.edit');
        Route::put('orders/{order}/edit', [OrderEditController::class, 'update'])->name('orders.edit-update');
        Route::get('fulfillment', [VendorOrderController::class, 'fulfillment'])->name('fulfillment.index');
        Route::post('orders/{order}/ship', [VendorOrderController::class, 'ship'])->name('orders.ship');
        Route::get('returns', [RefundController::class, 'returns'])->name('returns.index');
        Route::get('refund', [RefundController::class, 'index'])->name('refund.index');
        Route::put('refund/{item}', [RefundController::class, 'update'])->name('refund.update');

        /* ---------------- MARKETING ---------------- */
        Route::resource('coupon', VendorCouponController::class)->except(['show']);
        Route::get('promotions', [VendorPromotionController::class, 'index'])->name('promotions.index');
        Route::post('promotions', [VendorPromotionController::class, 'store'])->name('promotions.store');
        Route::delete('promotions/{promotion}', [VendorPromotionController::class, 'destroy'])->name('promotions.destroy');
        Route::get('clearance', [ClearanceSaleController::class, 'index'])->name('clearance.index');
        Route::put('clearance', [ClearanceSaleController::class, 'update'])->name('clearance.update');
        Route::delete('clearance/{product}', [ClearanceSaleController::class, 'remove'])->name('clearance.remove');
        Route::get('coupons/campaigns', [VendorCouponController::class, 'campaigns'])->name('coupon.campaigns');

        /* ---------------- FINANCE ---------------- */
        Route::get('wallet', [VendorWalletController::class, 'index'])->name('wallet.index');
        Route::post('wallet/withdraw', [VendorWalletController::class, 'requestWithdraw'])->name('wallet.withdraw');
        Route::get('finance/revenue', [VendorWalletController::class, 'revenue'])->name('finance.revenue');
        Route::get('finance/commission', [VendorWalletController::class, 'commission'])->name('finance.commission');
        Route::get('finance/payouts', [VendorWalletController::class, 'payouts'])->name('finance.payouts');
        Route::get('cash-collect', [CashCollectController::class, 'index'])->name('cash-collect.index');
        Route::match(['get', 'post'], 'cash-collect/{collect}/mark', [CashCollectController::class, 'markCollected'])->name('cash-collect.mark');

        /* ---------------- ANALYTICS ---------------- */
        Route::get('report/products', [VendorReportController::class, 'products'])->name('report.products');
        Route::get('report/orders', [VendorReportController::class, 'orders'])->name('report.orders');
        Route::get('report/transactions', [VendorReportController::class, 'transactions'])->name('report.transactions');
        Route::get('analytics', [VendorReportController::class, 'index'])->name('analytics.index');
        Route::get('analytics/sales', [VendorReportController::class, 'sales'])->name('analytics.sales');
        Route::get('analytics/customers', [VendorReportController::class, 'customers'])->name('analytics.customers');
        Route::get('analytics/products', [VendorReportController::class, 'productsAnalytics'])->name('analytics.products');

        /* ---------------- AI ---------------- */
        Route::get('ai', [VendorAiController::class, 'index'])->name('ai.index');
        Route::post('ai/generate', [VendorAiController::class, 'generate'])->name('ai.generate');

        /* ---------------- POS / TOOLS ---------------- */
        Route::get('pos', [PosController::class, 'index'])->name('pos.index');
        Route::post('pos/order', [PosController::class, 'storeOrder'])->name('pos.store');
        Route::get('pos/held', [PosController::class, 'heldOrders'])->name('pos.held');
        Route::post('pos/{order}/resume', [PosController::class, 'resumeHeldOrder'])->name('pos.resume');
        Route::post('pos/{order}/cancel-hold', [PosController::class, 'cancelHeldOrder'])->name('pos.cancel-hold');
        Route::get('pos/{order}/print', [PosController::class, 'printInvoice'])->name('pos.print');
        Route::get('pos/{order}/print-pdf', [PosController::class, 'printInvoicePdf'])->name('pos.print-pdf');
        Route::get('bulk-import', [BulkImportController::class, 'index'])->name('bulk-import.index');
        Route::post('bulk-import', [BulkImportController::class, 'store'])->name('bulk-import.store');
        Route::get('barcode', [BarcodeController::class, 'index'])->name('barcode.index');
        Route::get('barcode/print', [BarcodeController::class, 'print'])->name('barcode.print');
        Route::get('digital', [DigitalProductController::class, 'index'])->name('digital.index');
        Route::post('digital/{product}/upload', [DigitalProductController::class, 'upload'])->name('digital.upload');
        Route::get('invoice/{order}', [InvoiceController::class, 'show'])->name('invoice.show');
        Route::get('invoice/{order}/download', [InvoiceController::class, 'download'])->name('invoice.download');

        /* ---------------- SHIPPING ---------------- */
        Route::get('shipping', [VendorSettingsController::class, 'shipping'])->name('shipping.index');
        Route::put('shipping', [VendorSettingsController::class, 'updateShipping'])->name('shipping.update');

        /* ---------------- SUPPORT ---------------- */
        Route::get('chat', [ChatController::class, 'inbox'])->name('chat.inbox');
        Route::get('chat/{conversation}', [ChatController::class, 'messages'])->name('chat.messages');
        Route::get('chat/customer/{user}', [ChatController::class, 'messagesByCustomer'])->name('chat.customer');
        Route::post('chat/send', [ChatController::class, 'send'])->name('chat.send');
        Route::get('tickets', [VendorTicketController::class, 'index'])->name('tickets.index');
        Route::get('tickets/{ticket}', [VendorTicketController::class, 'show'])->name('tickets.show');
        Route::post('tickets', [VendorTicketController::class, 'store'])->name('tickets.store');
        Route::post('tickets/{ticket}/reply', [VendorTicketController::class, 'reply'])->name('tickets.reply');
        Route::post('tickets/{ticket}/close', [VendorTicketController::class, 'close'])->name('tickets.close');
        Route::get('help', fn () => view('vendor.help.index'))->name('help.index');

        /* ---------------- SUBSCRIPTION ---------------- */
        Route::get('subscription', [VendorSubscriptionController::class, 'index'])->name('subscription.index');
        Route::post('subscription/subscribe/{plan}', [VendorSubscriptionController::class, 'subscribe'])->name('subscription.subscribe');
        Route::post('subscription/renew', [VendorSubscriptionController::class, 'renew'])->name('subscription.renew');
        Route::post('subscription/cancel', [VendorSubscriptionController::class, 'cancel'])->name('subscription.cancel');

        /* ---------------- SETTINGS ---------------- */
        Route::get('settings', [VendorSettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [VendorSettingsController::class, 'update'])->name('settings.update');
        Route::resource('staff', StaffController::class)->only(['index', 'store', 'destroy'])->names('staff');
        Route::get('notifications', [VendorSettingsController::class, 'notifications'])->name('notifications.index');
        Route::put('notifications', [VendorSettingsController::class, 'updateNotifications'])->name('notifications.update');
        Route::get('security', [VendorSettingsController::class, 'security'])->name('security.index');
        Route::put('security', [VendorSettingsController::class, 'updateSecurity'])->name('security.update');
        Route::get('restock-requests', [VendorOrderController::class, 'restockRequests'])->name('restock.index');
        Route::post('restock-requests/notify', [VendorOrderController::class, 'notifyRestock'])->name('restock.notify');

        /* ---------------- ONBOARDING ---------------- */
        Route::get('onboarding/step1', [OnboardingController::class, 'step1'])->name('onboarding.step1');
        Route::post('onboarding/step1', [OnboardingController::class, 'storeStep1'])->name('onboarding.step1.store');
        Route::get('onboarding/step2', [OnboardingController::class, 'step2'])->name('onboarding.step2');
        Route::post('onboarding/step2', [OnboardingController::class, 'storeStep2'])->name('onboarding.step2.store');
        Route::get('onboarding/step3', [OnboardingController::class, 'step3'])->name('onboarding.step3');
        Route::post('onboarding/step3', [OnboardingController::class, 'storeStep3'])->name('onboarding.step3.store');
        Route::get('onboarding/step4', [OnboardingController::class, 'step4'])->name('onboarding.step4');
        Route::post('onboarding/step4', [OnboardingController::class, 'storeStep4'])->name('onboarding.step4.store');
        Route::get('onboarding/skip', [OnboardingController::class, 'skip'])->name('onboarding.skip');
    });
});

/*
|--------------------------------------------------------------------------
| Delivery (courier) routes
|--------------------------------------------------------------------------
*/
Route::prefix('delivery')->name('delivery.')->middleware('delivery')->group(function (): void {
    Route::get('/', [DeliveryCashCollectController::class, 'index'])->name('index');
    Route::get('cash-collect', [DeliveryCashCollectController::class, 'index'])->name('cash-collect.index');
    Route::post('cash-collect/{collect}/mark', [DeliveryCashCollectController::class, 'markCollected'])->name('cash-collect.mark');
});
