<?php
namespace App\Http\Controllers\Storefront;
use App\Http\Controllers\Controller;
use App\Models\ProductBundle;
use App\Models\GroupBuy;
use App\Models\PriceAlert;
use App\Models\SocialFeed;
use App\Models\CustomerBadge;
use App\Models\Product;
use Illuminate\Http\Request;

class InnovativeController extends Controller
{
    // AI Recommendations — "Customers also bought"
    public function recommendations(Product $product) {
        $categoryId = $product->category_id;
        $boughtTogether = Product::where('status','approved')->where('category_id',$categoryId)
            ->where('id','!=',$product->id)->with('shop')->inRandomOrder()->take(6)->get();
        return view('storefront.recommendations',compact('product','boughtTogether'));
    }

    // Price Alerts
    public function setAlert(Request $request) {
        $request->validate(['product_id'=>'required|exists:products,id','target_price'=>'required|numeric|min:0']);
        PriceAlert::updateOrCreate(['customer_id'=>auth()->id(),'product_id'=>$request->product_id],['target_price'=>$request->target_price,'notified'=>false]);
        return back()->with('success','Alert harga disetel! Kami akan notifikasi saat harga turun ke Rp '.number_format($request->target_price,0,',','.'));
    }

    // Bundles
    public function bundles() {
        $bundles = ProductBundle::where('is_active',true)->with('products.shop')->get();
        return view('storefront.bundles',compact('bundles'));
    }

    // Group Buy
    public function groupBuys() {
        $groups = GroupBuy::where('is_active',true)->where('end_date','>',now())->with('product.shop')->latest()->get();
        return view('storefront.group-buys',compact('groups'));
    }
    public function joinGroup(GroupBuy $groupBuy) {
        GroupBuyParticipant::firstOrCreate(['group_buy_id'=>$groupBuy->id,'customer_id'=>auth()->id()]);
        $groupBuy->increment('current_count');
        return back()->with('success','Anda bergabung! Butuh '.($groupBuy->target_count - $groupBuy->current_count).' orang lagi.');
    }

    // Social Feed
    public function feed() {
        $feeds = SocialFeed::where('is_active',true)->with(['product.shop','shop'])->latest()->paginate(10);
        return view('storefront.social-feed',compact('feeds'));
    }

    // Gamification
    public function leaderboard() {
        $top = \App\Models\User::where('role','customer')
            ->withSum('orders as total_spent','total')
            ->orderByDesc('total_spent')->take(20)->get();
        $badges = CustomerBadge::with('customer')->latest()->get();
        return view('storefront.leaderboard',compact('top','badges'));
    }

    // Price History API for product detail
    public function priceHistory(Product $product) {
        $history = \App\Models\OrderItem::where('product_id',$product->id)
            ->whereHas('order',fn($q)=>$q->where('order_status','!=','canceled'))
            ->selectRaw('DATE(created_at) as date, AVG(price) as avg_price')
            ->groupBy('date')->orderBy('date','desc')->take(30)->get();
        return response()->json($history);
    }
}
