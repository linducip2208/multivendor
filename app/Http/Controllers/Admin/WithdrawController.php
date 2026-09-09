<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VendorWithdrawRequest;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WithdrawController extends Controller
{
    protected NotificationService $notifications;

    public function __construct()
    {
        $this->notifications = new NotificationService;
    }

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
        try {
            $withdraw = DB::transaction(function () use ($request, $withdraw) {
                $withdraw = VendorWithdrawRequest::lockForUpdate()->findOrFail($withdraw->id);
                $allowed = ['pending' => ['approved', 'rejected'], 'approved' => ['completed', 'rejected']];
                if (!in_array($request->status, $allowed[$withdraw->status] ?? [], true)) abort(422, 'Status withdraw tidak dapat diubah.');
                $withdraw->update(['status' => $request->status, 'approved_by' => auth('admin')->id(),
                    'approved_at' => $request->status === 'approved' ? now() : $withdraw->approved_at,
                    'completed_at' => $request->status === 'completed' ? now() : null,
                    'rejection_reason' => $request->status === 'rejected' ? $request->note : null]);
                $wallet = \App\Models\Wallet::lockForUpdate()->where('user_id', $withdraw->vendor_id)->first();
                if ($request->status === 'rejected' && $wallet) $wallet->release((float) $withdraw->amount, 'Withdraw rejected #'.$withdraw->id, 'withdraw', $withdraw->id, 'withdraw:release:'.$withdraw->id);
                if ($request->status === 'completed' && $wallet) {
                    $wallet->update(['pending_balance' => max(0, (float) $wallet->pending_balance - (float) $withdraw->amount)]);
                    $wallet->transactions()->create(['amount' => $withdraw->amount, 'type' => 'debit', 'operation' => 'withdraw', 'description' => 'Withdraw completed #'.$withdraw->id,
                        'reference_type' => 'withdraw', 'reference_id' => $withdraw->id, 'reference_key' => 'withdraw:complete:'.$withdraw->id,
                        'balance_before' => $wallet->balance, 'balance_after' => $wallet->balance, 'status' => 'completed']);
                }
                return $withdraw;
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Withdraw tidak dapat diperbarui.');
        }

        if ($request->status === 'completed') {
            $this->notifications->sendWithdrawCompleted($withdraw);
        }

        if ($request->status === 'approved') {
            $this->notifications->sendWithdrawApproved($withdraw);
        }

        if ($request->status === 'rejected') {
            $this->notifications->sendWithdrawRejected($withdraw);
        }

        $labels = ['approved' => 'disetujui', 'rejected' => 'ditolak', 'completed' => 'selesai'];
        return back()->with('success', 'Withdraw ' . ($labels[$request->status] ?? $request->status));
    }
}
