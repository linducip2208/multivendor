<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Roles permitted to sign in to the backoffice.
     *
     * `employee` is a real backoffice role with a scoped permission set resolved
     * by {@see \App\Services\Permissions}; `vendor`, `customer` and `delivery`
     * accounts are still refused, which is what the existing login test asserts.
     */
    private const ALLOWED_ROLES = ['admin', 'employee'];

    public function showLoginForm()
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::guard('admin')->attempt($credentials, $request->filled('remember'))) {
            $user = Auth::guard('admin')->user();

            if (! in_array((string) $user->role, self::ALLOWED_ROLES, true)) {
                Auth::guard('admin')->logout();
                return back()->withErrors(['email' => 'Akun ini bukan admin.'])->onlyInput('email');
            }

            if (($user->status ?? 'active') !== 'active') {
                Auth::guard('admin')->logout();
                return back()->withErrors(['email' => 'Akun ini tidak aktif.'])->onlyInput('email');
            }

            $request->session()->regenerate();
            return redirect()->intended(route('admin.dashboard'));
        }

        return back()->withErrors([
            'email' => 'Email atau password salah.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }
}
