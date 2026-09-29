<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ShopPublicResource;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Api\PersonalAccessTokenIssuer;
use App\Services\OrderWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MarketplaceApiController extends Controller
{
    public function products(Request $request)
    {
        $data = $request->validate(['per_page' => 'nullable|integer|min:1|max:100', 'search' => 'nullable|string|max:100', 'shop_id' => 'nullable|integer', 'category_id' => 'nullable|integer']);
        $query = Product::where('status', 'approved')->where('published', true)->whereHas('shop', fn ($shop) => $shop->where('status', 'active'))->with(['shop', 'category', 'brand'])->latest();
        if (! empty($data['search'])) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$data['search'].'%')->orWhere('sku', 'like', '%'.$data['search'].'%'));
        }
        foreach (['shop_id', 'category_id'] as $field) {
            if (! empty($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }

        return ProductResource::collection($query->paginate($data['per_page'] ?? 20))->additional(['success' => true, 'message' => 'OK']);
    }

    public function product(string $slug)
    {
        $product = Product::where('slug', $slug)->where('status', 'approved')->where('published', true)->whereHas('shop', fn ($shop) => $shop->where('status', 'active'))->with(['shop', 'category', 'brand', 'variants', 'reviews.customer'])->firstOrFail();

        return $this->success(new ProductResource($product));
    }

    public function categories()
    {
        return $this->success(Category::where('status', true)->with('children')->whereNull('parent_id')->get(['id', 'name', 'slug', 'parent_id']));
    }

    public function shops()
    {
        return ShopPublicResource::collection(Shop::where('status', 'active')->withCount('products')->paginate(20))->additional(['success' => true, 'message' => 'OK']);
    }

    public function shop(string $slug)
    {
        return $this->success(new ShopPublicResource(Shop::where('slug', $slug)->where('status', 'active')->withCount('products')->firstOrFail()));
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! auth()->attempt($data)) {
            return $this->error('Email atau password salah.', 401);
        }
        $user = $request->user();
        if (! $user->isCustomer()) {
            return $this->error('Akun customer diperlukan.', 403);
        }

        return $this->success(['token' => $this->issueToken($user, 'customer-api'), 'user' => new CustomerResource($user->load('wallet'))], 'Login berhasil');
    }

    public function register(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email', 'password' => 'required|string|min:8']);
        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password']), 'role' => 'customer', 'status' => 'active', 'referral_code' => Str::random(8)]);
        Wallet::create(['user_id' => $user->id, 'balance' => 0]);

        return $this->success(['token' => $this->issueToken($user, 'customer-api'), 'user' => new CustomerResource($user->load('wallet'))], 'Registrasi berhasil', 201);
    }

    public function profile(Request $request)
    {
        return $this->success(new CustomerResource($request->user()->load('wallet')));
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate(['name' => 'sometimes|string|max:255', 'phone' => 'nullable|string|max:20']);
        $request->user()->update($data);

        return $this->success(new CustomerResource($request->user()->fresh('wallet')), 'Profil diperbarui');
    }

    public function orders(Request $request)
    {
        return OrderResource::collection(Order::where('customer_id', $request->user()->id)->with(['shop', 'items.product'])->latest()->paginate(15))->additional(['success' => true, 'message' => 'OK']);
    }

    public function order(Request $request, Order $order)
    {
        abort_unless($order->customer_id === $request->user()->id, 403);

        return $this->success(new OrderResource($order->load(['shop', 'items.product', 'statusHistory'])));
    }

    public function track(Request $request, string $number)
    {
        $order = Order::where('order_number', $number)->where('customer_id', $request->user()->id)->with(['shop', 'statusHistory'])->firstOrFail();

        return $this->success(['order_number' => $order->order_number, 'status' => $order->order_status, 'tracking' => $order->shipping_tracking_id, 'courier' => $order->shipping_method, 'history' => $order->statusHistory->map(fn ($row) => ['status' => $row->status, 'at' => $row->created_at])]);
    }

    public function cart(Request $request)
    {
        return $this->success(Cart::where('customer_id', $request->user()->id)->with(['product.shop', 'variant'])->get()->map(fn ($cart) => ['id' => $cart->id, 'quantity' => $cart->quantity, 'price' => (float) $cart->price, 'product' => new ProductResource($cart->product), 'variant_id' => $cart->product_variant_id]));
    }

    public function addCart(Request $request)
    {
        $data = $request->validate(['product_id' => 'required|integer|exists:products,id', 'variant_id' => 'nullable|integer|exists:product_variants,id', 'quantity' => 'required|integer|min:1']);
        $product = Product::with('shop')->findOrFail($data['product_id']);
        $variant = ! empty($data['variant_id']) ? ProductVariant::whereKey($data['variant_id'])->where('product_id', $product->id)->firstOrFail() : null;
        $existing = Cart::where(['customer_id' => $request->user()->id, 'product_id' => $product->id, 'product_variant_id' => $variant?->id])->first();
        $quantity = $data['quantity'] + ($existing?->quantity ?? 0);
        $this->assertCartable($product, $variant, $quantity);
        $cart = Cart::updateOrCreate(['customer_id' => $request->user()->id, 'product_id' => $product->id, 'product_variant_id' => $variant?->id], ['quantity' => $quantity, 'price' => $variant?->getEffectivePrice() ?? $product->getEffectivePrice(), 'tax' => $product->tax]);

        return $this->success(['id' => $cart->id], 'Produk ditambahkan');
    }

    public function updateCart(Request $request, Cart $cart)
    {
        abort_unless($cart->customer_id === $request->user()->id, 403);
        $data = $request->validate(['quantity' => 'required|integer|min:1']);
        $cart->load('product.shop', 'variant');
        $this->assertCartable($cart->product, $cart->variant, $data['quantity']);
        $cart->update($data);

        return $this->success(null, 'Keranjang diperbarui');
    }

    public function removeCart(Request $request, Cart $cart)
    {
        abort_unless($cart->customer_id === $request->user()->id, 403);
        $cart->delete();

        return $this->success(null, 'Item dihapus');
    }

    public function cancel(Request $request, Order $order, OrderWorkflowService $workflow)
    {
        abort_unless($order->customer_id === $request->user()->id, 403);
        $workflow->cancel($order, $request->user()->id, 'Dibatalkan customer');

        return $this->success(null, 'Pesanan dibatalkan');
    }

    public function review(Request $request)
    {
        $data = $request->validate(['product_id' => 'required|integer|exists:products,id', 'rating' => 'required|integer|min:1|max:5', 'comment' => 'nullable|string|max:2000']);
        $item = OrderItem::where('product_id', $data['product_id'])->where('is_reviewed', false)->whereHas('order', fn ($q) => $q->where('customer_id', $request->user()->id)->where('payment_status', 'paid')->where('order_status', 'delivered'))->lockForUpdate()->firstOrFail();
        $review = ProductReview::create($data + ['customer_id' => $request->user()->id, 'status' => true]);
        $item->update(['is_reviewed' => true]);

        return $this->success(['id' => $review->id], 'Ulasan dikirim', 201);
    }

    private function assertCartable(Product $product, ?ProductVariant $variant, int $quantity): void
    {
        abort_if($product->status !== 'approved' || ! $product->published || $product->shop->status !== 'active' || $product->shop->vacation_mode, 422, 'Produk tidak tersedia.');
        abort_if($quantity < $product->min_qty || ($product->max_qty && $quantity > $product->max_qty), 422, 'Kuantitas tidak valid.');
        abort_if($quantity > ($variant?->stock ?? $product->current_stock), 422, 'Stok tidak mencukupi.');
    }

    private function issueToken(User $user, string $name): string
    {
        return app(PersonalAccessTokenIssuer::class)->issue($user, $name, ['*'])['token'];
    }

    private function success(mixed $data, string $message = 'OK', int $status = 200)
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    private function error(string $message, int $status)
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
