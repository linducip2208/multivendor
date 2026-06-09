<?php

/*
|--------------------------------------------------------------------------
| API v1 — Flutter Customer App
|--------------------------------------------------------------------------
| Base: /api/v1/*
| Auth: Sanctum Bearer Token (from /login)
*/

use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BannerController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CompareController;
use App\Http\Controllers\Api\V1\LoyaltyController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PriceAlertController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\RestockController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\ShopController;
use App\Http\Controllers\Api\V1\ShopFollowerController;
use App\Http\Controllers\Api\V1\SocialController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\WishlistController;
use Illuminate\Support\Facades\Route;

// ══════════════════ PUBLIC (no auth) ══════════════════
Route::prefix('v1')->group(function () {
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{slug}', [ProductController::class, 'show']);
    Route::get('products/{slug}/reviews', [ReviewController::class, 'productReviews']);
    Route::get('products/{slug}/price-history', [PriceAlertController::class, 'history']);
    Route::get('deals', [ProductController::class, 'deals']);
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('shops', [ShopController::class, 'index']);
    Route::get('shops/{slug}', [ShopController::class, 'show']);
    Route::get('banners', [BannerController::class, 'index']);
    Route::get('feed', [SocialController::class, 'feed']);
    Route::get('bundles', [SocialController::class, 'bundles']);
    Route::get('leaderboard', [SocialController::class, 'leaderboard']);
    Route::get('group-buys', [SocialController::class, 'groupBuys']);
    Route::get('track/{number}', [OrderController::class, 'track']);

    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);

    // ══════════════════ AUTHENTICATED ══════════════════
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('profile', [AuthController::class, 'profile']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::put('password', [AuthController::class, 'changePassword']);

        Route::get('cart', [CartController::class, 'index']);
        Route::post('cart', [CartController::class, 'add']);
        Route::put('cart/{cart}', [CartController::class, 'update']);
        Route::delete('cart/{cart}', [CartController::class, 'remove']);

        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{order}', [OrderController::class, 'show']);
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);

        Route::get('wishlist', [WishlistController::class, 'index']);
        Route::post('wishlist/toggle', [WishlistController::class, 'toggle']);

        Route::get('compare', [CompareController::class, 'index']);
        Route::post('compare', [CompareController::class, 'toggle']);
        Route::delete('compare/{item}', [CompareController::class, 'remove']);

        Route::get('addresses', [AddressController::class, 'index']);
        Route::post('addresses', [AddressController::class, 'store']);
        Route::put('addresses/{address}', [AddressController::class, 'update']);
        Route::delete('addresses/{address}', [AddressController::class, 'destroy']);

        Route::get('loyalty', [LoyaltyController::class, 'index']);
        Route::post('loyalty/redeem', [LoyaltyController::class, 'redeem']);

        Route::get('tickets', [TicketController::class, 'index']);
        Route::post('tickets', [TicketController::class, 'store']);
        Route::get('tickets/{ticket}', [TicketController::class, 'show']);
        Route::post('tickets/{ticket}/reply', [TicketController::class, 'reply']);

        Route::post('reviews', [ReviewController::class, 'store']);
        Route::post('alerts', [PriceAlertController::class, 'set']);
        Route::post('restock', [RestockController::class, 'request']);

        Route::post('shops/{shop}/follow', [ShopFollowerController::class, 'follow']);
        Route::delete('shops/{shop}/unfollow', [ShopFollowerController::class, 'unfollow']);

        Route::post('group-buys/{groupBuy}/join', [SocialController::class, 'joinGroup']);
    });
});

/*
|--------------------------------------------------------------------------
| API v2 — Flutter Vendor App
|--------------------------------------------------------------------------
| Base: /api/v2/vendor/*
| Auth: Sanctum (vendor guard)
*/
Route::prefix('v2/vendor')->group(function () {
    Route::post('login', function (\Illuminate\Http\Request $request) {
        if (!auth()->guard('vendor')->attempt($request->only('email','password')))
            return response()->json(['success'=>false,'message'=>'Email atau password salah'],401);
        $token = auth()->guard('vendor')->user()->createToken('vendor-app')->plainTextToken;
        return response()->json(['success'=>true,'token'=>$token,'shop'=>auth()->guard('vendor')->user()->shop]);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('dashboard', function(){
            $shop = auth('vendor')->user()->shop;
            return response()->json(['success'=>true,'data'=>[
                'products_count'=>\App\Models\Product::where('shop_id',$shop->id)->count(),
                'active_products'=>\App\Models\Product::where('shop_id',$shop->id)->where('status','approved')->count(),
                'orders_count'=>\App\Models\Order::where('shop_id',$shop->id)->count(),
                'pending_orders'=>\App\Models\Order::where('shop_id',$shop->id)->where('order_status','pending')->count(),
                'revenue'=>(float)\App\Models\Transaction::where('shop_id',$shop->id)->where('status','success')->sum('vendor_amount'),
                'wallet_balance'=>(float)(auth()->user()->wallet?->balance ?? 0),
            ]]);
        });

        Route::get('products', fn()=>response()->json(['success'=>true,'data'=>\App\Models\Product::where('shop_id',auth()->user()->shop->id)->with('category:id,name')->latest()->paginate(15)]));
        Route::post('products', function(\Illuminate\Http\Request $request){
            $shop = auth()->user()->shop;
            $v = $request->validate(['name'=>'required','category_id'=>'required|exists:categories,id','price'=>'required|numeric|min:0','current_stock'=>'required|integer|min:0']);
            $v['shop_id']=$shop->id; $v['slug']=\Illuminate\Support\Str::slug($v['name']); $v['status']='pending'; $v['created_by']='vendor'; $v['published']=true;
            $p = \App\Models\Product::create($v);
            return response()->json(['success'=>true,'data'=>$p,'message'=>'Produk dibuat'],201);
        });

        Route::get('orders', fn()=>response()->json(['success'=>true,'data'=>\App\Models\Order::where('shop_id',auth()->user()->shop->id)->with('customer:id,name,email')->latest()->paginate(15)]));
        Route::get('orders/{order}', function(\App\Models\Order $order){
            if($order->shop_id!==auth()->user()->shop->id)abort(403);
            return response()->json(['success'=>true,'data'=>$order->load('customer:id,name,email','items.product:id,name,thumbnail','statusHistory')]);
        });
        Route::put('orders/{order}/status', function(\Illuminate\Http\Request $request, \App\Models\Order $order){
            if($order->shop_id!==auth()->user()->shop->id)abort(403);
            $order->update(['order_status'=>$request->status,$request->status.'_at'=>now()]);
            $order->statusHistory()->create(['status'=>$request->status,'changed_by'=>auth()->id(),'note'=>$request->note]);
            return response()->json(['success'=>true,'message'=>'Status updated']);
        });

        Route::get('reports', function(){
            $shop = auth()->user()->shop;
            return response()->json(['success'=>true,'data'=>[
                'revenue'=>(float)\App\Models\Transaction::where('shop_id',$shop->id)->where('status','success')->sum('vendor_amount'),
                'top_products'=>\App\Models\Product::where('shop_id',$shop->id)->withSum(['orderItems as sold'=>fn($q)=>$q->whereHas('order',fn($o)=>$o->where('order_status','!=','canceled'))],'quantity')->withSum(['orderItems as revenue'=>fn($q)=>$q->whereHas('order',fn($o)=>$o->where('order_status','!=','canceled'))],'sub_total')->orderByDesc('revenue')->take(10)->get(),
            ]]);
        });

        Route::get('profile', fn()=>response()->json(['success'=>true,'data'=>auth()->user()->load('shop','wallet')]));
    });
});

/*
|--------------------------------------------------------------------------
| API v3 — Flutter Delivery Man App
|--------------------------------------------------------------------------
| Base: /api/v3/delivery/*
*/
Route::prefix('v3/delivery')->group(function () {
    Route::post('login', function (\Illuminate\Http\Request $request) {
        if (!auth()->attempt($request->only('email','password'))) return response()->json(['success'=>false,'message'=>'Invalid'],401);
        if (auth()->user()->role !== 'delivery') return response()->json(['success'=>false,'message'=>'Bukan kurir'],403);
        $token = auth()->user()->createToken('delivery-app')->plainTextToken;
        return response()->json(['success'=>true,'token'=>$token,'user'=>auth()->user()->load('wallet')]);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('orders', fn()=>response()->json(['success'=>true,'data'=>\App\Models\Order::where('delivery_man_id',auth()->id())->with('customer:id,name,phone','shop:id,name,address,phone')->latest()->paginate(15)]));
        Route::put('orders/{order}/status', function(\Illuminate\Http\Request $request, \App\Models\Order $order){
            if($order->delivery_man_id!==auth()->id())abort(403);
            $order->update(['order_status'=>$request->status]);
            return response()->json(['success'=>true,'message'=>'Status updated']);
        });
        Route::get('wallet', fn()=>response()->json(['success'=>true,'data'=>['balance'=>(float)(auth()->user()->wallet?->balance??0),'earnings'=>\App\Models\DeliveryManEarning::where('delivery_man_id',auth()->id())->latest()->paginate(15)]]));
        Route::get('profile', fn()=>response()->json(['success'=>true,'data'=>auth()->user()->load('wallet')]));
    });
});
