<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function inbox()
    {
        $shop = auth('vendor')->user()->shop;
        $customers = User::where('role', 'customer')
            ->whereHas('orders', fn($q) => $q->where('shop_id', $shop->id))
            ->get();
        return view('vendor.chat.inbox', compact('customers'));
    }

    public function messages(User $customer)
    {
        $shop = auth('vendor')->user()->shop;
        $hasOrder = $customer->orders()->where('shop_id', $shop->id)->exists();
        if (!$hasOrder) abort(403);
        return view('vendor.chat.messages', compact('customer'));
    }

    public function send(Request $request)
    {
        $request->validate(['user_id' => 'required|exists:users,id', 'message' => 'required|string']);
        return response()->json(['success' => true]);
    }
}
