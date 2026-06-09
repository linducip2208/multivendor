<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\BlogController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DealOfTheDayController;
use App\Http\Controllers\Admin\DeliveryManController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\FlashDealController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProviderController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\FileManagerController;
use App\Http\Controllers\Admin\ProductSeoController;
use App\Http\Controllers\Admin\SupportTicketController as AdminTicketController;
use App\Http\Controllers\Storefront\ProfileController;
use App\Http\Controllers\Storefront\ReviewController as StoreReviewController;
use App\Http\Controllers\Vendor\DigitalProductController;
use App\Http\Controllers\Vendor\GalleryController;
use App\Http\Controllers\Vendor\OrderEditController;
use App\Http\Controllers\Admin\PushNotificationController;
use App\Http\Controllers\Admin\DeliveryManController as AdminDeliveryManController;
use App\Http\Controllers\Admin\FeaturedDealController;
use App\Http\Controllers\Admin\MostDemandedController;
use App\Http\Controllers\Admin\WithdrawController as AdminWithdrawController;
use App\Http\Controllers\Storefront\ShopController as StoreShopController;
use App\Http\Controllers\Storefront\AuthController as SocialAuthController;
use App\Http\Controllers\Storefront\InnovativeController;
use App\Http\Controllers\Storefront\LoyaltyController;
use App\Http\Controllers\Storefront\TicketController;
use App\Http\Controllers\Storefront\WishlistController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\VendorController;
use App\Http\Controllers\PseoController;
use App\Http\Controllers\Vendor\AuthController as VendorAuthController;
use App\Http\Controllers\Vendor\BarcodeController;
use App\Http\Controllers\Vendor\BulkImportController;
use App\Http\Controllers\Vendor\ChatController;
use App\Http\Controllers\Vendor\ClearanceSaleController;
use App\Http\Controllers\Vendor\CouponController as VendorCouponController;
use App\Http\Controllers\Vendor\InvoiceController;
use App\Http\Controllers\Vendor\OrderController as VendorOrderController;
use App\Http\Controllers\Vendor\PosController;
use App\Http\Controllers\Vendor\ProductController as VendorProductController;
use App\Http\Controllers\Vendor\RefundController;
use App\Http\Controllers\Vendor\ReportController as VendorReportController;
use App\Http\Controllers\Vendor\LimitedStockController;
use App\Http\Controllers\Vendor\ShopController as VendorShopController;
use App\Http\Controllers\Vendor\WalletController as VendorWalletController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\OrderController as StoreOrderController;
use App\Http\Controllers\Storefront\ProductController as StoreProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront Routes (Customer-facing)
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    if (auth()->check()) {
        $user = auth()->user();
        if ($user->isAdmin()) return redirect()->route('admin.dashboard');
        if ($user->isVendor()) return redirect()->route('vendor.dashboard');
    }
    $featuredDeals = \App\Models\Product::where('status', 'approved')->where('featured', true)->with('shop')->take(8)->get();
    $dealOfTheDay = \App\Models\DealOfTheDay::with('product.shop')->where('date', now()->toDateString())->first();
    $flashDeals = \App\Models\FlashDeal::where('status', true)->where('start_date', '<=', now())->where('end_date', '>=', now())->with('products.shop')->take(3)->get();
    return view('storefront.home', compact('featuredDeals', 'dealOfTheDay', 'flashDeals'));
})->name('home');

Route::get('/login', function () {
    if (auth()->check()) return redirect('/');
    return view('storefront.auth.login');
})->name('login');

Route::post('/login', function () {
    $credentials = request()->validate(['email' => 'required|email', 'password' => 'required']);
    if (auth()->attempt($credentials, request()->filled('remember'))) {
        request()->session()->regenerate();
        return redirect()->intended('/');
    }
    return back()->withErrors(['email' => 'Email atau password salah.'])->onlyInput('email');
});

Route::get('/register', function () {
    if (auth()->check()) return redirect('/');
    return view('storefront.auth.register');
})->name('register');

Route::post('/register', function () {
    $validated = request()->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'phone' => 'nullable|string|max:20',
        'password' => 'required|min:6|confirmed',
        'referral_code' => 'nullable|string|exists:users,referral_code',
    ]);
    $referrer = null;
    if ($validated['referral_code'] ?? null) {
        $referrer = \App\Models\User::where('referral_code', $validated['referral_code'])->first();
    }
    $user = \App\Models\User::create([
        'name' => $validated['name'], 'email' => $validated['email'],
        'phone' => $validated['phone'], 'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
        'role' => 'customer', 'status' => 'active',
        'referral_code' => \Illuminate\Support\Str::random(8),
        'referred_by' => $referrer?->id,
    ]);
    \App\Models\Wallet::create(['user_id' => $user->id, 'balance' => 0]);
    \App\Models\LoyaltyPoint::create(['customer_id' => $user->id, 'points' => 0]);

    if ($referrer) {
        \App\Models\LoyaltyPoint::earn($referrer, 500, 'Referral bonus: ' . $user->name, 'referral', $user->id);
    }

    auth()->login($user);
    return redirect('/')->with('success', 'Akun berhasil dibuat! Selamat berbelanja.');
});

Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');
})->name('logout');

Route::get('/products', [StoreProductController::class, 'index'])->name('products.index');
Route::get('/products/{product:slug}', [StoreProductController::class, 'show'])->name('products.show');

Route::middleware('customer')->group(function () {
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
    Route::put('/cart/{cart}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/{cart}', [CartController::class, 'remove'])->name('cart.remove');

    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'process'])->name('checkout.process');
    Route::post('/checkout/shipping-cost', [CheckoutController::class, 'shippingCost'])->name('checkout.shipping-cost');

    Route::get('/orders', [StoreOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [StoreOrderController::class, 'show'])->name('orders.show');

    Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/wishlist/toggle', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
    Route::get('/compare', [WishlistController::class, 'compare'])->name('compare.index');
    Route::post('/compare/add', [WishlistController::class, 'addCompare'])->name('compare.add');
    Route::delete('/compare/{item}', [WishlistController::class, 'removeCompare'])->name('compare.remove');

    Route::resource('tickets', TicketController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('tickets.reply');

    Route::get('/track-order', function(\Illuminate\Http\Request $request){
        $order = null;
        if ($request->has('order_number')) {
            $order = \App\Models\Order::where('order_number', $request->order_number)->with(['items.product','statusHistory','shop'])->first();
        }
        return view('storefront.track-order.index', compact('order'));
    })->name('track-order');
    Route::get('/loyalty', [LoyaltyController::class, 'index'])->name('loyalty.index');
    Route::post('/loyalty/redeem', [LoyaltyController::class, 'redeem'])->name('loyalty.redeem');

    Route::get('/shop/{shop:slug}', [StoreShopController::class, 'show'])->name('shop.show');

    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/address', [ProfileController::class, 'addressStore'])->name('profile.address.store');
    Route::delete('/profile/address/{address}', [ProfileController::class, 'addressDestroy'])->name('profile.address.destroy');

    Route::post('/reviews', [StoreReviewController::class, 'store'])->name('reviews.store');

    Route::post('/restock', function(Request $request){
        $request->validate(['product_id'=>'required|exists:products,id']);
        \App\Models\RestockRequest::create(['product_id'=>$request->product_id,'customer_id'=>auth()->id(),'status'=>'pending']);
        return back()->with('success','Restock request dikirim!');
    })->name('restock.request');

    Route::get('/recommendations/{product:slug}', [InnovativeController::class, 'recommendations'])->name('recommendations');
    Route::post('/alerts', [InnovativeController::class, 'setAlert'])->name('alerts.set');
    Route::get('/bundles', [InnovativeController::class, 'bundles'])->name('bundles');
    Route::get('/group-buys', [InnovativeController::class, 'groupBuys'])->name('group-buys');
    Route::post('/group-buys/{groupBuy}/join', [InnovativeController::class, 'joinGroup'])->name('group-buys.join');
    Route::get('/feed', [InnovativeController::class, 'feed'])->name('feed');
    Route::get('/leaderboard', [InnovativeController::class, 'leaderboard'])->name('leaderboard');
    Route::get('/products/{product:slug}/price-history', [InnovativeController::class, 'priceHistory'])->name('products.price-history');

    Route::get('/page/{slug}', function($slug){
        $pages=['about'=>'Tentang Kami','terms'=>'Syarat & Ketentuan','privacy'=>'Kebijakan Privasi','return'=>'Kebijakan Pengembalian','faq'=>'FAQ'];
        if(!isset($pages[$slug])) abort(404);
        return view('storefront.pages.show',['title'=>$pages[$slug],'content'=>\App\Models\SystemSetting::get('page_'.$slug,'Konten sedang disiapkan.')]);
    })->name('page.show');

    Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('social.redirect');
    Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('social.callback');
});

Route::post('/webhook/payment/{provider}', function ($providerId) {
    return response()->json(['status' => 'ok']);
})->name('webhook.payment');

Route::get('/blog', function () {
    $posts = \App\Models\BlogPost::where('is_published', true)->where('published_at', '<=', now())->with('author')->latest()->paginate(12);
    return view('storefront.blog.index', compact('posts'));
})->name('blog.index');

Route::get('/blog/{slug}', function ($slug) {
    $post = \App\Models\BlogPost::where('slug', $slug)->where('is_published', true)->firstOrFail();
    return view('storefront.blog.show', compact('post'));
})->name('blog.show');

Route::get('/docs', function () {
    return view('storefront.docs.index');
})->name('docs');

Route::get('/sitemap.xml', [SitemapController::class, 'index']);
Route::get('/sitemap-main.xml', [SitemapController::class, 'main']);
Route::get('/sitemap-products.xml', [SitemapController::class, 'products']);
Route::get('/sitemap-categories.xml', [SitemapController::class, 'categories']);
Route::get('/sitemap-blog.xml', [SitemapController::class, 'blog']);

Route::get('/robots.txt', function () {
    return response("User-agent: *\nAllow: /\$\nAllow: /docs\nAllow: /marketing/\nAllow: /blog\nAllow: /products\nDisallow: /admin\nDisallow: /vendor\nDisallow: /api\nDisallow: /webhooks\nSitemap: /sitemap.xml", 200)->header('Content-Type', 'text/plain');
});

Route::get('/blog/feed.xml', function () {
    $posts = \App\Models\BlogPost::where('is_published', true)->where('published_at', '<=', now())->latest()->take(20)->get();
    $xml = '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>';
    $xml .= '<title>' . e(config('app.name') . ' Blog') . '</title><link>' . url('/blog') . '</link><description>Blog ' . e(config('app.name')) . '</description><language>id</language>';
    $xml .= '<atom:link href="' . url('/blog/feed.xml') . '" rel="self" type="application/rss+xml"/>';
    foreach ($posts as $post) {
        $xml .= '<item><title>' . e($post->title) . '</title><link>' . route('blog.show', $post->slug) . '</link><guid>' . route('blog.show', $post->slug) . '</guid>';
        $xml .= '<description>' . e($post->excerpt ?? strip_tags(substr($post->content, 0, 300))) . '</description><pubDate>' . $post->published_at->toRssString() . '</pubDate></item>';
    }
    $xml .= '</channel></rss>';
    return response($xml, 200)->header('Content-Type', 'application/rss+xml');
});

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

    Route::middleware('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::resource('vendors', VendorController::class)->parameters(['vendors' => 'shop'])->except(['show']);
        Route::get('vendors/{shop}', [VendorController::class, 'show'])->name('vendors.show');
        Route::put('vendors/{shop}/status', [VendorController::class, 'updateStatus'])->name('vendors.update-status');

        Route::resource('categories', CategoryController::class)->except(['show']);
        Route::resource('brands', BrandController::class)->except(['show']);

        Route::resource('products', ProductController::class)->only(['index', 'show', 'destroy']);
        Route::put('products/{product}/status', [ProductController::class, 'updateStatus'])->name('products.update-status');

        Route::resource('providers', ProviderController::class)->except(['show']);
        Route::get('providers/preset', [ProviderController::class, 'getPreset'])->name('providers.preset');

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::post('reports/ai', [ReportController::class, 'aiAnalysis'])->name('reports.ai');
        Route::post('reports/fetch-models', [ReportController::class, 'fetchModels'])->name('reports.fetch-models');

        Route::resource('coupons', CouponController::class)->except(['show']);
        Route::resource('flashdeals', FlashDealController::class)->except(['show']);
        Route::resource('orders', AdminOrderController::class)->only(['index', 'show']);
        Route::put('orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.update-status');
        Route::resource('blog', BlogController::class)->except(['show']);

        Route::resource('banners', BannerController::class)->except(['show']);
        Route::resource('customers', CustomerController::class)->only(['index', 'show']);
        Route::resource('delivery', DeliveryManController::class)->only(['index']);
        Route::resource('transactions', TransactionController::class)->only(['index']);

        Route::get('notifications', fn () => view('admin.notifications.index'))->name('notifications');
        Route::get('settings', [SettingsController::class, 'index'])->name('settings');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

        Route::resource('withdraws', AdminWithdrawController::class)->only(['index', 'update']);
        Route::put('withdraws/{withdraw}', [AdminWithdrawController::class, 'update'])->name('withdraws.update');

        Route::resource('deals', DealOfTheDayController::class)->only(['index', 'store', 'destroy']);
        Route::resource('featured-deals', FeaturedDealController::class)->only(['index', 'store']);
        Route::delete('featured-deals/{product}', [FeaturedDealController::class, 'remove'])->name('featured-deals.remove');

        Route::resource('delivery-men', AdminDeliveryManController::class);
        Route::get('delivery-men/{delivery_man}/wallet', [AdminDeliveryManController::class, 'wallet'])->name('delivery-men.wallet');

        Route::resource('push-notifications', PushNotificationController::class)->only(['index', 'store']);
        Route::post('push-notifications/{notification}/send', [PushNotificationController::class, 'send'])->name('push-notifications.send');

        Route::resource('product-seo', ProductSeoController::class)->only(['index', 'update']);
        Route::put('product-seo/{product}', [ProductSeoController::class, 'update'])->name('product-seo.update');

        Route::get('file-manager', [FileManagerController::class, 'index'])->name('file-manager.index');
        Route::post('file-manager/upload', [FileManagerController::class, 'upload'])->name('file-manager.upload');
        Route::delete('file-manager', [FileManagerController::class, 'destroy'])->name('file-manager.destroy');

        Route::get('email-templates', fn() => view('admin.email-templates.index'))->name('email-templates.index');
        Route::put('email-templates', function(Request $request){
            \App\Models\SystemSetting::set('email_'.$request->key.'_subject', $request->subject);
            \App\Models\SystemSetting::set('email_'.$request->key.'_body', $request->body);
            return back()->with('success','Template disimpan.');
        })->name('email-templates.update');

        Route::get('currency', fn() => view('admin.currency.index'))->name('currency.index');
        Route::put('currency', function(Request $request){
            foreach(['currency_code','currency_symbol','symbol_position','decimal_point'] as $k) \App\Models\SystemSetting::set($k, $request->$k);
            return back()->with('success','Mata uang disimpan.');
        })->name('currency.update');

        Route::get('offline-payment', fn() => view('admin.offline-payment.index'))->name('offline-payment.index');
        Route::put('offline-payment', function(Request $request){
            foreach(($request->methods??[]) as $key => $data){
                \App\Models\SystemSetting::set('payment_'.$key.'_active', $data['active']??null);
                \App\Models\SystemSetting::set('payment_'.$key.'_details', $data['details']??null);
            }
            return back()->with('success','Metode pembayaran disimpan.');
        })->name('offline-payment.update');

        Route::get('language', fn() => view('admin.language.index'))->name('language.index');
        Route::put('language', function(Request $request){
            foreach(($request->id??[]) as $k => $v) \App\Models\SystemSetting::set('lang_id_'.Str::slug($k), $v);
            foreach(($request->en??[]) as $k => $v) \App\Models\SystemSetting::set('lang_en_'.Str::slug($k), $v);
            return back()->with('success','Bahasa disimpan.');
        })->name('language.update');

        Route::get('stock-report', fn() => view('admin.stock-report.index'))->name('stock-report.index');
        Route::get('vendor-sale-report', fn() => view('admin.vendor-sale-report.index'))->name('vendor-sale-report.index');
        Route::get('roles', fn() => view('admin.roles.index'))->name('roles.index');
        Route::put('roles', function(Request $request){
            foreach(($request->roles??[]) as $mod => $perms)
                foreach($perms as $p => $v)
                    \App\Models\SystemSetting::set('role_'.$mod.'_'.$p, $v ? '1' : null);
            return back()->with('success','Role disimpan.');
        })->name('roles.update');

        Route::get('sms-gateway', fn() => view('admin.sms-gateway.index'))->name('sms-gateway.index');
        Route::put('sms-gateway', function(Request $request){
            foreach(['provider','api_key','api_secret','sender_id'] as $k) \App\Models\SystemSetting::set('sms_'.$k, $request->$k);
            return back()->with('success','SMS Gateway disimpan.');
        })->name('sms-gateway.update');

        Route::get('third-party', fn() => view('admin.third-party.index'))->name('third-party.index');
        Route::put('third-party', function(Request $request){
            if($request->section==='recaptcha'){ foreach(['recaptcha_site_key','recaptcha_secret_key'] as $k) \App\Models\SystemSetting::set($k, $request->$k); }
            elseif($request->section==='map'){ \App\Models\SystemSetting::set('map_api_key', $request->map_api_key); }
            elseif($request->section==='social'){ foreach(['whatsapp_number','whatsapp_message','fb_page_id'] as $k) \App\Models\SystemSetting::set($k, $request->$k); }
            elseif($request->section==='analytics'){ foreach(['ga_id','fb_pixel_id'] as $k) \App\Models\SystemSetting::set($k, $request->$k); }
            return back()->with('success','Pengaturan disimpan.');
        })->name('third-party.update');

        Route::get('maintenance', fn() => view('admin.maintenance.index'))->name('maintenance.index');
        Route::post('maintenance/toggle', function(){
            if(app()->isDownForMaintenance()){ \Illuminate\Support\Facades\Artisan::call('up'); return back()->with('success','Maintenance dinonaktifkan.'); }
            else{ \Illuminate\Support\Facades\Artisan::call('down',['--secret'=>'dev-bypass-'.config('app.key')]); return back()->with('success','Maintenance diaktifkan.'); }
        })->name('maintenance.toggle');
        Route::post('maintenance/cache', function(){ \Illuminate\Support\Facades\Artisan::call('optimize:clear'); return back()->with('success','Cache dibersihkan.'); })->name('maintenance.cache');

        Route::get('export', fn() => view('admin.export.index'))->name('export.index');
        Route::get('export/products', function(){
            $headers=['Name','SKU','Price','Stock','Shop']; $products=\App\Models\Product::where('status','approved')->with('shop')->get();
            $callback=function()use($products,$headers){ $f=fopen('php://output','w');fputcsv($f,$headers);foreach($products as $p){fputcsv($f,[$p->name,$p->sku,$p->price,$p->current_stock,$p->shop->name??'']);}fclose($f);};
            return response()->stream($callback,200,['Content-Type'=>'text/csv','Content-Disposition'=>'attachment;filename=products.csv']);
        })->name('export.products');
        Route::get('export/orders', function(){
            $headers=['Order','Customer','Shop','Total','Status','Date']; $orders=\App\Models\Order::with(['customer','shop'])->get();
            $callback=function()use($orders,$headers){ $f=fopen('php://output','w');fputcsv($f,$headers);foreach($orders as $o){fputcsv($f,[$o->order_number,$o->customer->name??'',$o->shop->name??'',$o->total,$o->order_status,$o->created_at]);}fclose($f);};
            return response()->stream($callback,200,['Content-Type'=>'text/csv','Content-Disposition'=>'attachment;filename=orders.csv']);
        })->name('export.orders');
        Route::get('export/customers', function(){
            $headers=['Name','Email','Phone','Orders','Joined']; $users=\App\Models\User::where('role','customer')->withCount('orders')->get();
            $callback=function()use($users,$headers){ $f=fopen('php://output','w');fputcsv($f,$headers);foreach($users as $u){fputcsv($f,[$u->name,$u->email,$u->phone,$u->orders_count,$u->created_at]);}fclose($f);};
            return response()->stream($callback,200,['Content-Type'=>'text/csv','Content-Disposition'=>'attachment;filename=customers.csv']);
        })->name('export.customers');
        Route::get('export/transactions', function(){
            $headers=['ID','Order','Customer','Amount','Method','Status','Date']; $tx=\App\Models\Transaction::with(['customer','order'])->get();
            $callback=function()use($tx,$headers){ $f=fopen('php://output','w');fputcsv($f,$headers);foreach($tx as $t){fputcsv($f,[$t->transaction_id,$t->order->order_number??'',$t->customer->name??'',$t->amount,$t->payment_method,$t->status,$t->created_at]);}fclose($f);};
            return response()->stream($callback,200,['Content-Type'=>'text/csv','Content-Disposition'=>'attachment;filename=transactions.csv']);
        })->name('export.transactions');

        Route::get('most-demanded', [MostDemandedController::class, 'index'])->name('most-demanded.index');

        Route::resource('employees', EmployeeController::class);

        Route::get('vat', fn()=>view('admin.vat.index'))->name('vat.index');
        Route::post('vat', function(Request $request){ \App\Models\VatTax::create($request->only(['name','rate']) + ['is_active'=>true]); return back()->with('success','Pajak ditambahkan.'); })->name('vat.store');
        Route::delete('vat/{vatTax}', function(\App\Models\VatTax $vatTax){ $vatTax->delete(); return back()->with('success','Pajak dihapus.'); })->name('vat.destroy');

        Route::get('translation', fn()=>view('admin.translation.index'))->name('translation.index');
        Route::put('translation', function(Request $request){
            if($request->key && $request->group){ \App\Models\Translation::set('id',$request->group,$request->key,$request->value_id); \App\Models\Translation::set('en',$request->group,$request->key,$request->value_en); }
            return back()->with('success','Translation disimpan.');
        })->name('translation.update');
        Route::get('support-tickets', [AdminTicketController::class, 'index'])->name('support-tickets.index');
        Route::get('support-tickets/{ticket}', [AdminTicketController::class, 'show'])->name('support-tickets.show');
        Route::put('support-tickets/{ticket}', [AdminTicketController::class, 'update'])->name('support-tickets.update');

        Route::get('pages', fn()=>view('admin.pages.index'))->name('pages.index');
        Route::put('pages', function(Request $request){ foreach(($request->pages??[]) as $k=>$v) \App\Models\SystemSetting::set('page_'.$k,$v); return back()->with('success','Halaman disimpan.'); })->name('pages.update');
        Route::get('help-topics', fn()=>view('admin.help-topics.index'))->name('help-topics.index');
        Route::put('help-topics', function(Request $request){ foreach(($request->topic_title??[]) as $i=>$v){ \App\Models\SystemSetting::set('help_topic_'.$i.'_title',$v); \App\Models\SystemSetting::set('help_topic_'.$i.'_body',$request->topic_body[$i]??''); } return back()->with('success','Help topics disimpan.'); })->name('help-topics.update');
        Route::get('contacts', fn()=>view('admin.contacts.index'))->name('contacts.index');
        Route::put('contacts', function(Request $request){ foreach(['address','email','phone','whatsapp','facebook','instagram'] as $k) \App\Models\SystemSetting::set('contact_'.$k,$request->$k); return back()->with('success','Kontak disimpan.'); })->name('contacts.update');
        Route::get('vendor-settings', fn()=>view('admin.vendor-settings.index'))->name('vendor-settings.index');
        Route::put('vendor-settings', function(Request $request){ foreach(['registration_open','default_commission','min_withdraw','auto_approve'] as $k) \App\Models\SystemSetting::set('vendor_'.$k,$request->$k); return back()->with('success','Vendor settings disimpan.'); })->name('vendor-settings.update');
        Route::get('inhouse-shop', fn()=>view('admin.inhouse-shop.index'))->name('inhouse-shop.index');
        Route::put('inhouse-shop', function(Request $request){ \App\Models\SystemSetting::set('inhouse_shop_active',$request->inhouse_active?'1':null); \App\Models\SystemSetting::set('inhouse_shop_name',$request->inhouse_name); return back()->with('success','Inhouse shop disimpan.'); })->name('inhouse-shop.update');
    });
});

/*
|--------------------------------------------------------------------------
| Vendor Routes
|--------------------------------------------------------------------------
*/

Route::prefix('vendor')->name('vendor.')->group(function () {
    Route::get('/login', [VendorAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [VendorAuthController::class, 'login']);
    Route::post('/logout', [VendorAuthController::class, 'logout'])->name('logout');

    Route::middleware('vendor')->group(function () {
        Route::get('/dashboard', function () {
            $shop = auth('vendor')->user()->shop;
            if (!$shop) return redirect()->route('vendor.login');
            $stats = [
                'total_products' => \App\Models\Product::where('shop_id', $shop->id)->count(),
                'active_products' => \App\Models\Product::where('shop_id', $shop->id)->where('status', 'approved')->count(),
                'total_orders' => \App\Models\Order::where('shop_id', $shop->id)->count(),
                'pending_orders' => \App\Models\Order::where('shop_id', $shop->id)->where('order_status', 'pending')->count(),
                'revenue' => \App\Models\Transaction::where('shop_id', $shop->id)->where('status', 'success')->sum('vendor_amount'),
                'wallet_balance' => auth('vendor')->user()->wallet?->balance ?? 0,
            ];
            $recentOrders = \App\Models\Order::where('shop_id', $shop->id)->with('customer')->latest()->take(5)->get();
            return view('vendor.dashboard', compact('stats', 'recentOrders', 'shop'));
        })->name('dashboard');

        Route::resource('products', VendorProductController::class);
        Route::resource('orders', VendorOrderController::class)->only(['index', 'show']);
        Route::put('orders/{order}/status', [VendorOrderController::class, 'updateStatus'])->name('orders.update-status');

        Route::get('wallet', [VendorWalletController::class, 'index'])->name('wallet.index');
        Route::post('wallet/withdraw', [VendorWalletController::class, 'requestWithdraw'])->name('wallet.withdraw');

        Route::get('shop/settings', [VendorShopController::class, 'settings'])->name('shop.settings');
        Route::put('shop/settings', [VendorShopController::class, 'updateSettings'])->name('shop.update');
        Route::put('shop/vacation', [VendorShopController::class, 'toggleVacation'])->name('shop.vacation');

        Route::resource('coupon', VendorCouponController::class)->except(['show']);

        Route::get('report/products', [VendorReportController::class, 'products'])->name('report.products');
        Route::get('report/orders', [VendorReportController::class, 'orders'])->name('report.orders');
        Route::get('report/transactions', [VendorReportController::class, 'transactions'])->name('report.transactions');

        Route::get('pos', [PosController::class, 'index'])->name('pos.index');
        Route::post('pos/order', [PosController::class, 'storeOrder'])->name('pos.store');

        Route::get('bulk-import', [BulkImportController::class, 'index'])->name('bulk-import.index');
        Route::post('bulk-import', [BulkImportController::class, 'store'])->name('bulk-import.store');

        Route::get('barcode', [BarcodeController::class, 'index'])->name('barcode.index');
        Route::get('barcode/print', [BarcodeController::class, 'print'])->name('barcode.print');

        Route::get('clearance', [ClearanceSaleController::class, 'index'])->name('clearance.index');
        Route::put('clearance', [ClearanceSaleController::class, 'update'])->name('clearance.update');
        Route::delete('clearance/{product}', [ClearanceSaleController::class, 'remove'])->name('clearance.remove');

        Route::get('invoice/{order}', [InvoiceController::class, 'show'])->name('invoice.show');
        Route::get('invoice/{order}/download', [InvoiceController::class, 'download'])->name('invoice.download');

        Route::get('refund', [RefundController::class, 'index'])->name('refund.index');
        Route::put('refund/{item}', [RefundController::class, 'update'])->name('refund.update');

        Route::get('chat', [ChatController::class, 'inbox'])->name('chat.inbox');
        Route::get('chat/{customer}', [ChatController::class, 'messages'])->name('chat.messages');
        Route::post('chat/send', [ChatController::class, 'send'])->name('chat.send');

        Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
        Route::put('reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');

        Route::get('limited-stock', [LimitedStockController::class, 'index'])->name('limited-stock.index');

        Route::get('digital', [DigitalProductController::class, 'index'])->name('digital.index');
        Route::post('digital/{product}/upload', [DigitalProductController::class, 'upload'])->name('digital.upload');

        Route::get('gallery', [GalleryController::class, 'index'])->name('gallery.index');

        Route::get('orders/{order}/edit', [OrderEditController::class, 'edit'])->name('orders.edit');
        Route::put('orders/{order}/edit', [OrderEditController::class, 'update'])->name('orders.edit-update');

        Route::get('restock-requests', function() {
            $shop = auth('vendor')->user()->shop;
            $requests = \App\Models\RestockRequest::whereHas('product', fn($q) => $q->where('shop_id', $shop->id))
                ->with(['product', 'customer'])->latest()->simplePaginate(15);
            return view('vendor.restock.requests.index', compact('requests'));
        })->name('restock.index');

        Route::get('shipping', fn()=>view('vendor.shipping.index'))->name('shipping.index');
        Route::put('shipping', function(Request $request){
            $shopId = auth('vendor')->user()->shop->id;
            foreach(($request->couriers??[]) as $code => $val)
                \App\Models\SystemSetting::set('shop_shipping_'.$shopId.'_'.$code, $val?'1':null);
            foreach(($request->costs??[]) as $code => $cost)
                \App\Models\SystemSetting::set('shop_shipping_'.$shopId.'_'.$code.'_cost', $cost);
            return back()->with('success','Metode pengiriman disimpan.');
        })->name('shipping.update');
    });
});
