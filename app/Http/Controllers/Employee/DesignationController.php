<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\EmployeeDesignation;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DesignationController extends Controller
{
    public function index()
    {
        $designations = EmployeeDesignation::query()->orderBy('active', 'desc')->orderBy('name')->paginate(Setting::pageSize('employees_ref_per_page', 20));
        return view('employees.designations.index', compact('designations'));
    }

    public function create()
    {
        return view('employees.designations.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:tenant.employee_designations,name'],
            'active' => ['nullable', 'boolean'],
        ]);
        $data['active'] = (bool) ($data['active'] ?? false);
        EmployeeDesignation::create($data);
        return redirect()->route('employees.designations.index')->with('status', 'Designation created.');
    }

    public function edit(EmployeeDesignation $designation)
    {
        return view('employees.designations.edit', compact('designation'));
    }

    public function update(Request $request, EmployeeDesignation $designation)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('tenant.employee_designations', 'name')->ignore($designation->id)],
            'active' => ['nullable', 'boolean'],
        ]);
        $data['active'] = (bool) ($data['active'] ?? false);
        $designation->update($data);
        return redirect()->route('employees.designations.index')->with('status', 'Designation updated.');
    }

    public function destroy(EmployeeDesignation $designation)
    {
        $designation->delete();
        return redirect()->route('employees.designations.index')->with('status', 'Designation deleted.');
    }
}
