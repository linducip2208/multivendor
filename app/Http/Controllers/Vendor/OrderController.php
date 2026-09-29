<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Services\OrderWorkflowService;
use App\Services\Vendor\VendorFulfillmentService;
use App\Support\Currency;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    private const TRANSITIONS = ['confirmed', 'processing', 'canceled'];

    public function __construct(private readonly VendorFulfillmentService $fulfillment) {}

    public function index(Request $request): View
    {
        $shop = auth('vendor')->user()->shop;

        if ($shop === null) {
            return redirect()->route('vendor.dashboard');
        }

        $status = VendorScopeRequest::enum(
            $request,
            'status',
            array_map(fn (OrderStatus $case): string => $case->stored(), OrderStatus::cases())
        );

        $query = Order::query()
            ->where('shop_id', $shop->id)
            ->when($status !== '', fn ($q) => $q->where('order_status', $status))
            ->when(
                VendorScopeRequest::search($request) !== '',
                fn ($q) => $q->where('order_number', 'like', '%'.VendorScopeRequest::search($request).'%')
            )
            ->with(['customer:id,name', 'items.product:id,name,thumbnail'])
            ->orderByDesc('created_at');

        $orders = $query->paginate(15)->withQueryString();

        $statusCounts = Order::query()
            ->where('shop_id', $shop->id)
            ->selectRaw('order_status, COUNT(*) as aggregate')
            ->groupBy('order_status')
            ->pluck('aggregate', 'order_status');

        return view('vendor.orders.index', [
            'orders' => $orders,
            'statusCounts' => $statusCounts,
            'status' => $status,
            'search' => VendorScopeRequest::search($request),
            'statusCases' => OrderStatus::cases(),
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        $this->assertOwned($order);

        $order->load([
            'customer:id,name,email,phone',
            'items.product:id,name,thumbnail,sku',
            'items.variant:id,name,sku',
            'shipments',
            'returns',
            'statusHistory.changedBy:id,name',
        ]);

        return view('vendor.orders.show', [
            'order' => $order,
            'shippable' => in_array((string) $order->order_status, VendorFulfillmentService::shippableStatuses(), true),
            'transitions' => self::TRANSITIONS,
            'statusCase' => OrderStatus::tryFrom((string) $order->order_status),
        ]);
    }

    public function updateStatus(Request $request, Order $order, OrderWorkflowService $workflow): RedirectResponse
    {
        $this->assertOwned($order);

        $validated = $request->validate([
            'status' => ['required', 'in:'.implode(',', self::TRANSITIONS)],
            'note' => ['nullable', 'string', 'max:1000'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $actorId = (int) auth('vendor')->id();
        $note = $validated['note'] ?? null;

        match ($validated['status']) {
            'confirmed' => $workflow->confirm($order, $actorId, $note),
            'processing' => $workflow->process($order, $actorId, $note),
            'canceled' => $workflow->cancel($order, $actorId, $validated['reason'] ?? $note),
        };

        $labels = [
            'confirmed' => 'Dikonfirmasi',
            'processing' => 'Diproses',
            'canceled' => 'Dibatalkan',
        ];

        return back()->with('success', 'Status pesanan diubah menjadi '.$labels[$validated['status']].'.');
    }

    public function fulfillment(Request $request): View
    {
        $queue = $this->fulfillment->queue((int) min(100, max(5, $request->integer('limit', 25))));

        // Cetak label massal + ekspor memakai rute fulfillment existing via ?format=.
        $selected = array_values(array_filter(array_map('intval', (array) $request->input('order_ids', []))));
        $labels = $selected !== [] && $request->input('format') === 'labels'
            ? $this->fulfillment->labelsFor($selected)
            : [];
        $export = $selected !== [] && $request->input('format') === 'csv'
            ? $this->fulfillment->exportRows($selected)
            : [];

        // Ubah status massal via ?bulk_status=confirmed|processing|packed (POST _method disarankan, GET tetap idempotent-safe via konfirmasi view).
        $bulkResult = null;

        if ($request->isMethod('post') && $selected !== [] && is_string($request->input('bulk_status'))) {
            $bulkResult = $this->fulfillment->bulkStatus($selected, (string) $request->input('bulk_status'), $request->input('bulk_note'));
        }

        return view('vendor.fulfillment.index', [
            'orders' => $queue['orders'],
            'total' => $queue['total'],
            'value' => $queue['value'],
            'currency' => Currency::config(),
            'shoppableStatuses' => VendorFulfillmentService::shippableStatuses(),
            'labels' => $labels,
            'export' => $export,
            'bulkResult' => $bulkResult,
            'selected' => $selected,
        ]);
    }

    public function ship(Request $request, Order $order): RedirectResponse
    {
        $this->assertOwned($order);

        $validated = $request->validate([
            'courier' => ['required', 'string', 'max:40'],
            'service' => ['nullable', 'string', 'max:80'],
            'tracking_number' => ['required', 'string', 'max:80'],
            'provider_id' => ['nullable', 'integer', 'exists:providers,id'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'label_url' => ['nullable', 'url', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
            'order_ids' => ['nullable', 'array'],
            'order_ids.*' => ['integer'],
        ], [
            'tracking_number.required' => 'Nomor resi wajib diisi agar pelanggan dapat melacak pesanan.',
        ]);

        // Bulk fulfillment via rute ship existing: kirim order_ids[] + resi dasar yang sama.
        $bulkIds = array_values(array_filter(array_map('intval', (array) ($validated['order_ids'] ?? []))));

        if ($bulkIds !== []) {
            $result = $this->fulfillment->bulkShip($bulkIds, $validated);
            $ok = count($result['ok']);
            $fail = count($result['fail']);

            return back()->with(
                $fail === 0 ? 'success' : 'warning',
                "Pengiriman massal selesai: {$ok} berhasil".($fail > 0 ? ", {$fail} gagal. Periksa status tiap pesanan." : '.')
            );
        }

        $shipment = $this->fulfillment->ship($order, $validated);

        return back()->with(
            'success',
            'Pesanan dikirim dengan resi '.$shipment->tracking_number.' dan biaya '.Currency::format((float) $shipment->cost).'.'
        );
    }

    public function restockRequests(Request $request): View
    {
        return view('vendor.restock.requests.index', [
            'requests' => $this->fulfillment->restockRequests(
                min(200, max(10, $request->integer('limit', 50)))
            ),
        ]);
    }

    public function notifyRestock(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $notified = $this->fulfillment->notifyRestock($validated['id']);

        return back()->with(
            'success',
            $notified > 0 ? 'Pelanggan diberi tahu barang tersedia kembali.' : 'Permintaan ini sudah pernah dikirim.'
        );
    }

    private function assertOwned(Order $order): void
    {
        abort_if((int) $order->shop_id !== (int) auth('vendor')->user()->shop_id, 403);
    }
}
