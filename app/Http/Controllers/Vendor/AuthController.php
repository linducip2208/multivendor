<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(private readonly VendorRegistrationController $registration) {}

    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::guard('vendor')->check()) {
            return redirect()->route('vendor.dashboard');
        }

        return view('vendor.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('vendor')->attempt($credentials, $request->filled('remember'))) {
            return back()->withErrors(['email' => 'Email atau kata sandi salah.'])->onlyInput('email');
        }

        $user = Auth::guard('vendor')->user();

        if (! $user->isVendor()) {
            Auth::guard('vendor')->logout();

            return back()->withErrors(['email' => 'Akun ini bukan akun penjual.']);
        }

        if ($user->shop === null) {
            Auth::guard('vendor')->logout();

            return back()->withErrors(['email' => 'Toko belum terhubung dengan akun ini.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('vendor.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('vendor')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('vendor.login');
    }

    public function showRegisterForm(): View
    {
        return $this->registration->create();
    }

    public function register(Request $request): RedirectResponse
    {
        return $this->registration->store($request);
    }
}
