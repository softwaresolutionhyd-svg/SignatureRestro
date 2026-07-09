<?php

namespace App\Http\Controllers;

use App\Models\InventoryCategory;
use App\Models\InventoryMove;
use App\Models\InventoryProduct;
use App\Models\MaintenanceDemand;
use App\Models\MaintenanceDemandLine;
use App\Models\MaintenanceIssue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class MaintenanceController extends Controller
{
    private const LEGACY_LOCATIONS = ['Restaurant', 'Office'];
    private const LEGACY_CATEGORIES = ['Electrical', 'Plumbing', 'General'];

    public function index(Request $request)
    {
        $this->ensureMaintenanceTables();

        $category = $this->maintenanceCategory();
        $items = InventoryProduct::query()
            ->where('category_id', $category->id)
            ->where('active', true)
            ->orderBy('sku')
            ->with(['uomConversions' => fn ($q) => $q->where('active', true)->select(['id', 'product_id', 'uom', 'factor_to_base'])])
            ->get(['id', 'sku', 'name', 'uom', 'qty_on_hand', 'reorder_level', 'cost']);

        $demands = MaintenanceDemand::query()
            ->with(['lines.product:id,name,uom', 'creator:id,name'])
            ->latest('id')
            ->limit(20)
            ->get();

        $issues = MaintenanceIssue::query()
            ->with(['product:id,name,uom', 'issuer:id,name'])
            ->latest('id')
            ->limit(30)
            ->get();

        $lineLocations = DB::connection('tenant')
            ->table('maintenance_locations')
            ->whereNotIn('name', self::LEGACY_LOCATIONS)
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();
        $lineCategories = DB::connection('tenant')
            ->table('maintenance_categories')
            ->whereNotIn('name', self::LEGACY_CATEGORIES)
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();

        $kpis = [
            'items' => $items->count(),
            'on_hand_total' => (float) $items->sum('qty_on_hand'),
            'pending_demands' => $demands->whereIn('status', ['pending', 'partial'])->count(),
            'cost_held_items' => (float) $items->sum(fn ($i) => ((float) $i->qty_on_hand) * ((float) $i->cost)),
        ];

        return view('maintenance.index', compact('items', 'demands', 'issues', 'kpis', 'lineLocations', 'lineCategories'));
    }

    public function storeItem(Request $request)
    {
        $this->ensureMaintenanceTables();
        $category = $this->maintenanceCategory();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'uom' => ['required', 'string', 'max:30'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
        ]);

        InventoryProduct::query()->create([
            'category_id' => $category->id,
            'sku' => InventoryProduct::generateNextSku('MNT'),
            'name' => trim((string) $data['name']),
            'uom' => trim((string) $data['uom']),
            'cost' => (float) ($data['cost'] ?? 0),
            'price' => 0,
            'qty_on_hand' => 0,
            'reorder_level' => (float) ($data['reorder_level'] ?? 0),
            'active' => true,
            'for_pos' => false,
            'for_purchase' => true,
            'extra_costs' => [],
            'gas_charges' => 0,
            'service_charges' => 0,
            'profit' => 0,
        ]);

        return redirect()->route('maintenance.index')->with('status', 'Maintenance item added.');
    }

    public function storeDemand(Request $request)
    {
        $this->ensureMaintenanceTables();
        return $this->saveDemandPayload($request, null);
    }

    public function editDemand(Request $request, MaintenanceDemand $demand)
    {
        $this->ensureMaintenanceTables();
        if ($demand->status !== 'draft') {
            return redirect()->route('maintenance.index')->with('warning', 'Only draft demands can be edited.');
        }

        $category = $this->maintenanceCategory();
        $items = InventoryProduct::query()
            ->where('category_id', $category->id)
            ->where('active', true)
            ->orderBy('sku')
            ->with(['uomConversions' => fn ($q) => $q->where('active', true)->select(['id', 'product_id', 'uom', 'factor_to_base'])])
            ->get(['id', 'sku', 'name', 'uom', 'qty_on_hand', 'reorder_level', 'cost']);

        $lineLocations = DB::connection('tenant')
            ->table('maintenance_locations')
            ->whereNotIn('name', self::LEGACY_LOCATIONS)
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();
        $lineCategories = DB::connection('tenant')
            ->table('maintenance_categories')
            ->whereNotIn('name', self::LEGACY_CATEGORIES)
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();

        $demand->load(['lines.product:id,name,uom']);

        return view('maintenance.demand_edit', compact('demand', 'items', 'lineLocations', 'lineCategories'));
    }

    public function updateDemand(Request $request, MaintenanceDemand $demand)
    {
        $this->ensureMaintenanceTables();
        if ($demand->status !== 'draft') {
            return redirect()->route('maintenance.index')->with('warning', 'Only draft demands can be edited.');
        }

        return $this->saveDemandPayload($request, $demand);
    }

    private function saveDemandPayload(Request $request, ?MaintenanceDemand $existingDemand)
    {
        $data = $request->validate([
            'demand_date' => ['required', 'date'],
            'needed_date' => ['nullable', 'date', 'after_or_equal:demand_date'],
            'requested_by' => ['required', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
            'demand_category' => ['nullable', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['nullable', 'integer', 'exists:tenant.inventory_products,id'],
            'lines.*.custom_item_name' => ['nullable', 'string', 'max:200'],
            'lines.*.line_location' => ['required', 'string', 'max:120'],
            'lines.*.line_category' => ['required', 'string', 'max:80'],
            'lines.*.qty_uom' => ['required', 'numeric', 'min:0.001'],
            'lines.*.uom' => ['required', 'string', 'max:30'],
            'lines.*.expected_rate' => ['nullable', 'numeric', 'min:0'],
            'save_mode' => ['nullable', 'in:draft,final'],
        ]);
        $saveMode = (string) ($data['save_mode'] ?? 'final');
        $targetStatus = $saveMode === 'draft' ? 'draft' : 'pending';

        DB::connection('tenant')->transaction(function () use ($request, $data, $existingDemand, $targetStatus) {
            $firstLineProductId = isset($data['lines'][0]['product_id']) && $data['lines'][0]['product_id'] !== ''
                ? (int) $data['lines'][0]['product_id']
                : 0;
            $firstProductId = $firstLineProductId > 0
                ? $firstLineProductId
                : $this->customDemandPlaceholderProduct()->id;
            $demand = $existingDemand ?: new MaintenanceDemand();
            $demand->fill([
                'product_id' => $firstProductId,
                'requested_by' => trim((string) $data['requested_by']),
                'qty_uom' => 0,
                'uom' => '-',
                'qty_base' => 0,
                'status' => $targetStatus,
                'demand_date' => $data['demand_date'],
                'needed_date' => $data['needed_date'] ?? null,
                'location' => trim((string) ($data['location'] ?? data_get($data, 'lines.0.line_location', ''))),
                'demand_category' => trim((string) ($data['demand_category'] ?? data_get($data, 'lines.0.line_category', ''))),
                'note' => $data['note'] ?? null,
                'created_by' => $request->user()?->id,
            ]);
            $demand->save();

            if ($existingDemand) {
                $demand->lines()->delete();
            }

            $sumQtyUom = 0.0;
            $sumQtyBase = 0.0;
            foreach ((array) $data['lines'] as $line) {
                $productId = isset($line['product_id']) && $line['product_id'] !== '' ? (int) $line['product_id'] : 0;
                $customName = trim((string) ($line['custom_item_name'] ?? ''));
                $isCustom = $productId <= 0;
                if ($isCustom && $customName === '') {
                    abort(422, 'Custom item name is required when no inventory item is selected.');
                }

                $item = $isCustom
                    ? $this->customDemandPlaceholderProduct()
                    : $this->maintenanceItemQuery()->findOrFail($productId);
                $uom = trim((string) $line['uom']);
                $qtyUom = (float) $line['qty_uom'];
                $factor = 1.0;
                if (! $isCustom) {
                    $factor = (float) $item->factorToBaseForUom($uom);
                    if ($factor <= 0) {
                        abort(422, 'Selected UOM is not configured for item '.$item->name);
                    }
                }
                $qtyBase = $qtyUom * $factor;
                $expectedRate = (float) ($line['expected_rate'] ?? 0);
                $expectedTotal = $qtyUom * $expectedRate;

                MaintenanceDemandLine::query()->create([
                    'demand_id' => $demand->id,
                    'product_id' => $item->id,
                    'item_name' => $isCustom ? $customName : null,
                    'is_custom' => $isCustom,
                    'line_location' => trim((string) $line['line_location']),
                    'line_category' => trim((string) $line['line_category']),
                    'qty_uom' => $qtyUom,
                    'uom' => $uom,
                    'qty_base' => $qtyBase,
                    'expected_rate' => $expectedRate,
                    'expected_total' => $expectedTotal,
                    'received_qty_uom' => 0,
                    'received_qty_base' => 0,
                    'actual_rate' => 0,
                    'actual_total' => 0,
                ]);
                $sumQtyUom += $qtyUom;
                $sumQtyBase += $qtyBase;
            }

            $demand->update([
                'qty_uom' => $sumQtyUom,
                'qty_base' => $sumQtyBase,
            ]);
        });

        $message = $targetStatus === 'draft'
            ? 'Demand saved as draft.'
            : ($existingDemand ? 'Draft finalized and saved.' : 'Demand created.');

        return redirect()->route('maintenance.index')->with('status', $message);
    }

    public function receiveDemand(Request $request, MaintenanceDemand $demand)
    {
        $this->ensureMaintenanceTables();
        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_id' => ['required', 'integer', 'exists:tenant.maintenance_demand_lines,id'],
            'lines.*.received_qty_uom' => ['nullable', 'numeric', 'min:0'],
            'lines.*.actual_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::connection('tenant')->transaction(function () use ($request, $demand, $data) {
            $status = 'received';
            foreach ((array) $data['lines'] as $row) {
                /** @var MaintenanceDemandLine $line */
                $line = $demand->lines()->lockForUpdate()->findOrFail((int) $row['line_id']);
                $isCustom = (bool) $line->is_custom;
                $product = $isCustom
                    ? null
                    : $this->maintenanceItemQuery()->lockForUpdate()->findOrFail((int) $line->product_id);

                $newReceivedQtyUom = (float) ($row['received_qty_uom'] ?? 0);
                $oldReceivedQtyUom = (float) $line->received_qty_uom;
                if ($newReceivedQtyUom < $oldReceivedQtyUom) {
                    abort(422, 'Received quantity cannot be decreased.');
                }
                if ($newReceivedQtyUom > (float) $line->qty_uom) {
                    abort(422, 'Received quantity cannot exceed demanded quantity.');
                }

                $factor = 1.0;
                if (! $isCustom) {
                    $factor = (float) $product->factorToBaseForUom((string) $line->uom);
                    if ($factor <= 0) {
                        abort(422, 'UOM mapping missing for '.$product->name);
                    }
                }

                $newReceivedBase = $newReceivedQtyUom * $factor;
                $oldReceivedBase = (float) $line->received_qty_base;
                $deltaBase = $newReceivedBase - $oldReceivedBase;

                if ($deltaBase > 0 && $product) {
                    $before = (float) $product->qty_on_hand;
                    $after = $before + $deltaBase;
                    $product->update(['qty_on_hand' => $after]);

                    InventoryMove::query()->create([
                        'product_id' => $product->id,
                        'user_id' => $request->user()?->id,
                        'type' => 'in',
                        'qty' => $deltaBase,
                        'uom' => (string) $line->uom,
                        'qty_uom' => $newReceivedQtyUom - $oldReceivedQtyUom,
                        'factor_to_base' => $factor,
                        'unit_cost' => null,
                        'total_cost' => null,
                        'qty_before' => $before,
                        'qty_after' => $after,
                        'reference' => 'maintenance-demand-'.$demand->id,
                        'note' => 'Maintenance demand receive',
                    ]);
                }

                $actualRate = (float) ($row['actual_rate'] ?? $line->actual_rate ?? 0);
                $line->update([
                    'received_qty_uom' => $newReceivedQtyUom,
                    'received_qty_base' => $newReceivedBase,
                    'actual_rate' => $actualRate,
                    'actual_total' => $newReceivedQtyUom * $actualRate,
                ]);

                if ($newReceivedQtyUom <= 0) {
                    $status = 'pending';
                } elseif ($newReceivedQtyUom < (float) $line->qty_uom && $status !== 'pending') {
                    $status = 'partial';
                }
            }

            $demand->update(['status' => $status]);
        });

        return redirect()->route('maintenance.index')->with('status', 'Demand receive details updated.');
    }

    public function issue(Request $request)
    {
        $this->ensureMaintenanceTables();
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:tenant.inventory_products,id'],
            'issued_location' => ['required', 'string', 'max:120'],
            'issued_to' => ['required', 'string', 'max:120'],
            'qty_uom' => ['required', 'numeric', 'min:0.001'],
            'uom' => ['required', 'string', 'max:30'],
            'reference' => ['nullable', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        DB::connection('tenant')->transaction(function () use ($request, $data) {
            $item = $this->maintenanceItemQuery()->lockForUpdate()->findOrFail((int) $data['product_id']);
            $factor = $item->factorToBaseForUom((string) $data['uom']);
            if ($factor === null || $factor <= 0) {
                abort(422, 'Selected UOM is not configured for this item.');
            }

            $qtyBase = (float) $data['qty_uom'] * $factor;
            $before = (float) $item->qty_on_hand;
            if ($qtyBase > $before) {
                abort(422, 'Insufficient stock for maintenance issue.');
            }
            $after = $before - $qtyBase;
            $item->update(['qty_on_hand' => $after]);

            InventoryMove::query()->create([
                'product_id' => $item->id,
                'user_id' => $request->user()?->id,
                'type' => 'out',
                'qty' => $qtyBase,
                'uom' => (string) $data['uom'],
                'qty_uom' => (float) $data['qty_uom'],
                'factor_to_base' => $factor,
                'unit_cost' => null,
                'total_cost' => null,
                'qty_before' => $before,
                'qty_after' => $after,
                'reference' => $data['reference'] ?? 'maintenance-issue',
                'note' => 'Maintenance issue to '.$data['issued_to'].' @ '.$data['issued_location'].($data['note'] ? ' | '.$data['note'] : ''),
            ]);

            MaintenanceIssue::query()->create([
                'product_id' => $item->id,
                'issued_location' => trim((string) $data['issued_location']),
                'issued_to' => trim((string) $data['issued_to']),
                'qty_uom' => (float) $data['qty_uom'],
                'uom' => trim((string) $data['uom']),
                'qty_base' => $qtyBase,
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
                'issued_by' => $request->user()?->id,
            ]);
        });

        return redirect()->route('maintenance.index')->with('status', 'Item issued successfully.');
    }

    public function storeLocation(Request $request)
    {
        $this->ensureMaintenanceTables();
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $name = trim((string) $data['name']);
        DB::connection('tenant')->table('maintenance_locations')->updateOrInsert(
            ['name' => $name],
            ['name' => $name, 'updated_at' => now(), 'created_at' => now()]
        );

        return redirect()->route('maintenance.index')->with('status', 'Location added.');
    }

    public function storeCategory(Request $request)
    {
        $this->ensureMaintenanceTables();
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        $name = trim((string) $data['name']);
        DB::connection('tenant')->table('maintenance_categories')->updateOrInsert(
            ['name' => $name],
            ['name' => $name, 'updated_at' => now(), 'created_at' => now()]
        );

        return redirect()->route('maintenance.index')->with('status', 'Demand category added.');
    }

    public function setOpeningStock(Request $request)
    {
        $this->ensureMaintenanceTables();
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:tenant.inventory_products,id'],
            'qty_uom' => ['required', 'numeric', 'min:0'],
            'uom' => ['required', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        DB::connection('tenant')->transaction(function () use ($request, $data) {
            $item = $this->maintenanceItemQuery()->lockForUpdate()->findOrFail((int) $data['product_id']);
            $factor = (float) $item->factorToBaseForUom((string) $data['uom']);
            if ($factor <= 0) {
                abort(422, 'Selected UOM is not configured for this item.');
            }

            $qtyBase = (float) $data['qty_uom'] * $factor;
            $before = (float) $item->qty_on_hand;
            $after = $qtyBase;
            $item->update(['qty_on_hand' => $after]);

            InventoryMove::query()->create([
                'product_id' => $item->id,
                'user_id' => $request->user()?->id,
                'type' => $after >= $before ? 'in' : 'out',
                'qty' => abs($after - $before),
                'uom' => (string) $data['uom'],
                'qty_uom' => abs((float) $data['qty_uom'] - ($before / $factor)),
                'factor_to_base' => $factor,
                'unit_cost' => null,
                'total_cost' => null,
                'qty_before' => $before,
                'qty_after' => $after,
                'reference' => 'maintenance-opening-balance',
                'note' => 'Opening balance set'.($data['note'] ? ' | '.$data['note'] : ''),
            ]);
        });

        return redirect()->route('maintenance.index')->with('status', 'Opening stock updated.');
    }

    public function purgeAll(Request $request): RedirectResponse
    {
        $this->ensureMaintenanceTables();
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $category = InventoryCategory::query()
            ->whereRaw('LOWER(name) = ?', ['maintenance'])
            ->first();

        if ($category === null) {
            return redirect()->route('maintenance.index')->with('status', 'No maintenance data to delete.');
        }

        $productIds = InventoryProduct::query()
            ->where('category_id', $category->id)
            ->pluck('id')
            ->all();

        DB::connection('tenant')->transaction(function () use ($productIds) {
            $schema = Schema::connection('tenant');

            if ($schema->hasTable('maintenance_demand_lines')) {
                DB::connection('tenant')->table('maintenance_demand_lines')->delete();
            }

            if ($schema->hasTable('maintenance_demands')) {
                DB::connection('tenant')->table('maintenance_demands')->delete();
            }

            if ($schema->hasTable('maintenance_issues')) {
                DB::connection('tenant')->table('maintenance_issues')->delete();
            }

            if ($schema->hasTable('maintenance_locations')) {
                DB::connection('tenant')->table('maintenance_locations')->delete();
            }

            if ($schema->hasTable('maintenance_categories')) {
                DB::connection('tenant')->table('maintenance_categories')->delete();
            }

            if ($productIds !== [] && $schema->hasTable('inventory_product_uom_conversions')) {
                DB::connection('tenant')->table('inventory_product_uom_conversions')
                    ->whereIn('product_id', $productIds)
                    ->delete();
            }

            if ($productIds !== []) {
                InventoryProduct::query()->whereIn('id', $productIds)->delete();
            }
        });

        return redirect()->route('maintenance.index')->with('status', 'All maintenance items and entries deleted.');
    }

    private function maintenanceCategory(): InventoryCategory
    {
        $existing = InventoryCategory::query()
            ->whereRaw('LOWER(name) = ?', ['maintenance'])
            ->first();
        if ($existing) {
            return $existing;
        }

        return InventoryCategory::query()->create([
            'name' => 'Maintenance',
            'parent_id' => null,
        ]);
    }

    private function maintenanceItemQuery()
    {
        $categoryId = $this->maintenanceCategory()->id;

        return InventoryProduct::query()->where('category_id', $categoryId);
    }

    private function customDemandPlaceholderProduct(): InventoryProduct
    {
        $category = $this->maintenanceCategory();

        $existing = InventoryProduct::query()
            ->where('category_id', $category->id)
            ->where('sku', 'MNT-CUSTOM')
            ->first();
        if ($existing) {
            return $existing;
        }

        return InventoryProduct::query()->create([
            'category_id' => $category->id,
            'sku' => 'MNT-CUSTOM',
            'name' => 'Custom Maintenance Demand (Non-stock)',
            'uom' => 'unit',
            'cost' => 0,
            'price' => 0,
            'qty_on_hand' => 0,
            'reorder_level' => 0,
            'active' => false,
            'for_pos' => false,
            'for_purchase' => false,
            'extra_costs' => [],
            'gas_charges' => 0,
            'service_charges' => 0,
            'profit' => 0,
        ]);
    }

    private function ensureMaintenanceTables(): void
    {
        $schema = Schema::connection('tenant');

        if (! $schema->hasTable('maintenance_demands')) {
            $schema->create('maintenance_demands', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
                $table->string('requested_by', 120);
                $table->decimal('qty_uom', 14, 3);
                $table->string('uom', 30);
                $table->decimal('qty_base', 14, 3)->default(0);
                $table->string('status', 20)->default('pending');
                $table->date('demand_date')->nullable();
                $table->date('needed_date')->nullable();
                $table->string('location', 120)->nullable();
                $table->string('demand_category', 80)->nullable();
                $table->string('note', 255)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->index(['product_id', 'status']);
            });
        } else {
            $schema->table('maintenance_demands', function (Blueprint $table) use ($schema) {
                if (! $schema->hasColumn('maintenance_demands', 'demand_date')) {
                    $table->date('demand_date')->nullable()->after('status');
                }
                if (! $schema->hasColumn('maintenance_demands', 'needed_date')) {
                    $table->date('needed_date')->nullable()->after('demand_date');
                }
                if (! $schema->hasColumn('maintenance_demands', 'location')) {
                    $table->string('location', 120)->nullable()->after('needed_date');
                }
                if (! $schema->hasColumn('maintenance_demands', 'demand_category')) {
                    $table->string('demand_category', 80)->nullable()->after('location');
                }
            });
        }

        if (! $schema->hasTable('maintenance_demand_lines')) {
            $schema->create('maintenance_demand_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->foreignId('demand_id')->constrained('maintenance_demands')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
                $table->string('item_name', 200)->nullable();
                $table->boolean('is_custom')->default(false);
                $table->string('line_location', 120)->nullable();
                $table->string('line_category', 80)->nullable();
                $table->decimal('qty_uom', 14, 3);
                $table->string('uom', 30);
                $table->decimal('qty_base', 14, 3)->default(0);
                $table->decimal('expected_rate', 14, 2)->default(0);
                $table->decimal('expected_total', 14, 2)->default(0);
                $table->decimal('received_qty_uom', 14, 3)->default(0);
                $table->decimal('received_qty_base', 14, 3)->default(0);
                $table->decimal('actual_rate', 14, 2)->default(0);
                $table->decimal('actual_total', 14, 2)->default(0);
                $table->timestamps();
                $table->index(['demand_id', 'product_id']);
            });
        } else {
            $schema->table('maintenance_demand_lines', function (Blueprint $table) use ($schema) {
                if (! $schema->hasColumn('maintenance_demand_lines', 'item_name')) {
                    $table->string('item_name', 200)->nullable()->after('product_id');
                }
                if (! $schema->hasColumn('maintenance_demand_lines', 'is_custom')) {
                    $table->boolean('is_custom')->default(false)->after('item_name');
                }
                if (! $schema->hasColumn('maintenance_demand_lines', 'line_location')) {
                    $table->string('line_location', 120)->nullable()->after('is_custom');
                }
                if (! $schema->hasColumn('maintenance_demand_lines', 'line_category')) {
                    $table->string('line_category', 80)->nullable()->after('line_location');
                }
            });
        }

        if (! $schema->hasTable('maintenance_issues')) {
            $schema->create('maintenance_issues', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
                $table->string('issued_location', 120)->nullable();
                $table->string('issued_to', 120);
                $table->decimal('qty_uom', 14, 3);
                $table->string('uom', 30);
                $table->decimal('qty_base', 14, 3)->default(0);
                $table->string('reference', 80)->nullable();
                $table->string('note', 255)->nullable();
                $table->unsignedBigInteger('issued_by')->nullable();
                $table->timestamps();
                $table->index(['product_id', 'created_at']);
            });
        } else {
            $schema->table('maintenance_issues', function (Blueprint $table) use ($schema) {
                if (! $schema->hasColumn('maintenance_issues', 'issued_location')) {
                    $table->string('issued_location', 120)->nullable()->after('product_id');
                }
            });
        }

        if (! $schema->hasTable('maintenance_locations')) {
            $schema->create('maintenance_locations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('name', 120)->unique();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('maintenance_categories')) {
            $schema->create('maintenance_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('name', 80)->unique();
                $table->timestamps();
            });
        }
    }
}

