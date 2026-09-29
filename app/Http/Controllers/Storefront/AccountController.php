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

        return view('storefront.account.dashboard', compact('stats', 'recentOrders', 'recommendations'));
    }

    public function addresses()
    {
        $addresses = CustomerAddress::where('customer_id', auth()->id())
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return view('storefront.account.addresses', compact('addresses'));
    }

    public function wallet(Request $request)
    {
        $customer = auth()->id();

        $wallet = Wallet::firstOrCreate(['user_id' => $customer], ['balance' => 0, 'pending_balance' => 0]);

        $transactions = WalletTransaction::where('wallet_id', $wallet->id)
            ->latest()
            ->paginate(20);

        return view('storefront.account.wallet', compact('wallet', 'transactions'));
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
        ]);
    }

    public function markRead(int|string $notification)
    {
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

        return view('storefront.account.preferences', compact('preferences'));
    }

    public function updatePreferences(Request $request)
    {
        $validated = $request->validate([
            'preferences' => ['nullable', 'array'],
            'preferences.*.category' => ['required', 'string', 'max:40'],
            'preferences.*.channel' => ['required', 'string', 'max:20'],
            'preferences.*.enabled' => ['nullable'],
        ]);

        foreach ($validated['preferences'] ?? [] as $row) {
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
