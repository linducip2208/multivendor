<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyPoint;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    public function index()
    {
        $lp = LoyaltyPoint::where('customer_id', auth()->id())->first();
        $transactions = \App\Models\LoyaltyTransaction::where('customer_id', auth()->id())->latest()->paginate(15);
        return view('storefront.loyalty.index', compact('lp', 'transactions'));
    }

    public function redeem(Request $request)
    {
        $lp = LoyaltyPoint::where('customer_id', auth()->id())->first();
        $request->validate(['points' => 'required|integer|min:100|max:' . ($lp->points ?? 0)]);
        $amount = LoyaltyPoint::redeem(auth()->user(), (int) $request->points);
        return back()->with('success', "{$request->points} poin ditukar menjadi Rp " . number_format($amount, 0, ',', '.') . ' di wallet.');
    }
}
