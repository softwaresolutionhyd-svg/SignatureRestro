<?php

namespace App\Http\Controllers\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\InventoryProduct;
use App\Models\ManufacturingBom;
use App\Models\ManufacturingOrder;
use App\Models\Setting;
use App\Services\ManufacturingStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class OrderController extends Controller
{
    public function __construct(
        private readonly ManufacturingStockService $stock
    ) {}

    public function index(): View
    {
        $orders = ManufacturingOrder::query()
            ->with(['bom.finishedProduct:id,sku,name', 'user:id,name'])
            ->latest()
            ->paginate(Setting::pageSize('manufacturing_orders_per_page', 25));

        return view('manufacturing.orders.index', compact('orders'));
    }

    public function create(): View
    {
        $boms = ManufacturingBom::query()
            ->where('active', true)
            ->with(['finishedProduct:id,sku,name,uom'])
            ->orderBy('name')
            ->get();

        return view('manufacturing.orders.create', compact('boms'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bom_id' => ['required', 'integer', 'exists:tenant.manufacturing_boms,id'],
            'qty_ordered' => ['required', 'numeric', 'min:0.001'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $bom = ManufacturingBom::query()->whereKey($data['bom_id'])->where('active', true)->firstOrFail();

        $order = ManufacturingOrder::create([
            'company_id' => $bom->company_id,
            'bom_id' => $bom->id,
            'user_id' => $request->user()?->id,
            'qty_ordered' => $data['qty_ordered'],
            'status' => ManufacturingOrder::STATUS_DRAFT,
            'reference' => null,
            'note' => $data['note'] ?? null,
        ]);
        $order->update(['reference' => 'MO-'.$order->id]);

        return redirect()->route('manufacturing.orders.show', $order)->with('status', 'Manufacturing order created.');
    }

    public function show(ManufacturingOrder $order): View
    {
        $order->load(['bom.lines.component.uomConversions', 'bom.finishedProduct', 'user']);

        return view('manufacturing.orders.show', compact('order'));
    }

    public function destroy(ManufacturingOrder $order): RedirectResponse
    {
        if (!$order->isDraft()) {
            return redirect()->back()->withErrors('Only draft orders can be deleted.');
        }
        $order->delete();

        return redirect()->route('manufacturing.orders.index')->with('status', 'Order deleted.');
    }

    public function complete(Request $request, ManufacturingOrder $order): RedirectResponse
    {
        if (!$order->isDraft()) {
            return redirect()->back()->withErrors('This order is not pending.');
        }

        try {
            DB::connection('tenant')->transaction(function () use ($order, $request) {
                $order = ManufacturingOrder::query()->lockForUpdate()->findOrFail($order->id);
                if ($order->status !== ManufacturingOrder::STATUS_DRAFT) {
                    abort(422, 'Order already processed.');
                }

                $bom = ManufacturingBom::query()
                    ->whereKey($order->bom_id)
                    ->lockForUpdate()
                    ->with(['lines.component.uomConversions', 'finishedProduct'])
                    ->firstOrFail();

                if (!$bom->active) {
                    abort(422, 'This BoM is inactive.');
                }

                $batch = (float) $bom->batch_qty;
                if ($batch <= 0) {
                    abort(422, 'Invalid BoM batch quantity.');
                }

                $mult = (float) $order->qty_ordered / $batch;

                $productIds = $bom->lines->pluck('component_product_id')->push($bom->finished_product_id)->unique()->sort()->values()->all();

                $locked = [];
                foreach ($productIds as $pid) {
                    $locked[$pid] = InventoryProduct::query()->lockForUpdate()->findOrFail($pid);
                }

                $ref = 'MFG-ORD-'.$order->id;

                $absorbedTotal = 0.0;
                foreach ($bom->lines as $line) {
                    $component = $locked[$line->component_product_id];
                    $component->loadMissing('uomConversions');
                    $lineUom = $line->uom !== null && trim((string) $line->uom) !== ''
                        ? trim((string) $line->uom)
                        : (string) $component->uom;
                    $qtyInLineUom = (float) $line->qty * $mult;
                    $needBase = $component->convertQtyToBaseUom($qtyInLineUom, $lineUom);
                    $absorbedTotal += $this->stock->stockOut(
                        $component,
                        $needBase,
                        $request->user()?->id,
                        $ref,
                        'MO #'.$order->id.' component'
                    );
                }

                $finishedQty = (float) $order->qty_ordered;
                $absorbedUnit = $finishedQty > 0 ? round($absorbedTotal / $finishedQty, 6) : 0.0;

                $finished = $locked[$bom->finished_product_id];
                $this->stock->stockIn(
                    $finished,
                    $finishedQty,
                    $request->user()?->id,
                    $ref,
                    'MO #'.$order->id.' output (FIFO absorbed)',
                    $absorbedUnit
                );

                $order->update([
                    'status' => ManufacturingOrder::STATUS_DONE,
                    'completed_at' => now(),
                ]);
            });
        } catch (RuntimeException|InvalidArgumentException $e) {
            return redirect()->back()->withErrors($e->getMessage());
        }

        return redirect()->route('manufacturing.orders.show', $order)->with('status', 'Production completed. Inventory updated.');
    }
}
