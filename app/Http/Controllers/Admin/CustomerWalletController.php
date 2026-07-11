<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;

class CustomerWalletController extends Controller
{
    public function index()
    {
        $wallets = Wallet::with('user')->whereHas('user', fn($q) => $q->where('role', 'customer'))->paginate(20);
        return view('admin.customers.wallets', compact('wallets'));
    }

    public function show(User $user)
    {
        $wallet = $user->wallet;
        $transactions = $wallet?->transactions()->latest()->paginate(20);
        return view('admin.customers.wallet-detail', compact('user', 'wallet', 'transactions'));
    }

    public function adjust(Request $request, User $user)
    {
        $request->validate([
            'amount' => 'required|numeric|not_in:0',
            'type' => 'required|in:credit,debit',
            'description' => 'nullable|string|max:255',
        ]);

        $wallet = $user->wallet;
        if (!$wallet) {
            $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 0]);
        }

        if ($request->type === 'credit') {
            $wallet->credit(abs($request->amount), $request->description ?? 'Admin adjustment');
        } else {
            if ($wallet->balance < abs($request->amount)) {
                return back()->with('error', 'Saldo tidak mencukupi.');
            }
            $wallet->debit(abs($request->amount), $request->description ?? 'Admin adjustment');
        }

        return back()->with('success', 'Saldo berhasil disesuaikan.');
    }
}
