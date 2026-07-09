<?php

namespace Database\Seeders;

use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\CreditLedger;
use App\Models\Employee;
use App\Models\EmployeeDepartment;
use App\Models\EmployeeDesignation;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryCategory;
use App\Models\InventoryCostLayer;
use App\Models\InventoryMove;
use App\Models\InventoryProduct;
use App\Models\InventoryProductUomConversion;
use App\Models\ManufacturingBom;
use App\Models\ManufacturingBomLine;
use App\Models\ManufacturingOrder;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PosPayment;
use App\Models\PosSession;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseVendor;
use App\Models\User;
use Illuminate\Database\Seeder;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        if (!$admin) {
            $this->command->warn('Admin user not found. Run DatabaseSeeder (or FixAdminEmployeeSeeder) first.');

            return;
        }

        $cid = (int) $admin->company_id;
        if ($cid < 1) {
            $this->command->warn('Admin user has no company_id. Run migrations and DatabaseSeeder first.');

            return;
        }

        if (InventoryProduct::where('company_id', $cid)->where('sku', 'DEMO-BEV-001')->exists()) {
            $this->command->info('Dummy catalog already seeded (SKU DEMO-BEV-001). Skipping DummyDataSeeder.');

            return;
        }

        $this->call(ExpenseCategorySeeder::class);

        $salesDept = EmployeeDepartment::firstOrCreate(['company_id' => $cid, 'name' => 'Sales'], ['active' => true]);
        $warehouseDept = EmployeeDepartment::firstOrCreate(['company_id' => $cid, 'name' => 'Warehouse'], ['active' => true]);
        $accountsDept = EmployeeDepartment::firstOrCreate(['company_id' => $cid, 'name' => 'Accounts'], ['active' => true]);

        $execDesig = EmployeeDesignation::firstOrCreate(['company_id' => $cid, 'name' => 'Executive'], ['active' => true]);
        $staffDesig = EmployeeDesignation::firstOrCreate(['company_id' => $cid, 'name' => 'Staff'], ['active' => true]);

        $catBev = InventoryCategory::firstOrCreate(['company_id' => $cid, 'name' => 'Beverages', 'parent_id' => null]);
        $catSnack = InventoryCategory::firstOrCreate(['company_id' => $cid, 'name' => 'Snacks', 'parent_id' => null]);
        $catRaw = InventoryCategory::firstOrCreate(['company_id' => $cid, 'name' => 'Raw Materials', 'parent_id' => null]);
        $catFinish = InventoryCategory::firstOrCreate(['company_id' => $cid, 'name' => 'Finished Goods', 'parent_id' => null]);

        $defs = [
            ['sku' => 'DEMO-BEV-001', 'barcode' => '8901000123456', 'name' => 'Cola 500ml', 'cat' => $catBev, 'uom' => 'pcs', 'cost' => 45, 'price' => 75, 'qty' => 240, 'reorder' => 50],
            ['sku' => 'DEMO-BEV-002', 'barcode' => '8901000123457', 'name' => 'Juice 1L', 'cat' => $catBev, 'uom' => 'pcs', 'cost' => 120, 'price' => 180, 'qty' => 80, 'reorder' => 20],
            ['sku' => 'DEMO-SN-001', 'barcode' => '8901000123458', 'name' => 'Potato Chips 50g', 'cat' => $catSnack, 'uom' => 'pcs', 'cost' => 35, 'price' => 55, 'qty' => 5, 'reorder' => 30],
            ['sku' => 'DEMO-RM-WHEAT', 'barcode' => null, 'name' => 'Wheat Flour 1kg', 'cat' => $catRaw, 'uom' => 'kg', 'cost' => 80, 'price' => 120, 'qty' => 500, 'reorder' => 0],
            ['sku' => 'DEMO-RM-SUGAR', 'barcode' => null, 'name' => 'Sugar 1kg', 'cat' => $catRaw, 'uom' => 'kg', 'cost' => 95, 'price' => 140, 'qty' => 200, 'reorder' => 0],
            ['sku' => 'DEMO-FG-CAKE', 'barcode' => '8901999000001', 'name' => 'Assorted Cake Box', 'cat' => $catFinish, 'uom' => 'box', 'cost' => 250, 'price' => 450, 'qty' => 40, 'reorder' => 10],
        ];

        $bySku = [];
        foreach ($defs as $def) {
            $p = InventoryProduct::create([
                'company_id' => $cid,
                'sku' => $def['sku'],
                'barcode' => $def['barcode'],
                'name' => $def['name'],
                'category_id' => $def['cat']->id,
                'uom' => $def['uom'],
                'cost' => $def['cost'],
                'price' => $def['price'],
                'qty_on_hand' => $def['qty'],
                'reorder_level' => $def['reorder'],
                'active' => true,
            ]);
            $bySku[$def['sku']] = $p;

            if ($def['sku'] === 'DEMO-RM-WHEAT') {
                InventoryProductUomConversion::query()->updateOrCreate(
                    ['product_id' => $p->id, 'uom' => 'g'],
                    ['company_id' => $cid, 'factor_to_base' => 0.001, 'active' => true]
                );
            }

            InventoryCostLayer::create([
                'company_id' => $cid,
                'product_id' => $p->id,
                'qty_remaining' => $p->qty_on_hand,
                'unit_cost' => $p->cost,
                'source' => 'opening',
                'reference' => 'DEMO-OPENING',
                'received_at' => now()->subDays(30),
            ]);
        }

        $v1 = PurchaseVendor::firstOrCreate(
            ['company_id' => $cid, 'name' => 'ABC Wholesale'],
            ['email' => 'orders@abcwholesale.test', 'phone' => '0300-1112233', 'active' => true]
        );
        $v2 = PurchaseVendor::firstOrCreate(
            ['company_id' => $cid, 'name' => 'Metro Traders'],
            ['email' => 'sales@metrotraders.test', 'phone' => '0321-4445566', 'active' => true]
        );

        $this->seedPurchaseOrder($cid, $admin, $v1, $bySku['DEMO-BEV-001'], 'DEMO-PO-RFQ1', 'rfq', false);
        $this->seedPurchaseOrder($cid, $admin, $v2, $bySku['DEMO-BEV-002'], 'DEMO-PO-CNF1', 'confirmed', true);
        $this->seedPurchaseOrder($cid, $admin, $v1, $bySku['DEMO-SN-001'], 'DEMO-PO-RCV1', 'received', true, true);

        $empSales = Employee::firstOrCreate(
            ['company_id' => $cid, 'employee_no' => 'DEMO-EMP-001'],
            [
                'company_id' => $cid,
                'name' => 'Ali Khan',
                'email' => 'ali.khan@demo.test',
                'phone' => '0300-2223344',
                'department_id' => $salesDept->id,
                'designation_id' => $staffDesig->id,
                'join_date' => now()->subYear()->toDateString(),
                'salary' => 85000,
                'address' => 'Lahore',
                'active' => true,
            ]
        );

        Employee::firstOrCreate(
            ['company_id' => $cid, 'employee_no' => 'DEMO-EMP-002'],
            [
                'company_id' => $cid,
                'name' => 'Sara Malik',
                'email' => 'sara.malik@demo.test',
                'phone' => '0321-5556677',
                'department_id' => $accountsDept->id,
                'designation_id' => $execDesig->id,
                'join_date' => now()->subMonths(6)->toDateString(),
                'salary' => 120000,
                'address' => 'Karachi',
                'active' => true,
            ]
        );

        $travelCat = ExpenseCategory::where('company_id', $cid)->where('name', 'Travel')->first()
            ?? ExpenseCategory::where('company_id', $cid)->where('active', true)->first();

        if ($travelCat && $empSales) {
            $this->seedExpense($cid, $admin, $empSales, $travelCat, 'Taxi to client site', 1, 2500, 0, Expense::STATUS_DRAFT, now()->subDays(2));
            $this->seedExpense($cid, $admin, $empSales, $travelCat, 'Intercity fuel', 1, 4200, 17, Expense::STATUS_SUBMITTED, now()->subDays(5));
        }

        $officeCat = ExpenseCategory::where('company_id', $cid)->where('name', 'Office Supplies')->first();
        $adminEmp = Employee::where('company_id', $cid)->where('employee_no', 'EMP-ADMIN-001')->first();
        if ($officeCat && $adminEmp) {
            $this->seedExpense($admin, $adminEmp, $officeCat, 'Printer paper & toner', 2, 1500, 17, Expense::STATUS_APPROVED, now()->subDays(10));
            $this->seedExpense($admin, $adminEmp, $officeCat, 'USB hubs for POS', 3, 800, 17, Expense::STATUS_PAID, now()->subDays(20));
        }

        Contact::firstOrCreate(
            ['company_id' => $cid, 'phone' => '0300-DEMO-001'],
            [
                'company_id' => $cid,
                'name' => 'Ahmed Traders',
                'email' => 'ahmed@demo.test',
                'address' => 'Shop 12, Main Bazaar',
                'city' => 'Faisalabad',
                'notes' => 'Demo credit customer',
                'active' => true,
            ]
        );

        Contact::firstOrCreate(
            ['company_id' => $cid, 'phone' => '0300-DEMO-002'],
            [
                'company_id' => $cid,
                'name' => 'Fatima General Store',
                'email' => null,
                'address' => null,
                'city' => 'Multan',
                'notes' => 'Walk-in wholesale',
                'active' => true,
            ]
        );

        $bom = ManufacturingBom::create([
            'company_id' => $cid,
            'finished_product_id' => $bySku['DEMO-FG-CAKE']->id,
            'name' => 'Standard Cake Box (demo)',
            'batch_qty' => 1,
            'active' => true,
            'notes' => 'Demo BoM — 1 box uses flour + sugar',
        ]);

        ManufacturingBomLine::create([
            'company_id' => $cid,
            'bom_id' => $bom->id,
            'component_product_id' => $bySku['DEMO-RM-WHEAT']->id,
            'qty' => 250,
            'uom' => 'g',
            'sort_order' => 1,
        ]);
        ManufacturingBomLine::create([
            'company_id' => $cid,
            'bom_id' => $bom->id,
            'component_product_id' => $bySku['DEMO-RM-SUGAR']->id,
            'qty' => 0.2,
            'uom' => 'kg',
            'sort_order' => 2,
        ]);

        $bom->refresh()->load(['lines.component.uomConversions']);
        $bom->syncFinishedProductStandardCost();

        ManufacturingOrder::create([
            'company_id' => $cid,
            'bom_id' => $bom->id,
            'user_id' => $admin->id,
            'qty_ordered' => 12,
            'status' => ManufacturingOrder::STATUS_DRAFT,
            'reference' => 'DEMO-MO-001',
            'note' => 'Demo draft production order',
        ]);

        CalendarEvent::create([
            'company_id' => $cid,
            'title' => 'Demo: Monthly stock count',
            'description' => 'Warehouse cycle count — seeded sample event',
            'location' => 'Main warehouse',
            'start_datetime' => now()->addDays(3)->setTime(10, 0),
            'end_datetime' => now()->addDays(3)->setTime(12, 0),
            'all_day' => false,
            'event_type' => 'task',
            'color' => CalendarEvent::$typeColors['task'] ?? '#0ea5e9',
            'created_by' => $admin->id,
        ]);

        $session = PosSession::create([
            'company_id' => $cid,
            'session_no' => 'REG-DEMO-DATA',
            'user_id' => $admin->id,
            'status' => 'closed',
            'opening_cash' => 5000,
            'closing_cash' => 5845.00,
            'expected_cash' => 5845.00,
            'cash_difference' => 0,
            'opened_at' => now()->subDay()->setTime(9, 0),
            'closed_at' => now()->subDay()->setTime(18, 0),
            'note' => 'Demo register session with sample sales',
        ]);

        $this->createCashPosOrder(
            $admin,
            $session,
            'POS-DEMO-001',
            [
                ['product' => $bySku['DEMO-BEV-001'], 'qty' => 2, 'price' => 75],
                ['product' => $bySku['DEMO-SN-001'], 'qty' => 1, 'price' => 55],
            ],
            220,
            15
        );

        $this->createCashPosOrder(
            $admin,
            $session,
            'POS-DEMO-002',
            [
                ['product' => $bySku['DEMO-BEV-002'], 'qty' => 1, 'price' => 180],
            ],
            200,
            20
        );

        $creditContact = Contact::where('company_id', $cid)->where('phone', '0300-DEMO-001')->first();
        if ($creditContact) {
            $order = PosOrder::create([
                'company_id' => $cid,
                'order_no' => 'POS-DEMO-CR-01',
                'session_id' => $session->id,
                'user_id' => $admin->id,
                'contact_id' => $creditContact->id,
                'is_credit' => true,
                'type' => 'sale',
                'status' => 'paid',
                'subtotal' => 450,
                'discount_total' => 0,
                'tax_total' => 0,
                'grand_total' => 450,
                'paid_at' => now()->subDay()->addHours(4),
            ]);

            $p = $bySku['DEMO-FG-CAKE'];
            $qty = 1.0;
            $lineSub = $qty * 450;
            PosOrderItem::create([
                'company_id' => $cid,
                'order_id' => $order->id,
                'product_id' => $p->id,
                'uom' => $p->uom,
                'qty' => $qty,
                'unit_price' => 450,
                'discount_percent' => 0,
                'tax_percent' => 0,
                'subtotal' => $lineSub,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total' => $lineSub,
            ]);

            $this->applyStockOut($admin, $p, $qty, $order->order_no);

            CreditLedger::create([
                'company_id' => $cid,
                'contact_id' => $creditContact->id,
                'type' => 'credit',
                'pos_order_id' => $order->id,
                'description' => 'POS Credit Sale — ' . $order->order_no,
                'amount' => 450,
                'balance_after' => 450,
                'entry_date' => now()->subDay()->toDateString(),
                'created_by' => $admin->id,
            ]);
        }

        $this->command->info('Dummy data seeded: demo products, purchase orders, employees, expenses, contacts, manufacturing, calendar, POS samples.');
    }

    private function seedPurchaseOrder(
        int $cid,
        User $admin,
        PurchaseVendor $vendor,
        InventoryProduct $product,
        string $number,
        string $status,
        bool $withLines,
        bool $received = false
    ): void {
        $po = PurchaseOrder::create([
            'company_id' => $cid,
            'number' => $number,
            'vendor_id' => $vendor->id,
            'created_by' => $admin->id,
            'status' => $status,
            'order_date' => now()->subDays(8)->toDateString(),
            'expected_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 0,
            'tax_total' => 0,
            'grand_total' => 0,
            'note' => 'Demo purchase order',
            'confirmed_at' => in_array($status, ['confirmed', 'received'], true) ? now()->subDays(6) : null,
            'received_at' => $received ? now()->subDays(2) : null,
        ]);

        if (!$withLines) {
            return;
        }

        $qty = 50;
        $unit = (float) $product->cost * 0.95;
        $lineSub = round($qty * $unit, 2);
        $taxPct = 17;
        $taxAmt = round($lineSub * ($taxPct / 100), 2);

        PurchaseOrderLine::create([
            'company_id' => $cid,
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'description' => $product->name,
            'uom' => $product->uom,
            'qty' => $qty,
            'unit_price' => $unit,
            'tax_percent' => $taxPct,
            'subtotal' => $lineSub,
            'tax_amount' => $taxAmt,
            'total' => $lineSub + $taxAmt,
        ]);

        $po->update([
            'subtotal' => $lineSub,
            'tax_total' => $taxAmt,
            'grand_total' => $lineSub + $taxAmt,
        ]);
    }

    private function seedExpense(
        int $cid,
        User $admin,
        Employee $employee,
        ExpenseCategory $category,
        string $description,
        float $qty,
        float $unitAmount,
        float $taxPercent,
        string $status,
        $expenseDate
    ): ?Expense {
        $e = new Expense([
            'company_id' => $cid,
            'employee_id' => $employee->id,
            'category_id' => $category->id,
            'description' => $description,
            'expense_date' => $expenseDate,
            'qty' => $qty,
            'unit_amount' => $unitAmount,
            'tax_percent' => $taxPercent,
            'notes' => 'Seeded demo expense',
            'status' => $status,
        ]);
        $e->recalculate();
        $e->save();

        if ($status === Expense::STATUS_SUBMITTED) {
            $e->update(['submitted_at' => now()->subDays(4)]);
        }
        if ($status === Expense::STATUS_APPROVED) {
            $e->update([
                'submitted_at' => now()->subDays(12),
                'approved_at' => now()->subDays(11),
                'approved_by' => $admin->id,
            ]);
        }
        if ($status === Expense::STATUS_PAID) {
            $e->update([
                'submitted_at' => now()->subDays(21),
                'approved_at' => now()->subDays(20),
                'approved_by' => $admin->id,
                'paid_at' => now()->subDays(18),
            ]);
        }

        return $e;
    }

    private function createCashPosOrder(
        User $admin,
        PosSession $session,
        string $orderNo,
        array $lines,
        float $cashTendered,
        float $cashChange
    ): void {
        $subtotal = 0.0;
        $taxTotal = 0.0;
        foreach ($lines as $ln) {
            $lineSub = (float) $ln['qty'] * (float) $ln['price'];
            $subtotal += $lineSub;
        }
        $grand = round($subtotal - 0 + $taxTotal, 2);

        $order = PosOrder::create([
            'company_id' => $session->company_id,
            'order_no' => $orderNo,
            'session_id' => $session->id,
            'user_id' => $admin->id,
            'contact_id' => null,
            'is_credit' => false,
            'type' => 'sale',
            'status' => 'paid',
            'subtotal' => round($subtotal, 2),
            'discount_total' => 0,
            'tax_total' => round($taxTotal, 2),
            'grand_total' => $grand,
            'cash_tendered' => $cashTendered,
            'cash_change' => $cashChange,
            'paid_at' => now()->subDay()->addHours(2),
        ]);

        foreach ($lines as $ln) {
            $p = $ln['product'];
            $qty = (float) $ln['qty'];
            $price = (float) $ln['price'];
            $lineSub = $qty * $price;
            PosOrderItem::create([
                'company_id' => $session->company_id,
                'order_id' => $order->id,
                'product_id' => $p->id,
                'uom' => $p->uom,
                'qty' => $qty,
                'unit_price' => $price,
                'discount_percent' => 0,
                'tax_percent' => 0,
                'subtotal' => $lineSub,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total' => $lineSub,
            ]);
            $this->applyStockOut($admin, $p, $qty, $orderNo);
        }

        PosPayment::create([
            'company_id' => $session->company_id,
            'order_id' => $order->id,
            'method' => 'cash',
            'amount' => $grand,
            'reference' => null,
        ]);
    }

    private function applyStockOut(User $admin, InventoryProduct $product, float $qtyUom, string $reference): void
    {
        $product->refresh();
        $qtyBase = $qtyUom;
        $factor = 1.0;
        $before = (float) $product->qty_on_hand;
        $after = $before - $qtyBase;
        $product->update(['qty_on_hand' => $after]);

        InventoryMove::create([
            'company_id' => $product->company_id,
            'product_id' => $product->id,
            'user_id' => $admin->id,
            'type' => 'out',
            'uom' => $product->uom,
            'qty' => $qtyBase,
            'qty_uom' => $qtyUom,
            'factor_to_base' => $factor,
            'unit_cost' => (float) $product->cost,
            'total_cost' => (float) $product->cost * $qtyBase,
            'qty_before' => $before,
            'qty_after' => $after,
            'reference' => $reference,
            'note' => 'Demo POS sale (seed)',
        ]);

        $remaining = $qtyBase;
        foreach (InventoryCostLayer::where('product_id', $product->id)->where('qty_remaining', '>', 0)->orderBy('received_at')->orderBy('id')->get() as $layer) {
            if ($remaining <= 0) {
                break;
            }
            $avail = (float) $layer->qty_remaining;
            $take = min($avail, $remaining);
            $layer->qty_remaining = $avail - $take;
            $layer->save();
            $remaining -= $take;
        }
    }
}
