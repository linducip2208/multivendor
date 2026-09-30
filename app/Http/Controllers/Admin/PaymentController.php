<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use App\Models\VendorWithdrawRequest;
use App\Plugins\PluginManager;
use App\Services\AuditLogger;
use App\Services\Backoffice\FinanceAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(private readonly FinanceAdminService $finance) {}

    public function index(Request $request): View
    {
        return view('admin.settlements.index', $this->finance->settlements(
            (int) $request->query('page', 1),
            trim((string) $request->query('search', '')),
            (string) $request->query('status', ''),
        ));
    }

    public function show(VendorWithdrawRequest $settlement): View
    {
        return view('admin.settlements.show', $this->finance->settlementDetail($settlement));
    }

    public function ledger(Request $request): View
    {
        return view('admin.ledger.index', $this->finance->ledgerReport(
            (int) $request->query('page', 1),
            (string) $request->query('account', ''),
            (string) $request->query('entry_type', ''),
            trim((string) $request->query('search', '')),
        ));
    }

    public function ledgerAccounts(): View
    {
        return view('admin.ledger.accounts', $this->finance->ledgerReport(
            1,
            (string) request()->query('account', ''),
            (string) request()->query('entry_type', ''),
            '',
            25,
        ));
    }

    public function refunds(Request $request): View
    {
        return view('admin.refunds.index', $this->finance->refunds(
            (int) $request->query('page', 1),
            (string) $request->query('status', ''),
            trim((string) $request->query('search', '')),
        ));
    }

    public function refundShow(Request $request, Refund $refund): View
    {
        return view('admin.refunds.show', $this->finance->refundDetail($refund));
    }

    public function refundOrder(Request $request, int $order): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:1000000000'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $result = $this->finance->refundOrder(
            $order,
            isset($validated['amount']) && $validated['amount'] !== null ? (float) $validated['amount'] : null,
            (string) $validated['reason'],
            auth('admin')->id(),
        );

        if (! $result['ok']) {
            return back()->with('error', $result['detail'])->withInput();
        }

        return redirect()->route('admin.refunds.index')->with('success', $result['detail']);
    }

    public function reconciliation(Request $request): View
    {
        return view('admin.payments.reconciliation', $this->finance->reconciliation(
            (int) $request->query('minutes', FinanceAdminService::RECONCILE_STALE_MINUTES),
        ));
    }

    public function runReconciliation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'minutes' => ['required', 'integer', 'min:1', 'max:1440'],
        ]);

        $result = $this->finance->runReconciliation((int) $validated['minutes'], auth('admin')->id());

        return back()->with(
            $result['groups'] > 0 ? 'success' : 'error',
            $result['groups'] > 0
                ? $result['groups'].' kelompok pembayaran diperiksa. '.$result['detail']
                : $result['detail'],
        );
    }

    /**
     * Daftar semua provider (ID + intl) via manifest plugin — data untuk
     * view admin/payments/providers. Tanpa kredensial live.
     */
    public function providers(): View
    {
        $manager = new PluginManager();
        $providers = $manager->providersOverview();

        return view('admin.payments.providers', [
            'providers' => $providers,
            'log' => $manager->paymentLog(10),
        ]);
    }

    /**
     * Health jujur per provider — aman, tanpa kredensial live, tanpa HTTP.
     * Status "unknown" bila belum dikonfigurasi.
     */
    public function providerHealth(string $code): JsonResponse
    {
        return response()->json((new PluginManager())->providerHealth($code));
    }
}
