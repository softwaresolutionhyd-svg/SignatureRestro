<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeStaffCategory;
use App\Support\ActivityLogger;
use App\Support\EnsuresEmployeeStaffCategorySchema;
use Illuminate\Http\Request;

class EmployeeStaffCategoryController extends Controller
{
    use EnsuresEmployeeStaffCategorySchema;

    public function index()
    {
        abort_unless(auth()->user()?->moduleAllows('hr', 'view') || auth()->user()?->bypassesModulePermissions(), 403);

        $this->seedDefaultStaffCategories();

        $categories = EmployeeStaffCategory::query()
            ->with(['employees' => fn ($q) => $q->orderBy('employee_no')->select('id', 'employee_no', 'name', 'designation_id', 'staff_category_id')])
            ->with(['employees.designation:id,name'])
            ->orderBy('sort_order')
            ->get();

        $allEmployees = Employee::query()
            ->where('active', true)
            ->with(['designation:id,name', 'staffCategory:id,name'])
            ->orderBy('employee_no')
            ->get(['id', 'employee_no', 'name', 'designation_id', 'staff_category_id']);

        return view('employees.staff-categories-index', compact('categories', 'allEmployees'));
    }

    public function assign(Request $request, EmployeeStaffCategory $staffCategory)
    {
        abort_unless(auth()->user()?->moduleAllows('hr', 'edit') || auth()->user()?->bypassesModulePermissions(), 403);

        $data = $request->validate([
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer', 'exists:tenant.employees,id'],
        ]);

        $ids = collect($data['employee_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();

        Employee::query()
            ->where('staff_category_id', $staffCategory->id)
            ->when($ids->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $ids))
            ->get()
            ->each(fn (Employee $employee) => $employee->update(['staff_category_id' => null]));

        if ($ids->isNotEmpty()) {
            Employee::query()
                ->whereIn('id', $ids)
                ->get()
                ->each(fn (Employee $employee) => $employee->update(['staff_category_id' => $staffCategory->id]));
        }

        ActivityLogger::log('staff_category.assigned', 'Employees assigned to staff category', $staffCategory, [
            'count' => $ids->count(),
        ]);

        return redirect()
            ->route('employees.staff-categories.index')
            ->with('status', $staffCategory->name.' — '.$ids->count().' employee(s) updated.');
    }

    public function removeEmployee(EmployeeStaffCategory $staffCategory, Employee $employee)
    {
        abort_unless(auth()->user()?->moduleAllows('hr', 'edit') || auth()->user()?->bypassesModulePermissions(), 403);

        if ((int) $employee->staff_category_id === (int) $staffCategory->id) {
            $employee->update(['staff_category_id' => null]);
        }

        return redirect()
            ->route('employees.staff-categories.index')
            ->with('status', $employee->name.' removed from '.$staffCategory->name.'.');
    }
}
