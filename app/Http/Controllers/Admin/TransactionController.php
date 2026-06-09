<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::with(['customer', 'shop', 'order'])->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->where('transaction_id', 'like', "%{$request->search}%");
        }
        $transactions = $query->paginate(15);
        return view('admin.transactions.index', compact('transactions'));
    }
}
