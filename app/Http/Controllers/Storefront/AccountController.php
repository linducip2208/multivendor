<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\CustomerAddress;
use App\Models\LoyaltyPoint;
use App\Models\Notification;
use App\Models\Order;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

/**
 * Customer account hub.
 *
 * Everything is scoped to `auth()->id()`; no identifier supplied by the client
 * is ever used to select a row, which removes the classic IDOR surface on
 * account endpoints.
 */
class AccountController extends Controller
{
    public function dashboard()
    {
        $customer = auth()->user();
        $orderQuery = Order::where('customer_id', $customer->id);

        $stats = [
            'total_orders' => (clone $orderQuery)->count(),
            'pending_orders' => (clone $orderQuery)->whereIn('order_status', [
                OrderStatus::Pending->stored(), OrderStatus::PaymentPending->stored(), OrderStatus::Paid->stored(),
            ])->count(),
            'shipped_orders' => (clone $orderQuery)->where('order_status', OrderStatus::Shipped->stored())->count(),
            'total_spent' => (float) (clone $orderQuery)->whereIn('payment_status', [PaymentStatus::Paid->value, PaymentStatus::Partial->value])
                ->sum('total'),
            'wallet_balance' => (float) (Wallet::where('user_id', $customer->id)->value('balance') ?? 0),
            'loyalty_points' => (int) (LoyaltyPoint::where('customer_id', $customer->id)->value('points') ?? 0),
            'wishlist_count' => \App\Models\Wishlist::where('customer_id', $customer->id)->count(),
            'unread_notifications' => $this->unreadCount($customer->id),
            'last_order_at' => (clone $orderQuery)->max('created_at'),
        ];

        $recentOrders = (clone $orderQuery)
            ->with(['shop'])
            ->latest()
            ->limit(5)
            ->get();

        $recommendations = \App\Models\Product::query()
            ->where('status', 'approved')->where('published', true)
            ->with(['shop', 'category', 'brand'])
            ->orderByDesc('featured')
            ->limit(6)
            ->get();

        // Perdalaman analitik pelanggan (read-only, dari riwayat yang ada).
        $loyalty = LoyaltyPoint::firstOrCreate(['customer_id' => $customer->id], ['points' => 0]);
        $analytics = ['rfm' => null, 'funnel' => [], 'cohort' => [], 'tier' => $loyalty->tier(), 'expiring' => $loyalty->expiringSoon()];
        try {
            $crm = app(\App\Services\Crm\Customer360Service::class);
            $analytics['rfm'] = $crm->rfm((int) $customer->id);
            $analytics['funnel'] = $crm->funnel((int) $customer->id);
            $analytics['cohort'] = $crm->cohort((int) $customer->id, 6);
        } catch (\Throwable $e) {
            report($e);
        }

        return view('storefront.account.dashboard', [
            'stats' => $stats, 'recentOrders' => $recentOrders, 'recommendations' => $recommendations,
            'analytics' => $analytics, 'wishlistCollections' => $this->wishlistCollections((int) $customer->id),
        ]);
    }

    /** Koleksi wishlist virtual (folder kategori) + matriks compare spek untuk dasbor akun. */
    private function wishlistCollections(int $customerId): array
    {
        try {
            $rows = \App\Models\Wishlist::where('customer_id', $customerId)
                ->with(['product.category:id,name', 'product.brand:id,name'])->latest('id')->limit(100)->get();
            $groups = [];
            foreach ($rows as $row) {
                $key = (string) ($row->product?->category?->name ?? 'Lainnya');
                $groups[$key]['label'] = $key;
                $groups[$key]['count'] = ($groups[$key]['count'] ?? 0) + 1;
                $groups[$key]['items'][] = ['product_id' => (int) $row->product_id, 'name' => (string) ($row->product?->name ?? 'Produk')];
            }

            return array_values($groups);
        } catch (\Throwable) {
            return [];
        }
    }

    public function addresses()
    {
        $addresses = CustomerAddress::where('customer_id', auth()->id())
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return view('storefront.account.addresses', [
            'addresses' => $addresses,
            'labels' => CustomerAddress::LABELS,
            'grouped' => $addresses->groupBy(fn ($a) => $a->label ?: 'Lainnya'),
            'primary' => $addresses->firstWhere('is_default', true) ?? $addresses->first(),
        ]);
    }

    public function wallet(Request $request)
    {
        $customer = auth()->id();

        $wallet = Wallet::firstOrCreate(['user_id' => $customer], ['balance' => 0, 'pending_balance' => 0]);

        $transactions = WalletTransaction::where('wallet_id', $wallet->id)
            ->latest()
            ->paginate(20);

        // Riwayat terpadu dompet + loyalty + referral ringkas (tanpa kolom baru).
        $loyalty = LoyaltyPoint::firstOrCreate(['customer_id' => $customer], ['points' => 0]);
        $loyaltyTx = \App\Models\LoyaltyTransaction::where('customer_id', $customer)
            ->latest()->limit(20)->get();
        $unified = $transactions->getCollection()
            ->map(fn ($t) => ['kind' => 'wallet', 'at' => $t->created_at, 'label' => (string) ($t->description ?? $t->type), 'amount' => (float) $t->amount, 'dir' => $t->type === 'credit' ? '+' : '-'])
            ->concat($loyaltyTx->map(fn ($t) => ['kind' => 'loyalty', 'at' => $t->created_at, 'label' => (string) ($t->description ?? 'Poin'), 'amount' => (int) $t->points, 'dir' => $t->type === 'earn' ? '+' : '-']))
            ->sortByDesc('at')->values()->take(20);

        return view('storefront.account.wallet', [
            'wallet' => $wallet, 'transactions' => $transactions,
            'loyalty' => $loyalty, 'tier' => $loyalty->tier(), 'expiring' => $loyalty->expiringSoon(),
            'unified' => $unified, 'referral' => $loyalty->referralHistory(10),
            'leaderboard' => LoyaltyPoint::referralLeaderboard(5),
        ]);
    }

    public function notifications()
    {
        $rows = UserNotification::query()
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', auth()->id())
            ->latest()
            ->paginate(20);

        // Backwards compatibility with the legacy notifications table.
        $legacy = Notification::where('notifiable_id', auth()->id())
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('storefront.account.notifications', [
            'notifications' => $rows,
            'legacy' => $legacy,
            'unreadCount' => $this->unreadCount((int) auth()->id()),
            'history' => UserNotification::query()
                ->where('notifiable_type', User::class)->where('notifiable_id', auth()->id())
                ->selectRaw('category, COUNT(*) as total, SUM(read_at IS NULL) as unread')
                ->groupBy('category')->orderByDesc('total')->limit(12)->get(),
        ]);
    }

    public function markRead(int|string $notification)
    {
        // 'all' menandai semua dibaca lewat route existing (aditif, tanpa route baru).
        if (in_array($notification, ['all', 'semua'], true)) {
            UserNotification::query()
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', auth()->id())
                ->whereNull('read_at')->update(['read_at' => now()]);

            return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
        }
        $row = UserNotification::query()
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', auth()->id())
            ->where(function ($query) use ($notification): void {
                $query->where('uuid', $notification);
                if (is_numeric($notification)) {
                    $query->orWhere('id', (int) $notification);
                }
            })
            ->first();

        $row?->markAsRead();

        return back()->with('success', 'Notifikasi ditandai sudah dibaca.');
    }

    public function messages()
    {
        $conversations = \App\Models\Conversation::query()
            ->whereHas('participants', fn ($q) => $q->where('user_id', auth()->id()))
            ->with(['participants', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->limit(30)
            ->get();

        return view('storefront.account.messages', compact('conversations'));
    }

    public function security()
    {
        return view('storefront.account.security', [
            'user' => auth()->user(),
            'socialLogins' => $this->socialLogins(),
        ]);
    }

    public function updateSecurity(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user->update(['password' => Hash::make($validated['password'])]);

        \App\Services\AuditLogger::log($user, 'user.password_changed', [], [], $user->id);

        return back()->with('success', 'Password berhasil diperbarui.')->with('updatePassword', true);
    }

    public function preferences()
    {
        $preferences = \App\Models\NotificationPreference::where('user_id', auth()->id())->get()
            ->keyBy(fn ($p) => $p->category.'|'.$p->channel);

        // Matriks granular kategori × kanal untuk UI (tanpa kolom baru).
        $categories = ['order' => 'Pesanan', 'promo' => 'Promo', 'stock' => 'Stok & harga', 'loyalty' => 'Loyalitas & dompet', 'support' => 'Bantuan', 'system' => 'Sistem'];
        $channels = ['email' => 'Email', 'push' => 'Push', 'whatsapp' => 'WhatsApp', 'sms' => 'SMS'];

        return view('storefront.account.preferences', compact('preferences', 'categories', 'channels'));
    }

    public function updatePreferences(Request $request)
    {
        $validated = $request->validate([
            'preferences' => ['nullable', 'array'],
            'preferences.*.category' => ['required', 'string', 'max:40'],
            'preferences.*.channel' => ['required', 'string', 'max:20'],
            'preferences.*.enabled' => ['nullable'],
        ]);

        // Dukung dua bentuk form: preferences[field]=1 (view) dan preferences[][category…] (API lama).
        $rows = [];
        foreach ($validated['preferences'] ?? [] as $key => $row) {
            if (is_array($row) && isset($row['category'])) {
                $rows[] = $row;
            } elseif (str_contains((string) $key, ':') || str_contains((string) $key, '|')) {
                [$category, $channel] = preg_split('/[:|]/', (string) $key, 2);
                $rows[] = ['category' => $category, 'channel' => $channel, 'enabled' => $row];
            }
        }
        foreach ($rows as $row) {
            \App\Models\NotificationPreference::updateOrCreate(
                ['user_id' => auth()->id(), 'category' => $row['category'], 'channel' => $row['channel']],
                ['enabled' => (bool) ($row['enabled'] ?? false)],
            );
        }

        return back()->with('success', 'Preferensi notifikasi disimpan.');
    }

    public function reviews()
    {
        $reviews = \App\Models\ProductReview::where('customer_id', auth()->id())
            ->with('product.shop')
            ->latest()
            ->paginate(15);
        // Foto + helpful votes untuk tampilan akun (engine milik tim katalog tak disentuh).
        $reviews->getCollection()->transform(function ($review) {
            $review->setAttribute('photo_urls', $review->photos());
            $review->setAttribute('helpful_count', $review->helpfulVotes());

            return $review;
        });

        return view('storefront.account.reviews', compact('reviews'));
    }

    /** Data misi harian + streak untuk panel akun (aditif, tanpa mengubah method existing). */
    public function misiHarianData(): array
    {
        try {
            $user = auth()->user();
            if (! $user) {
                return ['missions' => [], 'streak' => 0, 'points' => 0];
            }
            $misi = app(\App\Services\Loyalitas\MisiHarian::class);

            return [
                'missions' => $misi->statusFor($user),
                'streak' => $misi->streak($user),
                'points' => (int) (LoyaltyPoint::where('customer_id', $user->id)->value('points') ?? 0),
            ];
        } catch (\Throwable) {
            return ['missions' => [], 'streak' => 0, 'points' => 0];
        }
    }

    /** Koleksi wishlist lanjutan: folder per kategori + tautan berbagi (virtual, tanpa kolom baru). */
    public function koleksiBerbagiData(): array
    {
        try {
            $customerId = (int) auth()->id();
            if ($customerId === 0) {
                return [];
            }
            $rows = \App\Models\Wishlist::where('customer_id', $customerId)
                ->with(['product:id,name,slug,price,special_price,thumbnail'])
                ->latest('id')->limit(120)->get();

            $folders = [];
            foreach ($rows as $row) {
                if (! $row->product) {
                    continue;
                }
                $key = (string) ($row->product->category?->name ?? 'Lainnya');
                $slug = (string) (\Illuminate\Support\Str::slug($key) ?: 'lainnya');
                if (! isset($folders[$slug])) {
                    try {
                        $share = route('wishlist.index', ['koleksi' => $slug]);
                    } catch (\Throwable) {
                        $share = url('/wishlist?koleksi='.$slug);
                    }
                    $folders[$slug] = ['slug' => $slug, 'label' => $key, 'count' => 0, 'items' => [], 'share_url' => $share];
                }
                $folders[$slug]['count']++;
                if (count($folders[$slug]['items']) < 6) {
                    $folders[$slug]['items'][] = [
                        'product_id' => (int) $row->product_id,
                        'name' => (string) $row->product->name,
                        'url' => (string) $row->product->storefront_url,
                        'price' => (float) $row->product->getEffectivePrice(),
                    ];
                }
            }

            return array_values($folders);
        } catch (\Throwable) {
            return [];
        }
    }

    /** Dasbor afiliasi pelanggan: tautan + klik + komisi + leaderboard (read-only). */
    public function dasborAfiliasiData(): array
    {
        try {
            $user = auth()->user();
            if (! $user) {
                return ['affiliate' => null, 'leaderboard' => []];
            }
            $affiliate = \App\Models\Affiliate::where('user_id', $user->id)->first();
            if (! $affiliate && ! empty($user->referral_code)) {
                $affiliate = \App\Models\Affiliate::findByCode((string) $user->referral_code);
            }
            if (! $affiliate) {
                return ['affiliate' => null, 'leaderboard' => \App\Models\Affiliate::leaderboard(5)];
            }

            return [
                'affiliate' => [
                    'code' => (string) $affiliate->code,
                    'link' => $affiliate->referralLink(),
                    'status' => (string) $affiliate->status,
                    'commission_rate' => (float) $affiliate->commission_rate,
                    'clicks' => (int) $affiliate->clicks()->count(),
                    'clicks_30d' => (int) $affiliate->clicks()->where('created_at', '>=', now()->subDays(30))->count(),
                    'conversions' => (int) $affiliate->clicks()->whereNotNull('converted_order_id')->count(),
                    'total_orders' => (int) $affiliate->total_orders,
                    'total_revenue' => (float) $affiliate->total_revenue,
                    'total_commission' => (float) $affiliate->total_commission,
                ],
                'leaderboard' => \App\Models\Affiliate::leaderboard(5),
            ];
        } catch (\Throwable) {
            return ['affiliate' => null, 'leaderboard' => []];
        }
    }

    /** Riwayat cashback saya: dompet (reference_key cashback-*) + poin earn cashback (read-only). */
    public function dataCashbackSaya(): array
    {
        try {
            $user = auth()->user();
            if (! $user) {
                return ['riwayat' => [], 'total_dompet' => 0.0, 'total_poin' => 0];
            }
            $customerId = (int) $user->id;
            $riwayat = [];

            try {
                $dompetId = \App\Models\Wallet::query()->where('user_id', $customerId)->value('id');
                if ($dompetId) {
                    foreach (\App\Models\WalletTransaction::query()->where('wallet_id', $dompetId)->where('reference_type', 'cashback')->latest('id')->limit(10)->get() as $tx) {
                        $riwayat[] = ['jenis' => 'dompet', 'nominal' => (float) $tx->amount, 'label' => (string) ($tx->description ?? 'Cashback'), 'at' => (string) ($tx->created_at?->format('d M Y H:i') ?? '')];
                    }
                }
            } catch (\Throwable) {
            }

            $totalPoin = 0;
            try {
                foreach (\App\Models\LoyaltyTransaction::query()->where('customer_id', $customerId)->where('type', 'earn')->where('reference_type', 'cashback')->latest('id')->limit(10)->get() as $tx) {
                    $totalPoin += (int) $tx->points;
                    $riwayat[] = ['jenis' => 'poin', 'nominal' => (int) $tx->points, 'label' => (string) ($tx->description ?? 'Cashback poin'), 'at' => (string) ($tx->created_at?->format('d M Y H:i') ?? '')];
                }
            } catch (\Throwable) {
            }

            $totalDompet = 0.0;
            foreach ($riwayat as $row) {
                if (($row['jenis'] ?? '') === 'dompet') {
                    $totalDompet += (float) $row['nominal'];
                }
            }

            return ['riwayat' => array_slice($riwayat, 0, 10), 'total_dompet' => $totalDompet, 'total_poin' => $totalPoin];
        } catch (\Throwable) {
            return ['riwayat' => [], 'total_dompet' => 0.0, 'total_poin' => 0];
        }
    }

    /**
     * Data bagikan referral: tautan + pesan + poster SVG + tombol salin.
     * QR tidak tersedia (tak ada paket QR/barcode terinstal) — pakai tautan
     * + tombol salin + poster unduhan.
     */
    public function dataBagikanReferral(): array
    {
        try {
            $user = auth()->user();
            if (! $user) {
                return ['tautan' => null, 'kode' => null, 'pesan' => null, 'poster_svg' => null, 'qr_tersedia' => false];
            }
            $affiliate = \App\Models\Affiliate::where('user_id', (int) $user->id)->first();
            if (! $affiliate && ! empty($user->referral_code)) {
                $affiliate = \App\Models\Affiliate::findByCode((string) $user->referral_code);
            }

            if (! $affiliate) {
                $kode = (string) ($user->referral_code ?? '');
                if ($kode === '') {
                    return ['tautan' => null, 'kode' => null, 'pesan' => null, 'poster_svg' => null, 'qr_tersedia' => false];
                }
                try {
                    $dasar = route('products.index');
                } catch (\Throwable) {
                    $dasar = url('/products');
                }
                $tautan = rtrim($dasar, '?&').(str_contains($dasar, '?') ? '&' : '?').'ref='.urlencode($kode);

                return ['tautan' => $tautan, 'kode' => $kode, 'pesan' => 'Belanja lewat tautanku '.$tautan.' — pakai kode referral '.$kode.'!', 'poster_svg' => null, 'qr_tersedia' => false];
            }

            return [
                'tautan' => $affiliate->tautanBagikan(),
                'kode' => (string) $affiliate->code,
                'pesan' => $affiliate->pesanBagikan(),
                'poster_svg' => $affiliate->posterSvg(),
                'qr_tersedia' => $affiliate->qrTersedia(),
            ];
        } catch (\Throwable) {
            return ['tautan' => null, 'kode' => null, 'pesan' => null, 'poster_svg' => null, 'qr_tersedia' => false];
        }
    }

    private function unreadCount(int $userId): int
    {
        try {
            return UserNotification::where('notifiable_type', User::class)
                ->where('notifiable_id', $userId)
                ->whereNull('read_at')
                ->count();
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    /** @return list<array{provider: string, name: ?string}> */
    private function socialLogins(): array
    {
        try {
            return DB::table('social_logins')->where('user_id', auth()->id())->get()
                ->map(fn ($row) => ['provider' => (string) $row->provider, 'name' => $row->name ?? null])
                ->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }
}
