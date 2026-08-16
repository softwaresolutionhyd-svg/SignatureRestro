<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeStaffCategory;
use App\Models\EmployeeStaffSubCategory;
use App\Support\ActivityLogger;
use App\Support\EnsuresEmployeeStaffCategorySchema;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmployeeStaffCategoryController extends Controller
{
    use EnsuresEmployeeStaffCategorySchema;

    public function index()
    {
        abort_unless(auth()->user()?->moduleAllows('hr', 'view') || auth()->user()?->bypassesModulePermissions(), 403);

        $this->seedDefaultStaffCategories();

        $categories = EmployeeStaffCategory::query()
            ->with([
                'subCategories',
                'subCategories.employees' => fn ($q) => $q
                    ->orderBy('employee_no')
                    ->select('id', 'employee_no', 'name', 'designation_id', 'staff_category_id', 'staff_sub_category_id'),
                'subCategories.employees.designation:id,name',
                'employees' => fn ($q) => $q
                    ->whereNull('staff_sub_category_id')
                    ->orderBy('employee_no')
                    ->select('id', 'employee_no', 'name', 'designation_id', 'staff_category_id', 'staff_sub_category_id'),
                'employees.designation:id,name',
            ])
            ->orderBy('sort_order')
            ->get();

        $allEmployees = Employee::query()
            ->where('active', true)
            ->with(['designation:id,name', 'staffCategory:id,name', 'staffSubCategory:id,name'])
            ->orderBy('employee_no')
            ->get(['id', 'employee_no', 'name', 'designation_id', 'staff_category_id', 'staff_sub_category_id']);

        return view('employees.staff-categories-index', compact('categories', 'allEmployees'));
    }

    public function storeSubCategory(Request $request, EmployeeStaffCategory $staffCategory)
    {
        abort_unless(auth()->user()?->moduleAllows('hr', 'edit') || auth()->user()?->bypassesModulePermissions(), 403);
        $this->ensureEmployeeStaffCategorySchema();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $name = trim((string) $data['name']);
        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = 'sub-'.Str::random(6);
        }

        $exists = EmployeeStaffSubCategory::query()
            ->where('staff_category_id', $staffCategory->id)
            ->where('slug', $slug)
            ->exists();
        if ($exists) {
            $slug .= '-'.Str::lower(Str::random(4));
        }

        $maxSort = (int) EmployeeStaffSubCategory::query()
            ->where('staff_category_id', $staffCategory->id)
            ->max('sort_order');

        $sub = EmployeeStaffSubCategory::query()->create([
            'company_id' => $staffCategory->company_id ?? current_company_id(),
            'staff_category_id' => $staffCategory->id,
            'name' => $name,
            'slug' => $slug,
            'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : ($maxSort + 1),
        ]);

        ActivityLogger::log('staff_sub_category.created', 'Staff sub category created', $sub);

        return redirect()
            ->route('employees.staff-categories.index')
            ->with('status', $staffCategory->name.' — sub category "'.$sub->name.'" add ho gayi.');
    }

    public function destroySubCategory(EmployeeStaffCategory $staffCategory, EmployeeStaffSubCategory $subCategory)
    {
        abort_unless(auth()->user()?->moduleAllows('hr', 'edit') || auth()->user()?->bypassesModulePermissions(), 403);
        abort_unless((int) $subCategory->staff_category_id === (int) $staffCategory->id, 404);

        Employee::query()
            ->where('staff_sub_category_id', $subCategory->id)
            ->update(['staff_sub_category_id' => null]);

        $name = $subCategory->name;
        $subCategory->delete();

        ActivityLogger::log('staff_sub_category.deleted', 'Staff sub category deleted', null, [
            'name' => $name,
            'staff_category_id' => $staffCategory->id,
        ]);

        return redirect()
            ->route('employees.staff-categories.index')
            ->with('status', 'Sub category "'.$name.'" delete ho gayi.');
    }

    public function assignSubCategory(Request $request, EmployeeStaffCategory $staffCategory, EmployeeStaffSubCategory $subCategory)
    {
        abort_unless(auth()->user()?->moduleAllows('hr', 'edit') || auth()->user()?->bypassesModulePermissions(), 403);
        abort_unless((int) $subCategory->staff_category_id === (int) $staffCategory->id, 404);
        $this->ensureEmployeeStaffCategorySchema();

        $data = $request->validate([
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer', 'exists:tenant.employees,id'],
        ]);

        $ids = collect($data['employee_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();

        Employee::query()
            ->where('staff_sub_category_id', $subCategory->id)
            ->when($ids->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $ids))
            ->get()
            ->each(fn (Employee $employee) => $employee->update(['staff_sub_category_id' => null]));

        if ($ids->isNotEmpty()) {
            Employee::query()
                ->whereIn('id', $ids)
                ->get()
                ->each(fn (Employee $employee) => $employee->update([
                    'staff_category_id' => $staffCategory->id,
                    'staff_sub_category_id' => $subCategory->id,
                ]));
        }

        ActivityLogger::log('staff_sub_category.assigned', 'Employees assigned to staff sub category', $subCategory, [
            'count' => $ids->count(),
        ]);

        return redirect()
            ->route('employees.staff-categories.index')
            ->with('status', $subCategory->name.' — '.$ids->count().' employee(s) updated.');
    }

    public function removeSubCategoryEmployee(
        EmployeeStaffCategory $staffCategory,
        EmployeeStaffSubCategory $subCategory,
        Employee $employee
    ) {
        abort_unless(auth()->user()?->moduleAllows('hr', 'edit') || auth()->user()?->bypassesModulePermissions(), 403);
        abort_unless((int) $subCategory->staff_category_id === (int) $staffCategory->id, 404);

        if ((int) $employee->staff_sub_category_id === (int) $subCategory->id) {
            $employee->update(['staff_sub_category_id' => null]);
        }

        return redirect()
            ->route('employees.staff-categories.index')
            ->with('status', $employee->name.' removed from '.$subCategory->name.'.');
    }

    public function assign(Request $request, EmployeeStaffCategory $staffCategory)
    {
        abort_unless(auth()->user()?->moduleAllows('hr', 'edit') || auth()->user()?->bypassesModulePermissions(), 403);
        $this->ensureEmployeeStaffCategorySchema();

        $data = $request->validate([
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer', 'exists:tenant.employees,id'],
        ]);

        $ids = collect($data['employee_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();

        Employee::query()
            ->where('staff_category_id', $staffCategory->id)
            ->whereNull('staff_sub_category_id')
            ->when($ids->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $ids))
            ->get()
            ->each(fn (Employee $employee) => $employee->update([
                'staff_category_id' => null,
                'staff_sub_category_id' => null,
            ]));

        if ($ids->isNotEmpty()) {
            Employee::query()
                ->whereIn('id', $ids)
                ->get()
                ->each(function (Employee $employee) use ($staffCategory) {
                    $payload = ['staff_category_id' => $staffCategory->id];
                    if ((int) ($employee->staff_category_id ?? 0) !== (int) $staffCategory->id) {
                        $payload['staff_sub_category_id'] = null;
                    }
                    $employee->update($payload);
                });
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
            $employee->update([
                'staff_category_id' => null,
                'staff_sub_category_id' => null,
            ]);
        }

        return redirect()
            ->route('employees.staff-categories.index')
            ->with('status', $employee->name.' removed from '.$staffCategory->name.'.');
    }
}
