<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\DeliveryCashCollect;
use App\Services\Vendor\VendorCashCollectService;
use App\Support\Currency;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Vendor view of cash-on-delivery custody.
 *
 * A COD collect is a record that a courier is holding the customer's cash, not
 * a settlement event: the vendor's share is credited by the order workflow when
 * the order is delivered. This controller therefore only flips the custody flag
 * and posts to a vendor-guarded route, never to the delivery-guarded courier
 * endpoint that rejected the vendor with a 403.
 */
class CashCollectController extends Controller
{
    public function __construct(private readonly VendorCashCollectService $cod) {}

    public function index(Request $request): View
    {
        $data = $this->cod->index(VendorScopeRequest::enum($request, 'status', ['pending', 'collected']));

        return view('vendor.cash-collect.index', [
            'collects' => $data['collects'],
            'totalPending' => $data['pending'],
            'totalCollected' => $data['collected'],
            'total' => $data['total'],
            'status' => VendorScopeRequest::enum($request, 'status', ['pending', 'collected']),
            'currency' => Currency::config(),
        ]);
    }

    public function markCollected(Request $request, DeliveryCashCollect $collect): RedirectResponse
    {
        $this->cod->markCollected($collect);

        return back()->with(
            'success',
            'Serah terima COD untuk pesanan #'.$collect->order?->order_number.' dicatat. Saldo toko mengikuti settlement pesanan.'
        );
    }
}
