<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VendorWithdrawRequest;
use Illuminate\Http\Request;

class WithdrawController extends Controller
{
    public function index(Request $request)
    {
        $query = VendorWithdrawRequest::with(['vendor', 'shop'])->latest();
        if ($request->filled('status')) $query->where('status', $request->status);
        $withdraws = $query->paginate(15);
        return view('admin.withdraws.index', compact('withdraws'));
    }

    public function update(Request $request, VendorWithdrawRequest $withdraw)
    {
        $request->validate(['status' => 'required|in:approved,rejected,completed', 'note' => 'nullable|string']);
        $withdraw->update([
            'status' => $request->status,
            'approved_by' => auth('admin')->id(),
            'approved_at' => $request->status === 'approved' ? now() : null,
            'completed_at' => $request->status === 'completed' ? now() : null,
            'rejection_reason' => $request->status === 'rejected' ? $request->note : null,
        ]);

        if ($request->status === 'completed') {
            $wallet = $withdraw->vendor->wallet;
            if ($wallet) {
                $wallet->debit($withdraw->amount, 'Withdraw completed #' . $withdraw->id);
            }
        }

        $labels = ['approved' => 'disetujui', 'rejected' => 'ditolak', 'completed' => 'selesai'];
        return back()->with('success', 'Withdraw ' . ($labels[$request->status] ?? $request->status));
    }
}
