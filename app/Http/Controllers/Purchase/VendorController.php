<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\PurchaseVendor;
use App\Models\Setting;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index()
    {
        $vendors = PurchaseVendor::query()->orderBy('name')->paginate(Setting::pageSize('purchase_vendors_per_page', 20));
        return view('purchase.vendors.index', compact('vendors'));
    }

    public function create()
    {
        return view('purchase.vendors.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'email' => ['nullable', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:60'],
            'tax_id' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
        ]);

        $data['active'] = (bool) ($data['active'] ?? false);
        PurchaseVendor::create($data);

        return redirect()->route('purchase.vendors.index')->with('status', 'Vendor created.');
    }

    public function edit(PurchaseVendor $vendor)
    {
        return view('purchase.vendors.edit', compact('vendor'));
    }

    public function update(Request $request, PurchaseVendor $vendor)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'email' => ['nullable', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:60'],
            'tax_id' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
        ]);

        $data['active'] = (bool) ($data['active'] ?? false);
        $vendor->update($data);

        return redirect()->route('purchase.vendors.index')->with('status', 'Vendor updated.');
    }

    public function destroy(PurchaseVendor $vendor)
    {
        $vendor->delete();
        return redirect()->route('purchase.vendors.index')->with('status', 'Vendor deleted.');
    }
}
