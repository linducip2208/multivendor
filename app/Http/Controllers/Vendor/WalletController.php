<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorWithdrawRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    public function index()
    {
        $vendor = auth('vendor')->user();
        $shop = $vendor->shop;
        $wallet = $vendor->wallet;
        $transactions = $wallet?->transactions()->latest()->paginate(15) ?? collect();
        $withdrawRequests = VendorWithdrawRequest::where('vendor_id', $vendor->id)->latest()->paginate(10);

        $savedBank = [
            'bank_name' => $shop->bank_name ?? '',
            'bank_account_number' => $shop->bank_account_number ?? '',
            'bank_account_name' => $shop->bank_account_name ?? '',
        ];

        return view('vendor.wallet.index', compact('wallet', 'transactions', 'withdrawRequests', 'savedBank'));
    }

    public function requestWithdraw(Request $request)
    {
        $vendor = auth('vendor')->user();
        $shop = $vendor->shop;
        $wallet = $vendor->wallet;

        $request->validate([
            'amount' => 'required|numeric|min:10000',
            'bank_name' => 'required|string|max:100',
            'bank_account_number' => 'required|string|max:50',
            'bank_account_name' => 'required|string|max:255',
            'note' => 'nullable|string',
        ]);

        try {
            DB::transaction(function () use ($vendor, $shop, $request) {
                $wallet = \App\Models\Wallet::lockForUpdate()->where('user_id', $vendor->id)->firstOrFail();
                $withdraw = VendorWithdrawRequest::create([
                    'vendor_id' => $vendor->id, 'shop_id' => $shop->id, 'amount' => $request->amount,
                    'bank_name' => $request->bank_name, 'bank_account_number' => $request->bank_account_number,
                    'bank_account_name' => $request->bank_account_name, 'note' => $request->note, 'status' => 'pending',
                ]);
                $wallet->reserve((float) $request->amount, 'Withdraw reserved #'.$withdraw->id, 'withdraw', $withdraw->id, 'withdraw:hold:'.$withdraw->id);
            });
        } catch (\DomainException) {
            return back()->withInput()->with('error', 'Saldo tersedia tidak mencukupi untuk nominal penarikan.');
        }

        return back()->with('success', 'Permintaan pencairan dana dikirim. Menunggu approval admin.');
    }
}
