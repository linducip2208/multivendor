<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorWithdrawRequest;
use App\Models\Wallet;
use App\Services\Analytics\DateRange;
use App\Services\Vendor\VendorFinanceService;
use App\Services\Vendor\VendorScope;
use App\Support\Currency;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    public function __construct(private readonly VendorFinanceService $finance) {}

    public function index(Request $request): View
    {
        $vendor = auth('vendor')->user();
        $shop = $vendor->shop;
        $wallet = $vendor->wallet;

        $transactions = $wallet?->transactions()->latest()->paginate(15) ?? collect();
        $withdrawRequests = VendorWithdrawRequest::query()
            ->where('shop_id', (int) $shop?->id)
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('vendor.wallet.index', [
            'wallet' => $wallet,
            'transactions' => $transactions,
            'withdrawRequests' => $withdrawRequests,
            'savedBank' => [
                'bank_name' => (string) ($shop->bank_name ?? ''),
                'bank_account_name' => (string) ($shop->bank_account_name ?? ''),
                'bank_account_number' => VendorScope::maskAccount($shop?->bank_account_number) ?? '',
            ],
            'minimum' => Money::of(10000),
            'currency' => Currency::config(),
        ]);
    }

    public function requestWithdraw(Request $request): RedirectResponse
    {
        $vendor = auth('vendor')->user();
        $shop = $vendor->shop;

        abort_if($shop === null, 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:10000', 'max:1000000000000'],
            'bank_name' => ['required', 'string', 'max:100'],
            'bank_account_number' => ['required', 'string', 'max:50', 'regex:/^[0-9\-\s]{6,50}$/'],
            'bank_account_name' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'bank_account_number.regex' => 'Nomor rekening hanya boleh berisi angka, spasi atau tanda hubung.',
        ]);

        $amount = Money::of($validated['amount']);

        try {
            DB::transaction(function () use ($vendor, $shop, $validated, $amount): void {
                $wallet = Wallet::query()->lockForUpdate()->where('user_id', $vendor->id)->firstOrFail();

                if ($wallet->balance <= 0 || $amount->toFloat() > (float) $wallet->balance) {
                    throw new \DomainException('Saldo tidak cukup.');
                }

                $withdraw = VendorWithdrawRequest::query()->create([
                    'vendor_id' => $vendor->id,
                    'shop_id' => $shop->id,
                    'amount' => $amount->toDecimal(),
                    'bank_name' => $validated['bank_name'],
                    'bank_account_number' => $validated['bank_account_number'],
                    'bank_account_name' => $validated['bank_account_name'],
                    'note' => $validated['note'] ?? null,
                    'status' => 'pending',
                ]);

                $wallet->reserve(
                    $amount->toFloat(),
                    'Penarikan dana #'.$withdraw->getKey(),
                    'withdraw',
                    (int) $withdraw->getKey(),
                    'withdraw:hold:'.$withdraw->getKey(),
                );
            }, 3);
        } catch (\DomainException) {
            return back()->withInput()->with('error', 'Saldo tersedia tidak mencukupi untuk nominal penarikan.');
        }

        return back()->with('success', 'Permintaan pencairan dana dikirim. Menunggu persetujuan admin.');
    }

    public function revenue(Request $request): View
    {
        return view('vendor.finance.revenue', $this->finance->revenue(DateRange::fromRequest($request)));
    }

    public function commission(Request $request): View
    {
        return view('vendor.finance.commission', $this->finance->commission());
    }

    public function payouts(Request $request): View
    {
        return view('vendor.finance.payouts', $this->finance->payouts());
    }
}
