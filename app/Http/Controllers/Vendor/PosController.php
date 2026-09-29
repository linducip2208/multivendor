<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Services\Vendor\PosService;
use App\Support\Currency;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PosController extends Controller
{
    public function __construct(private readonly PosService $pos) {}

    public function index(Request $request): View
    {
        $shop = auth('vendor')->user()->shop;
        abort_if($shop === null, 403);

        $search = VendorScopeRequest::search($request);

        $query = Product::query()
            ->where('shop_id', $shop->id)
            ->where('status', 'approved')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'));

        return view('vendor.pos.index', [
            'products' => $query->paginate(12)->withQueryString(),
            'shop' => $shop,
            'search' => $search,
            'currency' => Currency::config(),
        ]);
    }

    public function storeOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.product_variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0', 'max:1000000000000'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:1000000000000'],
            // Split tender: bila diisi, total semua tender harus pas dengan
            // total belanja (ditegakkan di PosService, atomik + idempoten).
            'tenders' => ['nullable', 'array', 'min:1', 'max:5'],
            'tenders.*.method' => ['required_with:tenders', 'string', 'in:'.implode(',', \App\Services\Vendor\PosService::TENDER_METHODS)],
            'tenders.*.amount' => ['required_with:tenders', 'numeric', 'min:1', 'max:1000000000000'],
            'idempotency_key' => ['nullable', 'string', 'max:80'],
            'payment_method' => ['required', 'in:cash,transfer,qris,split'],
            'hold' => ['nullable', 'boolean'],
            'pos_shift_id' => ['nullable', 'integer', 'exists:pos_shifts,id'],
            'pos_register_id' => ['nullable', 'integer', 'exists:pos_registers,id'],
        ]);

        $order = $this->pos->sell($validated);
        $tenders = $this->pos->tendersFor($order);

        return response()->json([
            'success' => true,
            'order_number' => $order->order_number,
            'total' => (float) $order->total,
            'total_formatted' => Currency::format((float) $order->total),
            'hold' => (bool) ($validated['hold'] ?? false),
            'payment_method' => (string) $order->payment_method,
            'tenders' => array_map(fn ($row) => [
                'method' => $row['method'],
                'amount' => (float) $row['amount'],
                'amount_formatted' => Currency::format((float) $row['amount']),
            ], $tenders),
            // Struk digital: tautan verifikasi (token) + WA bila ada nomor.
            'receipt_url' => $this->pos->receiptUrl($order),
            'wa_url' => $this->pos->waLink($order),
            'redirect' => route('vendor.pos.held'),
        ]);
    }

    public function heldOrders(Request $request): View
    {
        $shopId = (int) auth('vendor')->user()->shop_id;

        $orders = Order::query()
            ->where('shop_id', $shopId)
            ->where('order_status', 'pending')
            ->where('order_number', 'like', 'HOLD-%')
            ->with('items.product:id,name,thumbnail')
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('vendor.pos.held', [
            'orders' => $orders,
            'currency' => Currency::config(),
        ]);
    }

    public function resumeHeldOrder(Request $request, Order $order): RedirectResponse
    {
        $this->pos->resume($order);

        return back()->with('success', 'Hold order '.$order->order_number.' dilanjutkan menjadi transaksi lunas.');
    }

    public function cancelHeldOrder(Request $request, Order $order): RedirectResponse
    {
        $this->pos->cancelHold($order);

        return back()->with('success', 'Hold order '.$order->order_number.' dibatalkan.');
    }

    public function printInvoice(Request $request, Order $order): View
    {
        $this->assertOwned($order);

        // Verifikasi token struk digital: ?token= salah → 403. Tanpa token
        // tetap diizinkan untuk kasir pemilik (order legacy belum bertoken).
        abort_unless($this->pos->receiptVerifyOk($order, $request->query('token')), 403, 'Tautan verifikasi struk tidak valid.');

        $order->load(['items.product', 'customer', 'shop']);

        return view('vendor.pos.invoice-print', [
            'order' => $order,
            'currency' => Currency::config(),
            'tenders' => $this->pos->tendersFor($order),
            'receiptUrl' => $this->pos->receiptUrl($order),
            'waUrl' => $this->pos->waLink($order),
        ]);
    }

    public function printInvoicePdf(Request $request, Order $order)
    {
        $this->assertOwned($order);

        abort_unless($this->pos->receiptVerifyOk($order, $request->query('token')), 403, 'Tautan verifikasi struk tidak valid.');

        $order->load(['items.product', 'customer', 'shop']);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('vendor.pos.invoice-pdf', [
            'order' => $order,
            'currency' => Currency::config(),
            'tenders' => $this->pos->tendersFor($order),
            'receiptUrl' => $this->pos->receiptUrl($order),
        ]);

        return $pdf->download('pos-invoice-'.$order->order_number.'.pdf');
    }

    public function total(array $items, float $discount = 0.0): Money
    {
        $subTotal = Money::zero();

        foreach ($items as $item) {
            $subTotal = $subTotal->add(Money::of($item['price'] ?? 0)->multiply((int) ($item['quantity'] ?? 0)));
        }

        return $subTotal->subtract(Money::of($discount))->maxZero();
    }

    private function assertOwned(Order $order): void
    {
        abort_if((int) $order->shop_id !== (int) auth('vendor')->user()->shop_id, 403);
    }
}
