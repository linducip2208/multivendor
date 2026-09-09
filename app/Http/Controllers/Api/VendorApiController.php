<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\OrderWorkflowService;
use Illuminate\Http\Request;

class VendorApiController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! auth()->guard('vendor')->attempt($data)) {
            return $this->error('Email atau password salah.', 401);
        }
        $vendor = auth('vendor')->user();
        if (! $vendor->isVendor() || ! $vendor->shop) {
            return $this->error('Akun vendor dengan toko diperlukan.', 403);
        }

        return $this->success(['token' => $vendor->createToken('vendor-api')->plainTextToken, 'shop' => ['id' => $vendor->shop->id, 'name' => $vendor->shop->name, 'slug' => $vendor->shop->slug]], 'Login berhasil');
    }

    public function dashboard(Request $request)
    {
        $shopId = $request->user()->shop->id;

        return $this->success(['products_count' => Product::where('shop_id', $shopId)->count(), 'orders_count' => Order::where('shop_id', $shopId)->count(), 'revenue' => (float) Transaction::where('shop_id', $shopId)->where('status', 'success')->sum('vendor_amount')]);
    }

    public function products(Request $request)
    {
        return $this->success(Product::where('shop_id', $request->user()->shop->id)->latest()->paginate(20));
    }

    public function orders(Request $request)
    {
        return $this->success(Order::where('shop_id', $request->user()->shop->id)->with('customer:id,name')->latest()->paginate(15));
    }

    public function updateOrder(Request $request, Order $order, OrderWorkflowService $workflow)
    {
        abort_unless($order->shop_id === $request->user()->shop->id, 403);
        $data = $request->validate(['status' => 'required|in:confirmed,processing,shipped,canceled', 'note' => 'nullable|string|max:1000', 'tracking_id' => 'nullable|string|max:100']);
        match ($data['status']) {
            'confirmed' => $workflow->confirm($order, $request->user()->id, $data['note'] ?? null),
            'processing' => $workflow->process($order, $request->user()->id, $data['note'] ?? null),
            'shipped' => $workflow->ship($order, $request->user()->id, $data['tracking_id'] ?? null, $data['note'] ?? null),
            'canceled' => $workflow->cancel($order, $request->user()->id, $data['note'] ?? null),
        };

        return $this->success(null, 'Status pesanan diperbarui');
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
