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
