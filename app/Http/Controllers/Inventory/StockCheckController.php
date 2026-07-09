<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryProduct;
use App\Models\Setting;
use App\Models\StockCheck;
use App\Models\StockCheckLine;
use App\Services\StockCheckApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockCheckController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $checks = StockCheck::query()
            ->withCount('lines')
            ->when(in_array($status, ['draft', 'pending_approval', 'approved', 'rejected'], true), fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(Setting::pageSize('inventory_products_per_page', 20))
            ->withQueryString();

        return view('inventory.stock-check.index', compact('checks', 'status'));
    }

    public function create()
    {
        $products = InventoryProduct::query()
            ->where('active', true)
            ->orderBy('name')
            ->with(['uomConversions' => function ($q) {
                $q->where('active', true)->select(['id', 'product_id', 'uom', 'factor_to_base']);
            }])
            ->get(['id', 'sku', 'name', 'uom', 'qty_on_hand', 'for_purchase', 'package_contents_qty', 'package_contents_uom']);

        $autoLineProductIds = $products->where('for_purchase', true)->pluck('id')->values();

        return view('inventory.stock-check.create', compact('products', 'autoLineProductIds'));
    }

    public function store(Request $request)
    {
        $data = $this->validatedLinesRequest($request);

        $check = DB::connection('tenant')->transaction(function () use ($data, $request) {
            $nextId = (StockCheck::query()->max('id') ?? 0) + 1;
            $number = 'SC'.str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);

            $check = StockCheck::create([
                'number' => $number,
                'title' => $data['title'] ?? null,
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);

            $this->syncLines($check, $data['lines']);

            return $check;
        });

        return redirect()->route('inventory.stock-check.show', $check)->with('status', 'Draft saved.');
    }

    public function show(StockCheck $stockCheck)
    {
        $stockCheck->load(['lines.product:id,sku,name,uom']);

        return view('inventory.stock-check.show', compact('stockCheck'));
    }

    public function edit(StockCheck $stockCheck)
    {
        abort_unless($stockCheck->isDraft(), 403);

        $stockCheck->load(['lines.product:id,sku,name,uom,qty_on_hand']);

        $products = InventoryProduct::query()
            ->where('active', true)
            ->orderBy('name')
            ->with(['uomConversions' => function ($q) {
                $q->where('active', true)->select(['id', 'product_id', 'uom', 'factor_to_base']);
            }])
            ->get(['id', 'sku', 'name', 'uom', 'qty_on_hand', 'package_contents_qty', 'package_contents_uom']);

        return view('inventory.stock-check.edit', compact('stockCheck', 'products'));
    }

    public function update(Request $request, StockCheck $stockCheck)
    {
        abort_unless($stockCheck->isDraft(), 403);

        $data = $this->validatedLinesRequest($request);

        DB::connection('tenant')->transaction(function () use ($stockCheck, $data) {
            StockCheckLine::query()->where('stock_check_id', $stockCheck->id)->delete();
            $stockCheck->update(['title' => $data['title'] ?? null]);
            $this->syncLines($stockCheck, $data['lines']);
        });

        return redirect()->route('inventory.stock-check.show', $stockCheck)->with('status', 'Draft updated.');
    }

    public function destroy(StockCheck $stockCheck)
    {
        abort_unless($stockCheck->isDraft(), 403);

        $stockCheck->delete();

        return redirect()->route('inventory.stock-check.index')->with('status', 'Draft deleted.');
    }

    public function submit(StockCheck $stockCheck)
    {
        abort_unless($stockCheck->isDraft(), 403);

        DB::connection('tenant')->transaction(function () use ($stockCheck) {
            $stockCheck->load('lines');
            foreach ($stockCheck->lines as $line) {
                if ($line->counted_qty === null) {
                    abort(422, 'Har product ke liye counted qty likhein (base UOM mein).');
                }
            }

            foreach ($stockCheck->lines as $line) {
                $product = InventoryProduct::query()->lockForUpdate()->findOrFail($line->product_id);
                $line->update(['expected_qty' => $product->qty_on_hand]);
            }

            $stockCheck->update([
                'status' => 'pending_approval',
                'submitted_at' => now(),
            ]);
        });

        return redirect()->route('inventory.stock-check.show', $stockCheck)->with('status', 'Admin approval ke liye bhej diya gaya.');
    }

    public function approve(StockCheck $stockCheck, StockCheckApprovalService $approval)
    {
        abort_unless($stockCheck->isPendingApproval(), 403);

        try {
            $approval->approve($stockCheck, (int) auth()->id());
        } catch (\Throwable $e) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                throw $e;
            }

            return redirect()
                ->route('inventory.stock-check.show', $stockCheck)
                ->withErrors(['approve' => $e->getMessage() ?: 'Approve failed.']);
        }

        return redirect()->route('inventory.stock-check.show', $stockCheck)->with('status', 'Approve ho gaya — stock adjust ho chuka hai.');
    }

    public function reject(Request $request, StockCheck $stockCheck)
    {
        abort_unless($stockCheck->isPendingApproval(), 403);

        $data = $request->validate([
            'reject_reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $stockCheck->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'reject_reason' => $data['reject_reason'],
        ]);

        return redirect()->route('inventory.stock-check.show', $stockCheck)->with('status', 'Reject kar diya — stock same hai.');
    }

    /**
     * @return array{title: ?string, lines: list<array{product_id: int, uom: string, qty: mixed}>}
     */
    private function validatedLinesRequest(Request $request): array
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists(InventoryProduct::class, 'id')],
            'lines.*.uom' => ['required', 'string', 'max:20'],
            'lines.*.qty' => ['nullable', 'numeric', 'min:0'],
        ]);

        $ids = array_column($data['lines'], 'product_id');
        if (count($ids) !== count(array_unique($ids))) {
            abort(422, 'Ek product ek hi dafa line mein aa sakta hai.');
        }

        return $data;
    }

    /**
     * @param  list<array{product_id: int, uom: string, qty: mixed}>  $lines
     */
    private function syncLines(StockCheck $check, array $lines): void
    {
        foreach ($lines as $row) {
            $product = InventoryProduct::query()->findOrFail((int) $row['product_id']);
            $qty = $row['qty'];
            $uom = trim((string) ($row['uom'] ?? ''));
            if ($uom === '') {
                abort(422, 'Line UOM required.');
            }
            $counted = null;
            if ($qty !== null && $qty !== '') {
                $counted = $product->convertQtyToBaseUom((float) $qty, $uom);
            }

            StockCheckLine::create([
                'stock_check_id' => $check->id,
                'product_id' => $product->id,
                'expected_qty' => (float) $product->qty_on_hand,
                'counted_qty' => $counted,
                'note' => null,
            ]);
        }
    }

}
