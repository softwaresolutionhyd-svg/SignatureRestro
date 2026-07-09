<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryUnit;
use App\Models\InventoryUnitConversion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UomLibraryController extends Controller
{
    public function index(): View
    {
        $units = InventoryUnit::query()
            ->withCount(['conversionsFrom', 'conversionsTo'])
            ->orderBy('code')
            ->get();
        $conversions = InventoryUnitConversion::query()
            ->with(['fromUnit', 'toUnit'])
            ->orderBy('from_unit_id')
            ->orderBy('to_unit_id')
            ->get();

        return view('inventory.uom-library.index', compact('units', 'conversions'));
    }

    public function storeUnit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:120'],
        ]);
        $code = InventoryUnit::normalizeCode($data['code']);
        if ($code === '') {
            return back()->withErrors(['code' => 'Enter a valid code.'])->withInput();
        }
        if (InventoryUnit::query()->where('code', $code)->exists()) {
            return back()->withErrors(['code' => 'This code already exists (codes are case-insensitive).'])->withInput();
        }

        InventoryUnit::query()->create([
            'code' => $code,
            'name' => $data['name'],
        ]);

        return redirect()->route('inventory.uom-library.index')->with('status', 'Unit saved.');
    }

    public function destroyUnit(InventoryUnit $unit): RedirectResponse
    {
        if ($unit->conversionsFrom()->exists() || $unit->conversionsTo()->exists()) {
            return redirect()->route('inventory.uom-library.index')
                ->withErrors('Remove or reassign conversion rules that use this unit first.');
        }

        $unit->delete();

        return redirect()->route('inventory.uom-library.index')->with('status', 'Unit deleted.');
    }

    public function storeConversion(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from_unit_id' => ['required', 'integer', 'exists:tenant.inventory_units,id'],
            'to_unit_id' => ['required', 'integer', 'exists:tenant.inventory_units,id', 'different:from_unit_id'],
            'factor' => ['required', 'numeric', 'min:0.000000000001'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $exists = InventoryUnitConversion::query()
            ->where('from_unit_id', $data['from_unit_id'])
            ->where('to_unit_id', $data['to_unit_id'])
            ->exists();
        if ($exists) {
            return redirect()->route('inventory.uom-library.index')
                ->withErrors('A rule for this from → to pair already exists. Delete it first to replace.');
        }

        InventoryUnitConversion::query()->create($data);

        return redirect()->route('inventory.uom-library.index')->with('status', 'Conversion rule saved.');
    }

    public function destroyConversion(InventoryUnitConversion $conversion): RedirectResponse
    {
        $conversion->delete();

        return redirect()->route('inventory.uom-library.index')->with('status', 'Conversion rule deleted.');
    }
}
