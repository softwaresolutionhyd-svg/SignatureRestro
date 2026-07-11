<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeDepartment;
use App\Models\EmployeeDesignation;
use App\Models\Setting;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\AppPasswordRules;
use App\Support\LoginUsername;
use App\Support\ModuleAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $employeeNo = trim((string) $request->query('employee_no', ''));

        $employees = Employee::query()
            ->with(['department:id,name', 'designation:id,name', 'user:id,email'])
            ->when($employeeNo !== '', fn ($query) => $query->where('employee_no', 'like', "%{$employeeNo}%"))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('employee_no', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$q}%")
                            ->orWhere('email', 'like', LoginUsername::toStoredValue($q).'%'));
                });
            })
            ->orderBy('active', 'desc')
            ->orderBy('employee_no')
            ->paginate(Setting::pageSize('employees_per_page', 20))
            ->withQueryString();

        return view('employees.index', compact('employees', 'q', 'employeeNo'));
    }

    public function create()
    {
        $cid = current_company_id();
        abort_if($cid === null, 403);

        $departments = EmployeeDepartment::query()->where('active', true)->orderBy('name')->get(['id', 'name']);
        $designations = EmployeeDesignation::query()->where('active', true)->orderBy('name')->get(['id', 'name']);
        $employee = new Employee(['employee_no' => Employee::generateNextEmployeeNo($cid)]);
        return view('employees.create', compact('departments', 'designations', 'employee'));
    }

    public function store(Request $request)
    {
        $cid = current_company_id();
        abort_if($cid === null, 403);

        $data = $request->validate([
            'employee_no' => [
                'nullable',
                'string',
                'max:40',
                Rule::unique('tenant.employees', 'employee_no')->where(fn ($q) => $q->where('company_id', $cid)),
            ],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:200'],
            'phone' => ['nullable', 'string', 'max:60'],
            'department_id' => ['nullable', 'integer', 'exists:tenant.employee_departments,id'],
            'designation_id' => ['nullable', 'integer', 'exists:tenant.employee_designations,id'],
            'join_date' => ['nullable', 'date'],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'address' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],

            'account_username' => LoginUsername::rules(),
            'account_password' => AppPasswordRules::optionalConfirmed(),
            'permissions' => ['nullable', 'array'],
        ]);

        $data['active'] = (bool) ($data['active'] ?? false);
        $data['salary'] = $data['salary'] ?? 0;
        $data['department_id'] = isset($data['department_id']) && $data['department_id'] !== '' ? (int) $data['department_id'] : null;
        $data['designation_id'] = isset($data['designation_id']) && $data['designation_id'] !== '' ? (int) $data['designation_id'] : null;
        $data['employee_no'] = trim((string) ($data['employee_no'] ?? '')) !== ''
            ? trim((string) $data['employee_no'])
            : Employee::generateNextEmployeeNo($cid);

        $userId = null;
        $createdUser = null;
        if (! empty($data['account_username']) && ! empty($data['account_password'])) {
            $loginUsername = LoginUsername::toStoredValue($data['account_username']);
            $createdUser = User::create([
                'name' => $data['name'],
                'email' => $loginUsername,
                'password' => $data['account_password'],
                'role' => 'user',
                'company_id' => $cid,
                'permissions' => $this->normalizePermissions($request->input('permissions', [])),
                'must_change_password' => false,
            ]);
            $userId = $createdUser->id;
        }

        try {
            DB::connection('tenant')->transaction(function () use ($data, $request, $cid, $userId) {
                $emp = Employee::create([
                    'company_id' => $cid,
                    'user_id' => $userId,
                    'employee_no' => $data['employee_no'],
                    'name' => $data['name'],
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'department_id' => $data['department_id'],
                    'designation_id' => $data['designation_id'],
                    'join_date' => $data['join_date'] ?? null,
                    'salary' => $data['salary'],
                    'address' => $data['address'] ?? null,
                    'active' => $data['active'],
                ]);
                ActivityLogger::log('employee.created', 'Employee created', $emp);
            });
        } catch (\Throwable $e) {
            if ($createdUser) {
                $createdUser->delete();
            }
            throw $e;
        }
        return redirect()->route('employees.index')->with('status', 'Employee created.');
    }

    public function edit(Employee $employee)
    {
        $cid = current_company_id();
        abort_if($cid === null, 403);

        $employee->load(['user', 'department', 'designation']);
        $departments = EmployeeDepartment::query()->where('active', true)->orderBy('name')->get(['id', 'name']);
        $designations = EmployeeDesignation::query()->where('active', true)->orderBy('name')->get(['id', 'name']);
        return view('employees.edit', compact('employee', 'departments', 'designations'));
    }

    public function update(Request $request, Employee $employee)
    {
        $cid = current_company_id();
        abort_if($cid === null || (int) $employee->company_id !== (int) $cid, 403);

        $data = $request->validate([
            'employee_no' => [
                'required',
                'string',
                'max:40',
                Rule::unique('tenant.employees', 'employee_no')
                    ->where(fn ($q) => $q->where('company_id', $cid))
                    ->ignore($employee->id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:200'],
            'phone' => ['nullable', 'string', 'max:60'],
            'department_id' => ['nullable', 'integer', 'exists:tenant.employee_departments,id'],
            'designation_id' => ['nullable', 'integer', 'exists:tenant.employee_designations,id'],
            'join_date' => ['nullable', 'date'],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'address' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],

            'account_username' => LoginUsername::rules($employee->user_id),
            'account_password' => AppPasswordRules::optionalConfirmed(),
            'permissions' => ['nullable', 'array'],
        ]);

        $data['active'] = (bool) ($data['active'] ?? false);
        $data['salary'] = $data['salary'] ?? 0;
        $data['department_id'] = isset($data['department_id']) && $data['department_id'] !== '' ? (int) $data['department_id'] : null;
        $data['designation_id'] = isset($data['designation_id']) && $data['designation_id'] !== '' ? (int) $data['designation_id'] : null;

        $employee->load('user');
        $user = $employee->user;

        if (! empty($data['account_username'])) {
            $loginUsername = LoginUsername::toStoredValue($data['account_username']);
            if (! $user) {
                $usingDefaultPassword = empty($data['account_password']);
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $loginUsername,
                    'password' => $data['account_password'] ?: 'password123',
                    'role' => 'user',
                    'company_id' => $cid,
                    'permissions' => $this->normalizePermissions($request->input('permissions', [])),
                    'must_change_password' => $usingDefaultPassword,
                ]);
                $employee->user_id = $user->id;
            } else {
                $user->update([
                    'name' => $data['name'],
                    'email' => $loginUsername,
                    'permissions' => $this->normalizePermissions($request->input('permissions', $user->permissions ?? [])),
                ]);
            }
        }

        if ($user && ! empty($data['account_password'])) {
            $user->update([
                'password' => $data['account_password'],
                'must_change_password' => false,
            ]);
        }

        $employee->update([
            'employee_no' => $data['employee_no'],
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'department_id' => $data['department_id'],
            'designation_id' => $data['designation_id'],
            'join_date' => $data['join_date'] ?? null,
            'salary' => $data['salary'],
            'address' => $data['address'] ?? null,
            'active' => $data['active'],
            'user_id' => $employee->user_id,
        ]);
        ActivityLogger::log('employee.updated', 'Employee updated', $employee->fresh());
        return redirect()->route('employees.index')->with('status', 'Employee updated.');
    }

    public function resetPassword(Request $request, Employee $employee)
    {
        $cid = current_company_id();
        abort_if($cid === null || (int) $employee->company_id !== (int) $cid, 403);

        $employee->load('user');
        if (! $employee->user) {
            return redirect()->back()->withErrors('This employee has no login account.');
        }
        if (! in_array($employee->user->role ?? '', ['user'], true)) {
            return redirect()->back()->withErrors('Only staff (user role) passwords can be reset here. Use Users & roles for admins.');
        }

        $data = $request->validate([
            'password' => AppPasswordRules::requiredConfirmed(),
        ]);
        $employee->user->update([
            'password' => $data['password'],
            'must_change_password' => false,
        ]);

        ActivityLogger::log('employee.password_reset', 'Employee login password reset', $employee);

        return redirect()->back()->with('status', 'Password updated for '.LoginUsername::display($employee->user->email).'.');
    }

    public function destroy(Employee $employee)
    {
        $employee->load('user');
        if ($employee->user && in_array($employee->user->role ?? '', ['company_admin', 'super_admin', 'admin'], true)) {
            return redirect()->route('employees.index')->withErrors('This administrator employee record cannot be deleted.');
        }

        $employee->load('user');
        $user = $employee->user;
        ActivityLogger::log('employee.deleted', 'Employee deleted', null, [
            'employee_no' => $employee->employee_no,
            'name' => $employee->name,
        ]);
        $employee->delete();
        if ($user && ($user->role ?? null) === 'user') {
            $user->delete();
        }
        return redirect()->route('employees.index')->with('status', 'Employee deleted.');
    }

    private function normalizePermissions(array $permissions): array
    {
        return ModuleAccess::normalize($permissions);
    }
}
