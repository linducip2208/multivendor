<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Vendor\VendorInventoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request, VendorInventoryService $inventory): View
    {
        $data = $inventory->overview(
            VendorScopeRequest::search($request),
            VendorScopeRequest::stockFilter($request),
        );

        return view('vendor.inventory.index', [
            'products' => $data['products'],
            'stats' => $data['stats'],
            'search' => $data['search'],
            'stock' => $data['stock'],
        ]);
    }

    public function movements(Request $request, VendorInventoryService $inventory): View
    {
        $transfers = \App\Models\StockTransfer::query()
            ->with(['fromWarehouse:id,name,code', 'toWarehouse:id,name,code'])
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        return view('vendor.inventory.movements', [
            'movements' => $inventory->movements([
                'product_id' => $request->integer('product_id') ?: null,
                'type' => VendorScopeRequest::movementType($request),
            ]),
            'products' => $inventory->overview('', '')['products']->getCollection()->pluck('name', 'id'),
            'types' => [
                'in' => 'Barang masuk',
                'out' => 'Barang keluar',
                'adjustment' => 'Penyesuaian',
                'transfer' => 'Transfer gudang',
                'return' => 'Retur ke stok',
                'opname' => 'Stock opname',
            ],
            'selected' => [
                'product_id' => $request->integer('product_id') ?: '',
                'type' => VendorScopeRequest::movementType($request),
            ],
            'transfers' => $transfers,
            'variance' => $inventory->varianceReport(25),
            'warehouses' => \App\Models\Warehouse::query()->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function adjust(Request $request, VendorInventoryService $inventory): RedirectResponse
    {
        $action = (string) $request->input('action', 'adjust');

        if ($action === 'allocate') {
            return $this->handleAllocate($request, $inventory);
        }

        if (in_array($action, ['transfer_request', 'transfer_approve', 'transfer_receive', 'transfer_cancel'], true)) {
            return $this->handleTransfer($request, $inventory, $action);
        }

        if ($action === 'opname') {
            $validated = $request->validate([
                'product_id' => ['required', 'integer', 'exists:products,id'],
                'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
                'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
                'reason' => ['required', 'string', 'max:255'],
            ]);

            $product = Product::query()->findOrFail($validated['product_id']);
            $inventory->opname($product, (int) $validated['quantity'], $validated['warehouse_id'] ?? null, $validated['reason']);

            return back()->with('success', 'Hasil opname '.$product->name.' dicatat. Selisih dapat dilihat pada laporan selisih.');
        }

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'mode' => ['required', 'in:increase,decrease,set'],
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $product = Product::query()->findOrFail($validated['product_id']);
        abort_if((int) $product->shop_id !== (int) auth('vendor')->user()->shop_id, 403);

        $current = (int) $product->current_stock;
        $target = $validated['mode'] === 'set'
            ? (int) $validated['quantity']
            : $current + ($validated['mode'] === 'increase' ? 1 : -1) * (int) $validated['quantity'];

        $delta = $target - $current;

        if ($delta !== 0) {
            $inventory->adjust($product, $delta, 'adjustment', $validated['reason']);
        }

        return back()->with(
            $delta === 0 ? 'warning' : 'success',
            $delta === 0
                ? 'Stok tidak berubah karena nilai baru sama dengan stok saat ini.'
                : 'Stok '.$product->name.' diperbarui menjadi '.$target.' unit.'
        );
    }

    private function handleTransfer(Request $request, VendorInventoryService $inventory, string $action): RedirectResponse
    {
        if ($action === 'transfer_request') {
            $validated = $request->validate([
                'from_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
                'to_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
                'items' => ['nullable', 'array', 'min:1'],
                'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
                'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
                'items_text' => ['nullable', 'string', 'max:2000'],
                'reason' => ['nullable', 'string', 'max:255'],
            ]);

            $items = $validated['items'] ?? [];

            if ($items === [] && ! empty($validated['items_text'])) {
                foreach (explode(',', (string) $validated['items_text']) as $pair) {
                    [$pid, $qty] = array_pad(explode(':', trim($pair), 2), 2, null);

                    if (is_numeric($pid) && is_numeric($qty) && (int) $qty > 0) {
                        $items[] = ['product_id' => (int) $pid, 'quantity' => (int) $qty];
                    }
                }
            }

            if ($items === []) {
                return back()->withInput()->with('error', 'Tambahkan minimal satu produk untuk transfer.');
            }

            $transfer = $inventory->requestTransfer(
                (int) $validated['from_warehouse_id'],
                (int) $validated['to_warehouse_id'],
                $items,
                $validated['reason'] ?? null,
            );

            return back()->with('success', 'Transfer '.$transfer->transfer_number.' dibuat sebagai draft. Menunggu persetujuan.');
        }

        $validated = $request->validate(['transfer_id' => ['required', 'integer', 'exists:stock_transfers,id']]);
        $transfer = \App\Models\StockTransfer::query()->findOrFail($validated['transfer_id']);

        match ($action) {
            'transfer_approve' => $inventory->approveTransfer($transfer),
            'transfer_receive' => $inventory->receiveTransfer($transfer),
            default => $inventory->cancelTransfer($transfer),
        };

        $labels = [
            'transfer_approve' => 'disetujui dan dikirim',
            'transfer_receive' => 'diterima dan stok tujuan ditambah',
            'transfer_cancel' => 'dibatalkan',
        ];

        return back()->with('success', 'Transfer '.$transfer->transfer_number.' '.($labels[$action] ?? 'diproses').'.');
    }

    /**
     * Saran alokasi gudang otomatis (aditif, read-only): tanpa mengunci stok,
     * hasil dikembalikan sebagai flash agar tampil pada halaman inventori.
     */
    private function handleAllocate(Request $request, VendorInventoryService $inventory): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'city' => ['nullable', 'string', 'max:100'],
        ]);

        $product = Product::query()->findOrFail($validated['product_id']);
        $result = $inventory->allocationFor($product, (int) $validated['quantity'], $validated['city'] ?? null);
        $warehouse = $result['warehouse'];

        if ($warehouse === null) {
            return back()->withInput()->with('warning', 'Tidak ada gudang aktif untuk alokasi.');
        }

        return back()->with('allocation', [
            'product' => $product->name,
            'quantity' => (int) $validated['quantity'],
            'warehouse' => $warehouse->name.' ('.$warehouse->code.')',
            'city' => $warehouse->city ?? '-',
            'available' => $result['available'],
            'full' => $result['full'],
        ])->with(
            'success',
            'Alokasi: '.$warehouse->name.' ('.$warehouse->code.') — tersedia '.$result['available'].' unit'
            .($result['full'] ? ', cukup untuk kebutuhan.' : ', kurang dari kebutuhan, gudang utama dipakai sebagai fallback.')
        );
    }
}
