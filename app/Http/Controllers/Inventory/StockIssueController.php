<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryDepartment;
use App\Models\InventoryMove;
use App\Models\InventoryProduct;
use App\Models\InventoryProductStock;
use App\Models\Setting;
use App\Services\InventoryStockService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class StockIssueController extends Controller
{
    public function __construct(
        private readonly InventoryStockService $stockService
    ) {}

    public function index(Request $request)
    {
        $this->stockService->ensureWarehouse();

        $date = $this->parseIssueDate($request->input('date'));
        $grouped = $this->issuesGroupedByDepartment($date);
        $warehouse = InventoryDepartment::query()->where('is_warehouse', true)->first();
        $totalLines = $grouped->sum('count');

        return view('inventory.issues.index', compact('grouped', 'warehouse', 'date', 'totalLines'));
    }

    public function create()
    {
        $warehouse = $this->stockService->ensureWarehouse();

        $departments = InventoryDepartment::query()
            ->where('is_warehouse', false)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $products = InventoryProduct::query()
            ->where('active', true)
            ->where('for_purchase', true)
            ->orderBy('name')
            ->with([
                'stocks' => fn ($q) => $q->where('department_id', $warehouse->id),
                'uomConversions' => fn ($q) => $q->where('active', true)->select(['id', 'product_id', 'uom', 'factor_to_base']),
            ])
            ->get(['id', 'sku', 'name', 'uom', 'qty_on_hand', 'cost', 'package_contents_qty', 'package_contents_uom']);

        $products->transform(function (InventoryProduct $product) use ($warehouse) {
            $product->warehouse_qty = $this->stockService->stockQty((int) $product->id, (int) $warehouse->id);

            return $product;
        });

        return view('inventory.issues.create', compact('warehouse', 'departments', 'products'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'to_department_id' => ['required', 'integer', 'exists:tenant.inventory_departments,id'],
            'note' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:80'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:tenant.inventory_products,id'],
            'lines.*.qty_uom' => ['required', 'numeric', 'gt:0'],
            'lines.*.uom' => ['required', 'string', 'max:30'],
        ]);

        $toDepartment = InventoryDepartment::query()->findOrFail($data['to_department_id']);
        abort_if($toDepartment->is_warehouse, 422, 'Target department warehouse nahi ho sakta.');

        $productIds = array_column($data['lines'], 'product_id');
        if (count($productIds) !== count(array_unique($productIds))) {
            return back()->withErrors(['lines' => 'Ek product ek hi dafa line mein aa sakta hai.'])->withInput();
        }

        $issued = [];

        try {
            \Illuminate\Support\Facades\DB::connection('tenant')->transaction(function () use ($request, $data, $toDepartment, &$issued) {
                foreach ($data['lines'] as $row) {
                    $product = InventoryProduct::query()->lockForUpdate()->findOrFail($row['product_id']);
                    $factor = $product->factorToBaseForUom((string) $row['uom']);
                    if ($factor === null || $factor <= 0) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'lines' => $product->sku.': invalid UOM.',
                        ]);
                    }

                    $qtyBase = round((float) $row['qty_uom'] * $factor, 3);

                    $this->stockService->issueFromWarehouse(
                        $product,
                        $toDepartment,
                        $qtyBase,
                        (string) $row['uom'],
                        (float) $row['qty_uom'],
                        $factor,
                        (int) $request->user()?->id,
                        $data['note'] ?? null,
                        $data['reference'] ?? null
                    );

                    $issued[] = $product->name;
                }
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            return back()->withErrors(['lines' => $e->getMessage()])->withInput();
        } catch (\Throwable $e) {
            return back()->withErrors(['lines' => $e->getMessage() ?: 'Issue failed.'])->withInput();
        }

        $count = count($issued);
        $label = $count === 1
            ? sprintf('Stock %s ko %s mein issue ho gaya.', $issued[0], $toDepartment->name)
            : sprintf('%d products %s mein issue ho gaye.', $count, $toDepartment->name);

        return redirect()
            ->route('inventory.issues.index')
            ->with('status', $label);
    }

    public function warehouseStockPrint()
    {
        $warehouse = $this->stockService->ensureWarehouse();

        $stocks = InventoryProductStock::query()
            ->where('department_id', $warehouse->id)
            ->where('qty_on_hand', '>', 0)
            ->with(['product:id,sku,name,uom,cost,gas_charges,extra_costs,price'])
            ->get();

        $lines = $stocks
            ->map(function (InventoryProductStock $stock) {
                $product = $stock->product;
                if (! $product) {
                    return null;
                }

                $qty = (float) $stock->qty_on_hand;
                $unitCost = (float) $product->total;

                return [
                    'sku' => (string) $product->sku,
                    'name' => (string) $product->name,
                    'uom' => (string) $product->uom,
                    'qty' => $qty,
                    'unit_price' => $unitCost,
                    'amount' => round($qty * $unitCost, 2),
                ];
            })
            ->filter()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $grandTotal = round((float) $lines->sum('amount'), 2);
        $currency = Setting::get('currency_symbol', 'Rs.');

        return view('inventory.issues.warehouse-stock-print', compact(
            'warehouse',
            'lines',
            'grandTotal',
            'currency'
        ));
    }

    public function dailyPrint(Request $request)
    {
        $date = $this->parseIssueDate($request->input('date'));
        $grouped = $this->issuesGroupedByDepartment($date);
        $companyName = (string) Setting::get('company_name', config('app.name'));
        $companyLogo = company_logo_url(Setting::get('company_logo'));
        $totalLines = $grouped->sum('count');

        return view('inventory.issues.daily-print', compact(
            'date',
            'grouped',
            'companyName',
            'companyLogo',
            'totalLines'
        ));
    }

    private function parseIssueDate(mixed $date): string
    {
        try {
            return Carbon::parse($date ?: now()->toDateString())->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }

    /**
     * @return Collection<int, array{name: string, count: int, items: Collection<int, InventoryMove>}>
     */
    private function issuesGroupedByDepartment(string $date): Collection
    {
        $start = Carbon::parse($date)->startOfDay();
        $end = Carbon::parse($date)->endOfDay();

        $issues = InventoryMove::query()
            ->where('type', 'transfer')
            ->whereBetween('created_at', [$start, $end])
            ->with([
                'product:id,sku,name,uom',
                'user:id,name',
                'fromDepartment:id,name',
                'toDepartment:id,name',
            ])
            ->orderBy('created_at')
            ->get();

        return $issues
            ->groupBy(fn (InventoryMove $move) => (string) ($move->to_department_id ?: 0))
            ->map(function (Collection $rows) {
                $dept = $rows->first()?->toDepartment;

                return [
                    'name' => $dept?->name ?: 'Department',
                    'count' => $rows->count(),
                    'items' => $rows
                        ->sortBy(fn (InventoryMove $move) => mb_strtolower((string) ($move->product?->name ?? '')))
                        ->values(),
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }
}
