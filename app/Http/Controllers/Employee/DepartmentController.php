<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\EmployeeDepartment;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = EmployeeDepartment::query()->orderBy('active', 'desc')->orderBy('name')->paginate(Setting::pageSize('employees_ref_per_page', 20));
        return view('employees.departments.index', compact('departments'));
    }

    public function create()
    {
        return view('employees.departments.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:tenant.employee_departments,name'],
            'active' => ['nullable', 'boolean'],
        ]);
        $data['active'] = (bool) ($data['active'] ?? false);
        EmployeeDepartment::create($data);
        return redirect()->route('employees.departments.index')->with('status', 'Department created.');
    }

    public function edit(EmployeeDepartment $department)
    {
        return view('employees.departments.edit', compact('department'));
    }

    public function update(Request $request, EmployeeDepartment $department)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('tenant.employee_departments', 'name')->ignore($department->id)],
            'active' => ['nullable', 'boolean'],
        ]);
        $data['active'] = (bool) ($data['active'] ?? false);
        $department->update($data);
        return redirect()->route('employees.departments.index')->with('status', 'Department updated.');
    }

    public function destroy(EmployeeDepartment $department)
    {
        $department->delete();
        return redirect()->route('employees.departments.index')->with('status', 'Department deleted.');
    }
}
