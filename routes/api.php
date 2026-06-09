<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('products', fn(Request $r) => response()->json(['success'=>true,'data'=>\App\Models\Product::where('status','approved')->where('published',true)->with('shop','category','brand')->latest()->paginate($r->per_page??20)]));
    Route::get('products/{slug}', fn($slug) => response()->json(['success'=>true,'data'=>\App\Models\Product::where('slug',$slug)->where('status','approved')->where('published',true)->with('shop','category','brand','variants','reviews.customer')->firstOrFail()]));
    Route::get('deals', function(){
        return response()->json(['success'=>true,'data'=>[
            'deal_of_the_day'=>\App\Models\DealOfTheDay::with('product.shop')->whereDate('date',now()->toDateString())->first(),
            'featured'=>\App\Models\Product::where('status','approved')->where('featured',true)->with('shop')->take(10)->get(),
            'flash_deals'=>\App\Models\FlashDeal::where('status',true)->where('start_date','<=',now())->where('end_date','>=',now())->with('products.shop')->get(),
        ]]);
    });
    Route::get('categories', fn() => \App\Models\Category::where('status',true)->with('children')->whereNull('parent_id')->get());
    Route::get('shops', fn() => \App\Models\Shop::where('status','active')->withCount('products')->paginate(20));
    Route::get('shops/{slug}', function($slug){
        $shop = \App\Models\Shop::where('slug',$slug)->where('status','active')->with('products')->firstOrFail();
        return response()->json(['success'=>true,'data'=>$shop]);
    });
    Route::get('track/{number}', function($number){
        $o = \App\Models\Order::where('order_number',$number)->with('shop','items.product','statusHistory')->first();
        return $o ? response()->json(['success'=>true,'data'=>$o]) : response()->json(['success'=>false],404);
    });
    Route::post('login', fn(Request $r) => auth()->attempt($r->only('email','password')) ? response()->json(['success'=>true,'token'=>auth()->user()->createToken('api')->plainTextToken,'user'=>auth()->user()->load('wallet')]) : response()->json(['success'=>false,'message'=>'Invalid'],401));
    Route::post('register', function(Request $r){
        $v = $r->validate(['name'=>'required','email'=>'required|email|unique:users','password'=>'required|min:6']);
        $u = \App\Models\User::create(['name'=>$v['name'],'email'=>$v['email'],'password'=>$v['password'],'role'=>'customer','status'=>'active','referral_code'=>\Illuminate\Support\Str::random(8)]);
        \App\Models\Wallet::create(['user_id'=>$u->id,'balance'=>0]);
        \App\Models\LoyaltyPoint::create(['customer_id'=>$u->id,'points'=>0]);
        return response()->json(['success'=>true,'token'=>$u->createToken('api')->plainTextToken,'user'=>$u->load('wallet')],201);
    });

    Route::middleware('auth:sanctum')->group(function(){
        Route::get('profile', fn() => auth()->user()->load('wallet','addresses'));
        Route::put('profile', fn(Request $r) => tap(auth()->user())->update($r->only(['name','phone'])));
        Route::get('orders', fn() => \App\Models\Order::where('customer_id',auth()->id())->with('shop','items.product')->latest()->paginate(15));
        Route::get('orders/{order}', fn(\App\Models\Order $order) => $order->customer_id===auth()->id() ? $order->load('shop','items.product','items.variant','statusHistory') : abort(403));
        Route::get('cart', fn() => \App\Models\Cart::where('customer_id',auth()->id())->with('product.shop','variant')->get());
        Route::post('cart', fn(Request $r) => tap(\App\Models\Cart::firstOrCreate(['customer_id'=>auth()->id(),'product_id'=>$r->product_id,'product_variant_id'=>$r->variant_id],['quantity'=>$r->quantity,'price'=>\App\Models\Product::find($r->product_id)->getEffectivePrice()]),fn($c)=>$c->wasRecentlyCreated?:$c->increment('quantity',$r->quantity)));
        Route::delete('cart/{cart}', fn(\App\Models\Cart $cart) => $cart->customer_id===auth()->id() ? tap($cart)->delete() : abort(403));
        Route::get('wishlist', fn() => \App\Models\Wishlist::where('customer_id',auth()->id())->with('product.shop')->get());
        Route::post('wishlist/toggle', function(Request $r){ $w=\App\Models\Wishlist::where('customer_id',auth()->id())->where('product_id',$r->product_id)->first(); if($w){$w->delete();return['status'=>false];} \App\Models\Wishlist::create(['customer_id'=>auth()->id(),'product_id'=>$r->product_id]); return['status'=>true]; });
        Route::get('loyalty', fn() => ['points'=>\App\Models\LoyaltyPoint::where('customer_id',auth()->id())->first()?->points??0]);
        Route::post('loyalty/redeem', fn(Request $r) => ['amount'=>\App\Models\LoyaltyPoint::redeem(auth()->user(),(int)$r->points)]);
    });
});

Route::prefix('v2/vendor')->group(function(){
    Route::post('login', function(Request $r){
        if(!auth()->guard('vendor')->attempt($r->only('email','password'))) return response()->json(['success'=>false,'message'=>'Invalid'],401);
        return response()->json(['success'=>true,'token'=>auth()->guard('vendor')->user()->createToken('vendor-api')->plainTextToken,'shop'=>auth()->guard('vendor')->user()->shop]);
    });
    Route::middleware('auth:sanctum')->group(function(){
        Route::get('dashboard', fn() => response()->json(['success'=>true,'data'=>['products_count'=>\App\Models\Product::where('shop_id',auth()->user()->shop->id)->count(),'orders_count'=>\App\Models\Order::where('shop_id',auth()->user()->shop->id)->count(),'revenue'=>(float)\App\Models\Transaction::where('shop_id',auth()->user()->shop->id)->where('status','success')->sum('vendor_amount')]]));
        Route::get('products', fn() => \App\Models\Product::where('shop_id',auth()->user()->shop->id)->latest()->paginate(20));
        Route::get('orders', fn() => \App\Models\Order::where('shop_id',auth()->user()->shop->id)->with('customer:id,name,email')->latest()->paginate(15));
        Route::put('orders/{order}/status', function(Request $r, \App\Models\Order $order){ if($order->shop_id!==auth()->user()->shop->id)abort(403); $order->update(['order_status'=>$r->status]); return['success'=>true]; });
    });
});

Route::prefix('v3/delivery')->group(function(){
    Route::post('login', function(Request $r){
        if(!auth()->attempt($r->only('email','password'))||auth()->user()->role!=='delivery') return response()->json(['success'=>false],403);
        return response()->json(['success'=>true,'token'=>auth()->user()->createToken('delivery-api')->plainTextToken,'user'=>auth()->user()->load('wallet')]);
    });
    Route::middleware('auth:sanctum')->group(function(){
        Route::get('orders', fn() => \App\Models\Order::where('delivery_man_id',auth()->id())->with('customer:id,name,phone','shop:id,name,address')->latest()->paginate(15));
        Route::put('orders/{order}/status', fn(Request $r, \App\Models\Order $order) => $order->delivery_man_id===auth()->id() ? tap($order)->update(['order_status'=>$r->status]) : abort(403));
        Route::get('profile', fn() => auth()->user()->load('wallet'));
    });
});
