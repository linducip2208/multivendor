<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Backoffice\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function __construct(private readonly StockService $stock) {}

    public function index(Request $request): View
    {
        return view('admin.inventory.index', [
            'report' => $this->stock->overview(
                (int) $request->query('page', 1),
                (int) $request->query('per_page', 20),
                $this->search($request),
                (string) $request->query('warehouse', ''),
                (string) $request->query('state', ''),
            ),
            'warehouses' => $this->stock->warehouses(),
            'states' => $this->stockStates(),
        ]);
    }

    public function warehouses(Request $request): View
    {
        return view('admin.inventory.warehouses', [
            'rows' => $this->stock->warehouses(),
            'transfers' => $this->stock->transfers(
                (int) $request->query('page', 1),
                (int) $request->query('per_page', 20),
                (string) $request->query('status', ''),
            ),
        ]);
    }

    public function storeWarehouse(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->warehouseRules());

        $this->stock->createWarehouse($validated, auth('admin')->id());

        return back()->with('success', 'Gudang berhasil ditambahkan.');
    }

    public function updateWarehouse(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $validated = $request->validate($this->warehouseRules($warehouse->id));

        $this->stock->updateWarehouse($warehouse, $validated, auth('admin')->id());

        return back()->with('success', 'Gudang berhasil diperbarui.');
    }

    public function movements(Request $request): View
    {
        return view('admin.inventory.movements', [
            'report' => $this->stock->movements(
                (int) $request->query('page', 1),
                (int) $request->query('per_page', 25),
                $this->search($request),
                (string) $request->query('type', ''),
            ),
        ]);
    }

    public function adjust(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')],
            'product_variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')],
            'on_hand' => ['required', 'integer', 'min:0', 'max:100000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->stock->adjust(
            (int) $validated['product_id'],
            (int) $validated['warehouse_id'],
            (int) $validated['on_hand'],
            isset($validated['product_variant_id']) && $validated['product_variant_id'] !== null
                ? (int) $validated['product_variant_id']
                : null,
            (string) ($validated['note'] ?? ''),
            auth('admin')->id(),
        );

        return back()->with(
            'success',
            $result['delta'] === 0
                ? 'Stok tidak berubah; tidak ada movements yang dicatat.'
                : 'Stok disesuaikan '.($result['delta'] > 0 ? '+' : '').$result['delta'].'. Saldo akhir '.$result['balance_after'].'.',
        );
    }

    public function opname(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'lines.*.warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')],
            'lines.*.quantity' => ['required', 'integer', 'min:0', 'max:100000000'],
        ]);

        $result = $this->stock->opname($validated['lines'], auth('admin')->id());

        return back()->with(
            'success',
            'Opname selesai untuk '.$result['processed'].' baris, '.$result['changed'].' baris berbeda dari sistem.',
        );
    }

    public function storeTransfer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')],
            'to_warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id'), 'different:from_warehouse_id'],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ]);

        $transfer = $this->stock->createTransfer(
            (int) $validated['from_warehouse_id'],
            (int) $validated['to_warehouse_id'],
            $validated['items'],
            (string) ($validated['note'] ?? ''),
            auth('admin')->id(),
        );

        return redirect()
            ->route('admin.inventory.warehouses')
            ->with('success', 'Transfer '.$transfer->transfer_number.' dibuat dan ditandai dalam perjalanan.');
    }

    public function receiveTransfer(Request $request, \App\Models\StockTransfer $transfer): RedirectResponse
    {
        $validated = $request->validate([
            'received' => ['nullable', 'array'],
            'received.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        $received = [];
        foreach ((array) ($validated['received'] ?? []) as $itemId => $quantity) {
            $received[(int) $itemId] = (int) $quantity;
        }

        $updated = $this->stock->receiveTransfer($transfer, $received, auth('admin')->id());

        return back()->with(
            'success',
            $updated->status === 'received'
                ? 'Transfer '.$updated->transfer_number.' selesai diterima.'
                : 'Penerimaan dicatat. Masih ada item yang belum diterima penuh.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function warehouseRules(?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:40', Rule::unique('warehouses', 'code')->ignore($ignoreId)],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:12'],
            'country' => ['nullable', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:30'],
            'manager_name' => ['nullable', 'string', 'max:160'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function stockStates(): array
    {
        return [
            ['value' => '', 'label' => 'Semua kondisi'],
            ['value' => 'healthy', 'label' => 'Stok aman'],
            ['value' => 'low', 'label' => 'Stok menipis'],
            ['value' => 'out', 'label' => 'Stok habis'],
        ];
    }

    private function search(Request $request): string
    {
        return trim((string) $request->query('search', ''));
    }
}
