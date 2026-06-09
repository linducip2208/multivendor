<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function redirect($provider)
    {
        return Socialite::driver($provider)->redirect();
    }

    public function callback($provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', 'Gagal login dengan ' . $provider);
        }

        $user = User::where('email', $socialUser->getEmail())->first();

        if (!$user) {
            $user = User::create([
                'name' => $socialUser->getName() ?? $socialUser->getNickname(),
                'email' => $socialUser->getEmail(),
                'password' => Hash::make(Str::random(32)),
                'role' => 'customer',
                'status' => 'active',
                'avatar' => $socialUser->getAvatar(),
            ]);
            \App\Models\Wallet::create(['user_id' => $user->id, 'balance' => 0]);
        }

        auth()->login($user);
        return redirect('/')->with('success', 'Login berhasil dengan ' . ucfirst($provider));
    }
}
