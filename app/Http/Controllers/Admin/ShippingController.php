<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderReturn;
use App\Models\OrderShipment;
use App\Models\Provider;
use App\Services\Backoffice\FulfillmentService;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShippingController extends Controller
{
    public function __construct(private readonly FulfillmentService $fulfillment) {}

    public function index(Request $request): View
    {
        return $this->shipments($request);
    }

    public function shipments(Request $request): View
    {
        return view('admin.shipments.index', $this->fulfillment->shipments(
            (int) $request->query('page', 1),
            (string) $request->query('status', ''),
            trim((string) $request->query('search', '')),
        ));
    }

    public function show(OrderShipment $shipment): View
    {
        return view('admin.shipments.show', $this->fulfillment->shipmentDetail($shipment));
    }

    public function track(Request $request, OrderShipment $shipment): RedirectResponse
    {
        $result = $this->fulfillment->track($shipment);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function couriers(): View
    {
        return view('admin.shipping.couriers', $this->fulfillment->couriers());
    }

    public function courierShow(Provider $provider): View
    {
        return view('admin.shipping.courier-show', $this->fulfillment->courierDetail($provider));
    }

    public function returns(Request $request): View
    {
        return view('admin.returns.index', $this->fulfillment->returns(
            (int) $request->query('page', 1),
            (string) $request->query('status', ''),
            trim((string) $request->query('search', '')),
        ));
    }

    public function decideReturn(Request $request, OrderReturn $return): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validated['decision'] === 'rejected' && trim((string) ($validated['admin_note'] ?? '')) === '') {
            return back()->with('error', 'Penolakan retur wajib disertai alasan yang dapat dibaca pelanggan.')->withInput();
        }

        $updated = $this->fulfillment->decideReturn(
            $return,
            (string) $validated['decision'],
            $validated['admin_note'] ?? null,
            (float) ($validated['amount'] ?? 0),
            auth('admin')->id(),
        );

        return back()->with(
            'success',
            'Retur '.$updated->rma_number.' '.$updated->status.' pada '.now()->format('Y-m-d H:i').'.',
        );
    }
}
