<?php

namespace App\Http\Controllers;

use App\Models\CreditLedger;
use App\Models\InventoryCostLayer;
use App\Models\InventoryMove;
use App\Models\InventoryProduct;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Setting;
use App\Models\StockDemand;
use App\Models\StockDemandLine;
use App\Services\InventoryStockService;
use App\Services\Sync\SyncAwareDelete;
use App\Support\SuperAdminAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PurchasesDemandsController extends Controller
{
    public function __construct(
        private readonly InventoryStockService $inventoryStock
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $canPurchase = (bool) $user?->canViewModule('purchase');
        $canDemand = (bool) $user?->canAccessDemand();
        abort_unless($canPurchase || $canDemand, 403);

        $tab = (string) $request->query('tab', $canPurchase ? 'purchases' : 'demands');
        if (! in_array($tab, ['purchases', 'demands'], true)) {
            $tab = $canPurchase ? 'purchases' : 'demands';
        }
        if ($tab === 'purchases' && ! $canPurchase) {
            $tab = 'demands';
        }
        if ($tab === 'demands' && ! $canDemand) {
            $tab = 'purchases';
        }

        $currency = (string) Setting::get('currency_symbol', 'Rs.');
        $isSuperAdmin = SuperAdminAudit::userIsSuperAdmin($user);

        $purchases = collect();
        if ($tab === 'purchases' && $canPurchase) {
            $purchases = PurchaseOrder::query()
                ->with('vendor:id,name')
                ->latest('id')
                ->limit(50)
                ->get();
        }

        $demands = collect();
        if ($tab === 'demands' && $canDemand && Schema::connection('tenant')->hasTable('stock_demands')) {
            $demands = StockDemand::query()
                ->with(['department:id,name', 'lines'])
                ->latest('id')
                ->limit(50)
                ->get();
        }

        return view('purchases-demands.index', compact(
            'tab',
            'canPurchase',
            'canDemand',
            'purchases',
            'demands',
            'currency',
            'isSuperAdmin'
        ));
    }

    /** Super Admin: capture reason then open purchase edit. */
    public function beginPurchaseEdit(Request $request, PurchaseOrder $order)
    {
        SuperAdminAudit::abortUnlessSuperAdmin($request->user());
        $reason = SuperAdminAudit::requireReason($request);

        $request->session()->put('super_admin_action', [
            'type' => SuperAdminAudit::TYPE_EDIT,
            'entity' => 'purchase_order',
            'id' => (int) $order->id,
            'number' => (string) $order->number,
            'reason' => $reason,
        ]);

        return redirect()->route('purchase.orders.edit', $order);
    }

    /** Super Admin: delete purchase (incl. received with stock reverse) with reason. */
    public function destroyPurchase(Request $request, PurchaseOrder $order)
    {
        SuperAdminAudit::abortUnlessSuperAdmin($request->user());
        $reason = SuperAdminAudit::requireReason($request);

        $number = (string) $order->number;
        $status = (string) $order->status;
        $meta = [
            'entity' => 'purchase_order',
            'order_number' => $number,
            'status' => $status,
            'grand_total' => (float) $order->grand_total,
        ];

        DB::connection('tenant')->transaction(function () use ($order, $status) {
            if ($status === 'received') {
                $this->reverseReceivedStock($order);
            } elseif (! in_array($status, ['rfq', 'confirmed', 'cancelled'], true)) {
                throw ValidationException::withMessages([
                    'admin_reason' => ["Cannot delete PO with status {$status}."],
                ]);
            }

            SyncAwareDelete::query(
                CreditLedger::query()->where('purchase_order_id', $order->id)
            );
            SyncAwareDelete::query(
                PurchaseOrderLine::query()->where('purchase_order_id', $order->id)
            );
            $order->delete();
        });

        SuperAdminAudit::log(
            SuperAdminAudit::TYPE_DELETE,
            $reason,
            null,
            $meta,
            "Deleted purchase order {$number}"
        );

        return redirect()
            ->route('purchases-demands.index', ['tab' => 'purchases'])
            ->with('status', "PO {$number} deleted.");
    }

    /** Super Admin: capture reason then open demand edit. */
    public function beginDemandEdit(Request $request, StockDemand $demand)
    {
        SuperAdminAudit::abortUnlessSuperAdmin($request->user());
        $reason = SuperAdminAudit::requireReason($request);

        $request->session()->put('super_admin_action', [
            'type' => SuperAdminAudit::TYPE_EDIT,
            'entity' => 'stock_demand',
            'id' => (int) $demand->id,
            'number' => (string) $demand->demand_no,
            'reason' => $reason,
        ]);

        return redirect()->route('demand.edit', $demand);
    }

    /** Super Admin: delete pending/partial demand with reason (no issued qty). */
    public function destroyDemand(Request $request, StockDemand $demand)
    {
        SuperAdminAudit::abortUnlessSuperAdmin($request->user());
        $reason = SuperAdminAudit::requireReason($request);

        $demand->load('lines');
        $hasIssued = $demand->lines->contains(fn (StockDemandLine $l) => (float) $l->issued_qty_base > 0);
        if ($hasIssued || $demand->status === 'issued') {
            throw ValidationException::withMessages([
                'admin_reason' => ['Issued demands cannot be deleted (stock already moved).'],
            ]);
        }

        $number = (string) $demand->demand_no;
        $meta = [
            'entity' => 'stock_demand',
            'order_number' => $number,
            'status' => (string) $demand->status,
        ];

        DB::connection('tenant')->transaction(function () use ($demand) {
            StockDemandLine::query()->where('demand_id', $demand->id)->delete();
            $demand->delete();
        });

        SuperAdminAudit::log(
            SuperAdminAudit::TYPE_DELETE,
            $reason,
            null,
            $meta,
            "Deleted demand {$number}"
        );

        return redirect()
            ->route('purchases-demands.index', ['tab' => 'demands'])
            ->with('status', "Demand {$number} deleted.");
    }

    private function reverseReceivedStock(PurchaseOrder $order): void
    {
        $order->load('lines');
        $warehouse = $this->inventoryStock->ensureWarehouse();

        foreach ($order->lines as $line) {
            /** @var InventoryProduct $product */
            $product = InventoryProduct::query()->lockForUpdate()->findOrFail($line->product_id);
            $factor = $product->factorToBaseForUom((string) $line->uom);
            if ($factor === null || $factor <= 0) {
                $factor = 1.0;
            }
            $qtyBase = round((float) $line->qty * $factor, 6);
            if ($qtyBase <= 0) {
                continue;
            }

            $before = (float) $product->qty_on_hand;
            $after = max(0.0, round($before - $qtyBase, 6));
            $product->update(['qty_on_hand' => $after]);

            $this->inventoryStock->removeStock(
                (int) $product->id,
                (int) $warehouse->id,
                $qtyBase,
                allowNegative: true
            );

            if (Schema::connection('tenant')->hasTable('inventory_cost_layers')) {
                InventoryCostLayer::query()
                    ->where('product_id', $product->id)
                    ->where('reference', $order->number)
                    ->delete();
                InventoryCostLayer::refreshProductUnitCost((int) $product->id);
            }

            if (Schema::connection('tenant')->hasTable('inventory_moves')) {
                InventoryMove::query()
                    ->where('product_id', $product->id)
                    ->where('reference', $order->number)
                    ->where('type', 'in')
                    ->delete();
            }
        }
    }
}
