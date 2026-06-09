<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\DealOfTheDay;
use App\Models\FlashDeal;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiResponse
{
    public static function success($data, $message = 'OK', $code = 200) {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $code);
    }
    public static function error($message, $code = 400) {
        return response()->json(['success' => false, 'message' => $message], $code);
    }
    public static function paginated($paginator, $message = 'OK') {
        return response()->json([
            'success' => true, 'message' => $message,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        ]);
    }
}

class ProductController extends Controller
{
    public function index(Request $request) {
        $q = Product::where('status','approved')->where('published',true)->with(['shop','category','brand'])->latest();
        if($request->category_id) $q->where('category_id',$request->category_id);
        if($request->search) $q->where('name','like',"%{$request->search}%");
        if($request->featured) $q->where('featured',true);
        if($request->shop_id) $q->where('shop_id',$request->shop_id);
        if($request->min_price) $q->where('price','>=',$request->min_price);
        if($request->max_price) $q->where('price','<=',$request->max_price);
        if($request->product_type) $q->where('product_type',$request->product_type);
        if($request->sort) {
            match($request->sort) {
                'price_asc' => $q->orderBy('price','asc'),
                'price_desc' => $q->orderBy('price','desc'),
                'popular' => $q->withCount('orderItems')->orderByDesc('order_items_count'),
                default => $q->latest()
            };
        }
        return ApiResponse::paginated($q->paginate($request->per_page ?? 20));
    }

    public function show($slug) {
        $p = Product::where('slug',$slug)->where('status','approved')->where('published',true)
            ->with(['shop:id,name,slug','category:id,name,slug','brand:id,name,slug','variants','reviews.customer:id,name'])->first();
        if(!$p) return ApiResponse::error('Product not found', 404);
        if($p->thumbnail) $p->thumbnail = asset('storage/'.$p->thumbnail);
        if($p->images) { $images = json_decode($p->images,true); $p->images = array_map(fn($i)=>asset('storage/'.$i), $images ?? []); }
        if($p->video_url && str_starts_with($p->video_url,'videos/')) $p->video_url = asset('storage/'.$p->video_url);
        return ApiResponse::success($p);
    }

    public function deals() {
        return ApiResponse::success([
            'deal_of_the_day' => DealOfTheDay::with('product.shop:id,name')->whereDate('date',now()->toDateString())->first(),
            'featured' => Product::where('status','approved')->where('featured',true)->with('shop:id,name')->take(10)->get()->map(function($p){ if($p->thumbnail)$p->thumbnail=asset('storage/'.$p->thumbnail); return $p; }),
            'flash_deals' => FlashDeal::where('status',true)->where('start_date','<=',now())->where('end_date','>=',now())->with(['products'=>fn($q)=>$q->with('shop:id,name')])->get(),
        ]);
    }
}

class CategoryController extends Controller
{
    public function index() { return ApiResponse::success(Category::where('status',true)->with('children')->whereNull('parent_id')->get()); }
}

class ShopController extends Controller
{
    public function index() { return ApiResponse::paginated(Shop::where('status','active')->withCount('products')->paginate(20)); }
    public function show($slug) {
        $shop = Shop::where('slug',$slug)->where('status','active')->with(['products'=>fn($q)=>$q->where('status','approved')->where('published',true)->latest()->paginate(20)])->first();
        if(!$shop) return ApiResponse::error('Shop not found',404);
        return ApiResponse::success($shop);
    }
}

class AuthController extends Controller
{
    public function login(Request $request) {
        $request->validate(['email'=>'required|email','password'=>'required']);
        if(!auth()->attempt($request->only('email','password'))) return ApiResponse::error('Email atau password salah',401);
        $user = auth()->user()->load('wallet');
        $token = $user->createToken('flutter-app')->plainTextToken;
        return ApiResponse::success(['token'=>$token,'user'=>$user]);
    }
    public function register(Request $request) {
        $v = $request->validate(['name'=>'required','email'=>'required|email|unique:users','password'=>'required|min:6','phone'=>'nullable','referral_code'=>'nullable|exists:users,referral_code']);
        $referrer = $v['referral_code'] ? User::where('referral_code',$v['referral_code'])->first() : null;
        $user = User::create(['name'=>$v['name'],'email'=>$v['email'],'password'=>Hash::make($v['password']),'phone'=>$v['phone']??null,'role'=>'customer','status'=>'active','referral_code'=>Str::random(8),'referred_by'=>$referrer?->id]);
        \App\Models\Wallet::create(['user_id'=>$user->id,'balance'=>0]);
        \App\Models\LoyaltyPoint::create(['customer_id'=>$user->id,'points'=>0]);
        if($referrer) \App\Models\LoyaltyPoint::earn($referrer,500,'Referral: '.$user->name,'referral',$user->id);
        $token = $user->createToken('flutter-app')->plainTextToken;
        return ApiResponse::success(['token'=>$token,'user'=>$user->load('wallet')],'Register berhasil',201);
    }
    public function profile() { return ApiResponse::success(auth()->user()->load(['wallet','addresses'])); }
    public function updateProfile(Request $request) { auth()->user()->update($request->only(['name','phone','avatar'])); return ApiResponse::success(auth()->user()->fresh()); }
    public function changePassword(Request $request) {
        $request->validate(['current_password'=>'required','new_password'=>'required|min:6']);
        if(!Hash::check($request->current_password,auth()->user()->password)) return ApiResponse::error('Password saat ini salah',422);
        auth()->user()->update(['password'=>Hash::make($request->new_password)]);
        return ApiResponse::success(null,'Password berhasil diubah');
    }
}

class CartController extends Controller
{
    public function index() {
        $items = \App\Models\Cart::where('customer_id',auth()->id())->with(['product'=>fn($q)=>$q->select('id','shop_id','name','slug','thumbnail','price','special_price'),'product.shop:id,name','variant'])->get()->map(function($i){
            if($i->product && $i->product->thumbnail) $i->product->thumbnail = asset('storage/'.$i->product->thumbnail);
            return $i;
        });
        return ApiResponse::success($items);
    }
    public function add(Request $request) {
        $request->validate(['product_id'=>'required|exists:products,id','quantity'=>'required|integer|min:1','variant_id'=>'nullable|exists:product_variants,id']);
        $p = Product::findOrFail($request->product_id);
        $price = $p->getEffectivePrice();
        $existing = \App\Models\Cart::where('customer_id',auth()->id())->where('product_id',$p->id)->where('product_variant_id',$request->variant_id)->first();
        if($existing){ $existing->increment('quantity',$request->quantity); $existing->update(['price'=>$price]); }
        else \App\Models\Cart::create(['customer_id'=>auth()->id(),'product_id'=>$p->id,'product_variant_id'=>$request->variant_id,'quantity'=>$request->quantity,'price'=>$price,'tax'=>$p->tax]);
        return ApiResponse::success(null,'Ditambahkan ke keranjang');
    }
    public function update(Request $request, \App\Models\Cart $cart) { if($cart->customer_id!==auth()->id()) abort(403); $cart->update(['quantity'=>$request->quantity]); return ApiResponse::success(null,'Diperbarui'); }
    public function remove(\App\Models\Cart $cart) { if($cart->customer_id!==auth()->id()) abort(403); $cart->delete(); return ApiResponse::success(null,'Dihapus'); }
}

class OrderController extends Controller
{
    public function index() { return ApiResponse::paginated(Order::where('customer_id',auth()->id())->with(['shop:id,name','items.product:id,name,thumbnail'])->latest()->paginate(15)); }
    public function show(Order $order) { if($order->customer_id!==auth()->id())abort(403); return ApiResponse::success($order->load(['shop','items.product','items.variant','statusHistory'])); }
    public function track($number) { $o=Order::where('order_number',$number)->with(['shop:id,name','items.product:id,name,thumbnail','statusHistory'])->first(); if(!$o)return ApiResponse::error('Order tidak ditemukan',404); return ApiResponse::success($o); }
    public function cancel(Order $order) {
        if($order->customer_id!==auth()->id())abort(403);
        if(!in_array($order->order_status,['pending','confirmed'])) return ApiResponse::error('Pesanan tidak bisa dibatalkan',422);
        $order->update(['order_status'=>'canceled','canceled_at'=>now()]);
        $order->statusHistory()->create(['status'=>'canceled','changed_by'=>auth()->id()]);
        app(\App\Services\OrderService::class)->cancel($order);
        return ApiResponse::success(null,'Pesanan dibatalkan');
    }
}

class WishlistController extends Controller
{
    public function index() { return ApiResponse::success(\App\Models\Wishlist::where('customer_id',auth()->id())->with('product.shop:id,name')->latest()->get()->map(function($i){if($i->product&&$i->product->thumbnail)$i->product->thumbnail=asset('storage/'.$i->product->thumbnail);return $i;})); }
    public function toggle(Request $request) { $e=\App\Models\Wishlist::where('customer_id',auth()->id())->where('product_id',$request->product_id)->first(); if($e){$e->delete(); return ApiResponse::success(['status'=>false],'Dihapus dari wishlist');} \App\Models\Wishlist::create(['customer_id'=>auth()->id(),'product_id'=>$request->product_id]); return ApiResponse::success(['status'=>true],'Ditambahkan ke wishlist'); }
}

class LoyaltyController extends Controller
{
    public function index() { return ApiResponse::success(['points'=>\App\Models\LoyaltyPoint::where('customer_id',auth()->id())->first()?->points??0,'transactions'=>\App\Models\LoyaltyTransaction::where('customer_id',auth()->id())->latest()->paginate(15)]); }
    public function redeem(Request $request) { $amount=\App\Models\LoyaltyPoint::redeem(auth()->user(),(int)$request->points); if($amount<=0)return ApiResponse::error('Poin tidak cukup',422); return ApiResponse::success(['amount'=>$amount],'Poin ditukar ke wallet'); }
}

class AddressController extends Controller
{
    public function index() { return ApiResponse::success(auth()->user()->addresses); }
    public function store(Request $request) {
        $v=$request->validate(['label'=>'required','receiver_name'=>'required','receiver_phone'=>'required','address'=>'required','city'=>'required','province'=>'required','postal_code'=>'nullable']);
        if($request->is_default) \App\Models\CustomerAddress::where('customer_id',auth()->id())->update(['is_default'=>false]);
        return ApiResponse::success(\App\Models\CustomerAddress::create($v+['customer_id'=>auth()->id(),'is_default'=>$request->boolean('is_default')]),'Alamat ditambahkan',201);
    }
    public function update(Request $request, \App\Models\CustomerAddress $address) { if($address->customer_id!==auth()->id())abort(403); $address->update($request->all()); return ApiResponse::success($address); }
    public function destroy(\App\Models\CustomerAddress $address) { if($address->customer_id!==auth()->id())abort(403); $address->delete(); return ApiResponse::success(null,'Alamat dihapus'); }
}

class CompareController extends Controller
{
    public function index() { return ApiResponse::success(\App\Models\CompareList::where('customer_id',auth()->id())->with('product.shop:id,name')->get()); }
    public function toggle(Request $request) { \App\Models\CompareList::firstOrCreate(['customer_id'=>auth()->id(),'product_id'=>$request->product_id]); return ApiResponse::success(null,'Ditambahkan'); }
    public function remove(\App\Models\CompareList $item) { if($item->customer_id!==auth()->id())abort(403); $item->delete(); return ApiResponse::success(null,'Dihapus'); }
}

class BannerController extends Controller
{
    public function index() { return ApiResponse::success(\App\Models\Banner::where('status',true)->orderBy('sort_order')->get()); }
}

class TicketController extends Controller
{
    public function index() { return ApiResponse::paginated(SupportTicket::where('customer_id',auth()->id())->latest()->paginate(10)); }
    public function store(Request $request) {
        $v=$request->validate(['subject'=>'required','type'=>'required','priority'=>'required','description'=>'required']);
        return ApiResponse::success(\App\Models\SupportTicket::create($v+['customer_id'=>auth()->id(),'status'=>'open']),'Tiket dibuat',201);
    }
    public function show(\App\Models\SupportTicket $ticket) { if($ticket->customer_id!==auth()->id())abort(403); return ApiResponse::success($ticket->load('replies.user:id,name')); }
    public function reply(Request $request, \App\Models\SupportTicket $ticket) { if($ticket->customer_id!==auth()->id())abort(403); return ApiResponse::success(\App\Models\SupportTicketReply::create(['support_ticket_id'=>$ticket->id,'user_id'=>auth()->id(),'message'=>$request->message]),'Balasan dikirim',201); }
}

class SocialController extends Controller
{
    public function feed() { return ApiResponse::paginated(\App\Models\SocialFeed::where('is_active',true)->with(['product:id,name,slug,thumbnail,price','shop:id,name'])->latest()->paginate(10)); }
    public function leaderboard() { return ApiResponse::success(\App\Models\User::where('role','customer')->withSum('orders as total_spent','total')->orderByDesc('total_spent')->take(20)->get(['id','name','total_spent'])); }
    public function bundles() { return ApiResponse::success(\App\Models\ProductBundle::where('is_active',true)->with('products.shop:id,name')->get()); }
    public function groupBuys() { return ApiResponse::paginated(\App\Models\GroupBuy::where('is_active',true)->where('end_date','>',now())->with('product.shop:id,name')->latest()->paginate(10)); }
    public function joinGroup(\App\Models\GroupBuy $groupBuy) { \App\Models\GroupBuyParticipant::firstOrCreate(['group_buy_id'=>$groupBuy->id,'customer_id'=>auth()->id()]); $groupBuy->increment('current_count'); return ApiResponse::success($groupBuy->fresh(),'Berhasil bergabung'); }
}

class ReviewController extends Controller
{
    public function store(Request $request) {
        $v=$request->validate(['product_id'=>'required|exists:products,id','rating'=>'required|integer|min:1|max:5','comment'=>'nullable|string']);
        return ApiResponse::success(\App\Models\ProductReview::create($v+['customer_id'=>auth()->id(),'status'=>true]),'Ulasan dikirim',201);
    }
    public function productReviews(Product $product) { return ApiResponse::success($product->reviews()->where('status',true)->with('customer:id,name')->latest()->get()); }
}

class PriceAlertController extends Controller
{
    public function set(Request $request) { \App\Models\PriceAlert::updateOrCreate(['customer_id'=>auth()->id(),'product_id'=>$request->product_id],['target_price'=>$request->target_price,'notified'=>false]); return ApiResponse::success(null,'Alert disetel'); }
    public function history(Product $product) { return ApiResponse::success(\App\Models\OrderItem::where('product_id',$product->id)->whereHas('order',fn($q)=>$q->where('order_status','!=','canceled'))->selectRaw('DATE(created_at) as date, AVG(price) as avg_price')->groupBy('date')->orderBy('date','desc')->take(30)->get()); }
}

class RestockController extends Controller
{
    public function request(Request $request) { \App\Models\RestockRequest::create(['product_id'=>$request->product_id,'customer_id'=>auth()->id(),'status'=>'pending']); return ApiResponse::success(null,'Restock request dikirim'); }
}

class ShopFollowerController extends Controller
{
    public function follow(Request $request) { $f=\App\Models\ShopFollower::firstOrCreate(['shop_id'=>$request->shop_id,'customer_id'=>auth()->id()]); return ApiResponse::success(['following'=>true]); }
    public function unfollow(Request $request) { \App\Models\ShopFollower::where('shop_id',$request->shop_id)->where('customer_id',auth()->id())->delete(); return ApiResponse::success(['following'=>false]); }
}
