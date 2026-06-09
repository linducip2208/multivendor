<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class DeliveryManController extends Controller
{
    public function index(Request $request)
    {
        $query = User::where('role', 'delivery')->latest();
        if ($request->filled('search')) $query->where('name', 'like', "%{$request->search}%");
        $deliveryMen = $query->paginate(15);
        return view('admin.delivery-men.index', compact('deliveryMen'));
    }

    public function create() { return view('admin.delivery-men.create'); }

    public function store(Request $request)
    {
        $validated = $request->validate(['name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email', 'phone' => 'required|string|max:20', 'password' => 'required|min:6']);
        $user = User::create(['name' => $validated['name'], 'email' => $validated['email'], 'phone' => $validated['phone'], 'password' => Hash::make($validated['password']), 'role' => 'delivery', 'status' => 'active']);
        Wallet::create(['user_id' => $user->id, 'balance' => 0]);
        return redirect()->route('admin.delivery-men.index')->with('success', 'Kurir ditambahkan.');
    }

    public function edit(User $delivery_man) { return view('admin.delivery-men.edit', ['deliveryMan' => $delivery_man]); }

    public function update(Request $request, User $delivery_man)
    {
        $validated = $request->validate(['name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email,'.$delivery_man->id, 'phone' => 'required|string|max:20', 'password' => 'nullable|min:6', 'status' => 'required|in:active,inactive,suspended']);
        $delivery_man->update(['name' => $validated['name'], 'email' => $validated['email'], 'phone' => $validated['phone'], 'status' => $validated['status']]);
        if ($validated['password']) $delivery_man->update(['password' => Hash::make($validated['password'])]);
        return redirect()->route('admin.delivery-men.index')->with('success', 'Kurir diperbarui.');
    }

    public function destroy(User $delivery_man) { $delivery_man->delete(); return back()->with('success', 'Kurir dihapus.'); }

    public function wallet(User $delivery_man)
    {
        $wallet = $delivery_man->wallet;
        $transactions = $wallet?->transactions()->latest()->paginate(15) ?? collect();
        return view('admin.delivery-men.wallet', compact('delivery_man', 'wallet', 'transactions'));
    }
}
