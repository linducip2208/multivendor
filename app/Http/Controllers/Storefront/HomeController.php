<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Catalog\HomePageService;
use App\Support\Currency;
use App\Support\Feature;
use App\Enums\PlatformFeature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Storefront home page + customer authentication.
 *
 * The homepage is composed entirely from `homepage_sections`; the view renders
 * whichever sections the administrator enabled, in the configured order.
 */
class HomeController extends Controller
{
    public function __construct(private readonly HomePageService $home) {}

    public function index(Request $request)
    {
        $sections = $this->home->sections();
        $customerId = Auth::id();

        $payload = [];
        foreach ($sections as $section) {
            $payload[$section['code']] = $this->home->data($section['code'], $section['settings'] ?? [], $customerId);
        }

        $metaTitle = \App\Models\SystemSetting::get('seo_home_title');
        $metaDescription = \App\Models\SystemSetting::get('seo_home_description')
            ?: \App\Models\SystemSetting::get('storefront_tagline');

        return view('storefront.home', [
            'sections' => $sections,
            'data' => $payload,
            'metaTitle' => $metaTitle,
            'metaDescription' => $metaDescription,
            'canonicalUrl' => route('home'),
            'jsonLd' => $this->organizationSchema(),
        ]);
    }

    public function loginForm()
    {
        if (Auth::check()) {
            return redirect('/');
        }

        return view('storefront.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Email atau password salah.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        // A session-fixation-safe redirect that also respects role landing pages.
        return redirect()->intended($this->landingFor($request->user()));
    }

    public function registerForm()
    {
        if (Auth::check()) {
            return redirect('/');
        }

        return view('storefront.auth.register');
    }

    public function register(Request $request)
    {
        if (! Feature::enabled(PlatformFeature::Storefront) || ! \App\Models\SystemSetting::get('customer_registration_open', true)) {
            return back()->with('error', 'Pendaftaran pelanggan sedang ditutup.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'referral_code' => ['nullable', 'string', 'exists:users,referral_code'],
            'terms' => ['accepted'],
        ], [], [
            'terms' => 'ketentuan layanan',
        ]);

        $referrer = null;
        if (! empty($validated['referral_code'])) {
            $referrer = User::where('referral_code', $validated['referral_code'])->first();
        }

        $user = DB::transaction(function () use ($validated, $referrer): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($validated['password']),
                'role' => 'customer',
                'status' => 'active',
                'referral_code' => strtoupper(Str::random(8)),
                'referred_by' => $referrer?->id,
                'email_verified_at' => null,
            ]);

            Wallet::firstOrCreate(['user_id' => $user->id], ['balance' => 0, 'pending_balance' => 0]);
            \App\Models\LoyaltyPoint::firstOrCreate(['customer_id' => $user->id], ['points' => 0]);

            return $user;
        });

        if ($referrer) {
            $points = (int) \App\Models\SystemSetting::get('referral_bonus_points', 500);
            if ($points > 0) {
                \App\Models\LoyaltyPoint::earn($referrer, $points, 'Bonus referral: '.$user->name, 'referral', $user->id);
            }
        }

        \App\Services\ActivityLogger::log($user, 'customer.registered', ['referrer_id' => $referrer?->id]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect($this->landingFor($user))
            ->with('success', 'Akun berhasil dibuat. Selamat berbelanja!');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function landingFor(?User $user): string
    {
        return match (true) {
            $user?->isSuperAdmin() || $user?->isAdmin() => route('admin.dashboard'),
            $user?->isVendor() => route('vendor.dashboard'),
            $user?->isDelivery() => route('delivery.index'),
            default => '/',
        };
    }

    /** @return array<string, mixed> */
    private function organizationSchema(): array
    {
        $siteName = \App\Models\SystemSetting::get('app_name', config('app.name'));

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $siteName,
            'url' => url('/'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => url('/search').'?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ];

        $org = [
            '@type' => 'Organization',
            'name' => $siteName,
            'url' => url('/'),
        ];

        if ($logo = \App\Models\SystemSetting::get('logo_url')) {
            $org['logo'] = str_starts_with($logo, 'http') ? $logo : url($logo);
        }
        if ($email = \App\Models\SystemSetting::get('contact_email')) {
            $org['contactPoint'] = ['@type' => 'ContactPoint', 'contactType' => 'customer support', 'email' => $email];
        }

        return $schema + ['publisher' => $org];
    }
}
