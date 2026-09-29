<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Api\PersonalAccessTokenIssuer;
use App\Services\OrderWorkflowService;
use Illuminate\Http\Request;

class DeliveryApiController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! auth()->attempt($data) || auth()->user()->role !== 'delivery') {
            return response()->json(['success' => false, 'message' => 'Akun delivery diperlukan.'], 403);
        }

        $token = app(PersonalAccessTokenIssuer::class)->issue(auth()->user(), 'delivery-api', ['read', 'write'])['token'];

        return response()->json(['success' => true, 'message' => 'Login berhasil', 'data' => ['token' => $token]]);
    }

    public function orders(Request $request)
    {
        return response()->json(['success' => true, 'message' => 'OK', 'data' => Order::where('delivery_man_id', $request->user()->id)->with('shop:id,name')->latest()->paginate(15)]);
    }

    public function updateOrder(Request $request, Order $order, OrderWorkflowService $workflow)
    {
        abort_unless($order->delivery_man_id === $request->user()->id, 404);
        $data = $request->validate(['status' => 'required|in:shipped,delivered', 'note' => 'nullable|string|max:1000']);
        if ($data['status'] === 'shipped') {
            $workflow->ship($order, $request->user()->id, null, $data['note'] ?? null);
        } else {
            $workflow->deliver($order, $request->user()->id, $data['note'] ?? null);
        }

        return response()->json(['success' => true, 'message' => 'Status pengiriman diperbarui', 'data' => null]);
    }
}
