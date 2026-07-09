<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryDepartment;
use App\Models\Setting;
use App\Services\InventoryStockService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function __construct(
        private readonly InventoryStockService $stockService
    ) {}

    public function index()
    {
        $this->stockService->ensureWarehouse();

        $departments = InventoryDepartment::query()
            ->withCount('catalogProducts')
            ->withSum('stocks as stock_qty', 'qty_on_hand')
            ->orderByDesc('is_warehouse')
            ->orderBy('active', 'desc')
            ->orderBy('name')
            ->paginate(Setting::pageSize('inventory_departments_per_page', 20));

        return view('inventory.departments.index', compact('departments'));
    }

    public function create()
    {
        return view('inventory.departments.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:tenant.inventory_departments,name'],
            'active' => ['nullable', 'boolean'],
        ]);
        $data['active'] = (bool) ($data['active'] ?? false);

        if (strcasecmp(trim($data['name']), 'warehouse') === 0) {
            return back()->withErrors(['name' => 'Warehouse naam se department manually nahi bana sakte — default warehouse pehle se maujood hai.'])->withInput();
        }

        $data['is_warehouse'] = false;

        InventoryDepartment::create($data);

        return redirect()->route('inventory.departments.index')->with('status', 'Department created.');
    }

    public function edit(InventoryDepartment $department)
    {
        abort_if($department->is_warehouse, 403, 'Warehouse department edit nahi ho sakta.');

        return view('inventory.departments.edit', compact('department'));
    }

    public function update(Request $request, InventoryDepartment $department)
    {
        if ($department->is_warehouse) {
            return back()->withErrors(['name' => 'Default Warehouse department edit nahi ho sakta.']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('tenant.inventory_departments', 'name')->ignore($department->id)],
            'active' => ['nullable', 'boolean'],
        ]);
        $data['active'] = (bool) ($data['active'] ?? false);

        $department->update($data);

        return redirect()->route('inventory.departments.index')->with('status', 'Department updated.');
    }

    public function destroy(InventoryDepartment $department)
    {
        if ($department->is_warehouse) {
            return redirect()->route('inventory.departments.index')->with('error', 'Warehouse department delete nahi ho sakta.');
        }

        $department->delete();

        return redirect()->route('inventory.departments.index')->with('status', 'Department deleted.');
    }
}
