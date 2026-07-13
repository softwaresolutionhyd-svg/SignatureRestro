<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseOrderStoreRequest;
use App\Models\InventoryProduct;
use App\Models\InventoryProductUomConversion;
use App\Models\InventoryUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseVendor;
use App\Models\Setting;
use App\Services\AutoJournalService;
use App\Services\PurchaseCreditLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(
        private readonly AutoJournalService $autoJournal,
        private readonly PurchaseCreditLedgerService $purchaseCreditLedger
    ) {}

    public function index(Request $request)
    {
        $status = $request->query('status');

        $orders = PurchaseOrder::query()
            ->with('vendor:id,name')
            ->when(in_array($status, ['rfq', 'confirmed', 'received', 'cancelled'], true), fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(Setting::pageSize('purchase_orders_per_page', 20))
            ->withQueryString();

        return view('purchase.orders.index', compact('orders', 'status'));
    }

    public function create()
    {
        $vendors = PurchaseVendor::query()->where('active', true)->orderBy('name')->get(['id', 'name']);
        $uomLibraryUnits = Schema::hasTable('inventory_units')
            ? InventoryUnit::query()->orderBy('code')->get(['code', 'name'])
            : collect();
        $products = InventoryProduct::query()
            ->where('active', true)
            ->where('for_purchase', true)
            ->with(['uomConversions' => function ($q) {
                $q->where('active', true)->select(['id', 'product_id', 'uom', 'factor_to_base']);
            }])
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'uom', 'cost', 'package_contents_qty', 'package_contents_uom']);

        return view('purchase.orders.create', compact('vendors', 'products', 'uomLibraryUnits'));
    }

    public function store(PurchaseOrderStoreRequest $request)
    {
        $data = $request->validated();

        $order = DB::connection('tenant')->transaction(function () use ($data, $request) {
            $nextId = (PurchaseOrder::query()->max('id') ?? 0) + 1;
            $number = 'PO' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);

            $order = PurchaseOrder::create([
                'number' => $number,
                'vendor_id' => $data['vendor_id'],
                'created_by' => $request->user()->id,
                'status' => 'rfq',
                'purchase_type' => $data['purchase_type'],
                'payment_status' => $data['purchase_type'] === 'credit' ? 'unpaid' : 'paid',
                'order_date' => $data['order_date'] ?? null,
                'expected_date' => $data['expected_date'] ?? null,
                'paid_at' => $data['purchase_type'] === 'credit' ? null : now(),
                'note' => $data['note'] ?? null,
            ]);

            $this->syncLinesAndTotals($order, $data['lines']);

            return $order;
        });

        $this->purchaseCreditLedger->syncForOrder($order->fresh('vendor'));

        return redirect()->route('purchase.orders.edit', $order)->with('status', 'RFQ created.');
    }

    public function edit(PurchaseOrder $order)
    {
        $order->load(['vendor:id,name', 'lines.product:id,sku,name,uom']);

        $vendors = PurchaseVendor::query()->where('active', true)->orderBy('name')->get(['id', 'name']);
        $uomLibraryUnits = Schema::hasTable('inventory_units')
            ? InventoryUnit::query()->orderBy('code')->get(['code', 'name'])
            : collect();
        $products = InventoryProduct::query()
            ->where('active', true)
            ->where('for_purchase', true)
            ->with(['uomConversions' => function ($q) {
                $q->where('active', true)->select(['id', 'product_id', 'uom', 'factor_to_base']);
            }])
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'uom', 'cost', 'package_contents_qty', 'package_contents_uom']);

        return view('purchase.orders.edit', compact('order', 'vendors', 'products', 'uomLibraryUnits'));
    }

    public function update(PurchaseOrderStoreRequest $request, PurchaseOrder $order)
    {
        abort_unless($order->status === 'rfq', 403);

        $data = $request->validated();

        DB::connection('tenant')->transaction(function () use ($order, $data) {
            $order->update([
                'vendor_id' => $data['vendor_id'],
                'purchase_type' => $data['purchase_type'],
                'payment_status' => $data['purchase_type'] === 'credit' ? 'unpaid' : 'paid',
                'order_date' => $data['order_date'] ?? null,
                'expected_date' => $data['expected_date'] ?? null,
                'paid_at' => $data['purchase_type'] === 'credit' ? null : now(),
                'note' => $data['note'] ?? null,
            ]);

            $this->syncLinesAndTotals($order, $data['lines']);
        });

        $this->purchaseCreditLedger->syncForOrder($order->fresh('vendor'));

        return redirect()->route('purchase.orders.edit', $order)->with('status', 'RFQ updated.');
    }

    public function quickAddProduct(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'uom' => ['required', 'string', 'max:30'],
            'package_contents_qty' => ['nullable', 'numeric', 'min:0.000001'],
            'package_contents_uom' => ['nullable', 'required_with:package_contents_qty', 'string', 'max:30'],
            'cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $baseUom = InventoryUnit::normalizeCode((string) ($data['uom'] ?? ''));
        $innerUom = InventoryUnit::normalizeCode((string) ($data['package_contents_uom'] ?? ''));

        if ($innerUom !== '' && $innerUom === $baseUom) {
            throw ValidationException::withMessages([
                'package_contents_uom' => ['Inner UOM must be different from base UOM.'],
            ]);
        }

        $product = DB::connection('tenant')->transaction(function () use ($data, $baseUom, $innerUom) {
            $product = InventoryProduct::create([
                'sku' => InventoryProduct::generateNextSku('PUR'),
                'name' => trim((string) $data['name']),
                'uom' => $baseUom,
                'package_contents_qty' => $innerUom !== '' ? (float) $data['package_contents_qty'] : null,
                'package_contents_uom' => $innerUom !== '' ? $innerUom : null,
                'cost' => isset($data['cost']) ? (float) $data['cost'] : 0.0,
                'gas_charges' => 0,
                'profit' => 0,
                'service_charges' => 0,
                'price' => 0,
                'qty_on_hand' => 0,
                'reorder_level' => 0,
                'active' => true,
                'for_pos' => false,
                'for_purchase' => true,
            ]);

            if ($innerUom !== '' && (float) ($data['package_contents_qty'] ?? 0) > 0) {
                InventoryProductUomConversion::query()->updateOrCreate(
                    ['product_id' => $product->id, 'uom' => $innerUom],
                    ['factor_to_base' => 1 / (float) $data['package_contents_qty'], 'active' => true]
                );
            }

            return $product;
        });

        $product->load(['uomConversions' => fn ($q) => $q->where('active', true)->select(['id', 'product_id', 'uom', 'factor_to_base'])]);
        $uoms = $product->uomsForForms();

        return response()->json([
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'label' => $product->name,
                'base_uom' => $product->uom,
                'cost' => (float) $product->cost,
                'package_contents_qty' => $product->package_contents_qty !== null ? (float) $product->package_contents_qty : null,
                'package_contents_uom' => $product->package_contents_uom,
                'uoms' => $uoms,
                'search_label' => $product->name,
                'sku' => $product->sku,
            ],
        ], 201);
    }

    public function quickEditProduct(Request $request, InventoryProduct $product)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'uom' => ['required', 'string', 'max:30'],
            'package_contents_qty' => ['nullable', 'numeric', 'min:0.000001'],
            'package_contents_uom' => ['nullable', 'required_with:package_contents_qty', 'string', 'max:30'],
            'cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $baseUom = InventoryUnit::normalizeCode((string) ($data['uom'] ?? ''));
        $innerUom = InventoryUnit::normalizeCode((string) ($data['package_contents_uom'] ?? ''));

        if ($innerUom !== '' && $innerUom === $baseUom) {
            throw ValidationException::withMessages([
                'package_contents_uom' => ['Inner UOM must be different from base UOM.'],
            ]);
        }

        DB::connection('tenant')->transaction(function () use ($product, $data, $baseUom, $innerUom) {
            $product->update([
                'name' => trim((string) $data['name']),
                'uom' => $baseUom,
                'package_contents_qty' => $innerUom !== '' ? (float) $data['package_contents_qty'] : null,
                'package_contents_uom' => $innerUom !== '' ? $innerUom : null,
                'cost' => isset($data['cost']) ? (float) $data['cost'] : 0.0,
                'for_purchase' => true,
            ]);

            if ($innerUom !== '' && (float) ($data['package_contents_qty'] ?? 0) > 0) {
                InventoryProductUomConversion::query()->updateOrCreate(
                    ['product_id' => $product->id, 'uom' => $innerUom],
                    ['factor_to_base' => 1 / (float) $data['package_contents_qty'], 'active' => true]
                );
            }
        });

        $product->refresh();
        $product->load(['uomConversions' => fn ($q) => $q->where('active', true)->select(['id', 'product_id', 'uom', 'factor_to_base'])]);
        $uoms = $product->uomsForForms();

        return response()->json([
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'label' => $product->name,
                'base_uom' => $product->uom,
                'cost' => (float) $product->cost,
                'package_contents_qty' => $product->package_contents_qty !== null ? (float) $product->package_contents_qty : null,
                'package_contents_uom' => $product->package_contents_uom,
                'uoms' => $uoms,
                'search_label' => $product->name,
                'sku' => $product->sku,
            ],
        ]);
    }

    public function confirm(PurchaseOrder $order)
    {
        abort_unless($order->status === 'rfq', 403);

        $order->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        return redirect()->route('purchase.orders.edit', $order)->with('status', 'PO confirmed.');
    }

    public function markPaid(PurchaseOrder $order)
    {
        abort_unless($order->purchase_type === 'credit', 403);
        abort_unless($order->payment_status !== 'paid', 403);
        abort_unless($order->status !== 'cancelled', 403);

        $order->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->purchaseCreditLedger->registerPayment($order->fresh('vendor'));
        $this->autoJournal->postPurchasePaid($order);

        return redirect()->route('purchase.orders.edit', $order)->with('status', 'Purchase marked as paid.');
    }

    private function syncLinesAndTotals(PurchaseOrder $order, array $lines): void
    {
        PurchaseOrderLine::query()->where('purchase_order_id', $order->id)->delete();

        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($lines as $l) {
            $qty = (float) $l['qty'];
            $price = (float) $l['unit_price'];
            $taxPercent = isset($l['tax_percent']) ? (float) $l['tax_percent'] : 0.0;

            $lineSubtotal = $qty * $price;
            $lineTax = $lineSubtotal * ($taxPercent / 100.0);
            $lineTotal = $lineSubtotal + $lineTax;

            $subtotal += $lineSubtotal;
            $taxTotal += $lineTax;

            PurchaseOrderLine::create([
                'company_id' => $order->company_id,
                'purchase_order_id' => $order->id,
                'product_id' => $l['product_id'],
                'description' => $l['description'] ?? null,
                'uom' => $l['uom'],
                'qty' => $qty,
                'unit_price' => $price,
                'tax_percent' => $taxPercent,
                'subtotal' => $lineSubtotal,
                'tax_amount' => $lineTax,
                'total' => $lineTotal,
            ]);
        }

        $order->update([
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'grand_total' => $subtotal + $taxTotal,
        ]);
    }
}
