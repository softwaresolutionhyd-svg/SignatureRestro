<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeDesignation;
use App\Models\EmployeeStaffCategory;
use App\Models\Setting;
use App\Models\User;
use App\Services\EmployeeContactSyncService;
use App\Services\EmployeePhotoService;
use App\Services\QrAttendanceService;
use App\Support\ActivityLogger;
use App\Support\AppPasswordRules;
use App\Support\EnsuresEmployeePhotoSchema;
use App\Support\EnsuresEmployeeProfileSchema;
use App\Support\EnsuresEmployeeStaffCategorySchema;
use App\Support\LoginUsername;
use App\Support\ModuleAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmployeeController extends Controller
{
    use EnsuresEmployeePhotoSchema;
    use EnsuresEmployeeProfileSchema;
    use EnsuresEmployeeStaffCategorySchema;

    public function __construct(
        private readonly EmployeeContactSyncService $contactSync,
        private readonly EmployeePhotoService $photos,
        private readonly QrAttendanceService $qrAttendance,
    ) {}
    public function index(Request $request)
    {
        $this->ensureEmployeePhotoSchema();
        $this->ensureEmployeeProfileSchema();
        Employee::ensureQrTokenSchema();

        $q = trim((string) $request->query('q', ''));
        $employeeNo = trim((string) $request->query('employee_no', ''));
        $sort = strtolower(trim((string) $request->query('sort', '')));
        if (! in_array($sort, ['name_az', 'name_za', ''], true)) {
            $sort = '';
        }

        $employees = Employee::query()
            ->excludeAdminAccounts()
            ->with(['designation:id,name', 'user:id,email'])
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
            });

        if ($sort === 'name_az') {
            $employees->orderByRaw('LOWER(name) asc')->orderBy('employee_no');
        } elseif ($sort === 'name_za') {
            $employees->orderByRaw('LOWER(name) desc')->orderBy('employee_no');
        } else {
            $employees->orderBy('active', 'desc')->orderBy('employee_no');
        }

        $employees = $employees
            ->paginate(Setting::pageSize('employees_per_page', 20))
            ->withQueryString();

        return view('employees.index', compact('employees', 'q', 'employeeNo', 'sort'));
    }

    public function create()
    {
        $this->ensureEmployeePhotoSchema();
        $this->ensureEmployeeProfileSchema();
        Employee::ensureQrTokenSchema();
        $cid = current_company_id();
        abort_if($cid === null, 403);

        $designations = EmployeeDesignation::query()->where('active', true)->orderBy('name')->get(['id', 'name']);
        $staffCategories = $this->staffCategoriesForForm($cid);
        $employee = new Employee(['employee_no' => Employee::generateNextEmployeeNo($cid)]);

        return view('employees.create', compact('designations', 'staffCategories', 'employee'));
    }

    public function store(Request $request)
    {
        $this->ensureEmployeePhotoSchema();
        $this->ensureEmployeeProfileSchema();
        Employee::ensureQrTokenSchema();
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
            'designation_id' => ['nullable', 'integer', 'exists:tenant.employee_designations,id'],
            'staff_category_id' => ['nullable', 'integer', 'exists:tenant.employee_staff_categories,id'],
            'join_date' => ['nullable', 'date'],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'father_name' => ['nullable', 'string', 'max:150'],
            'cnic' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'active' => ['nullable', 'boolean'],

            'account_username' => LoginUsername::rules(),
            'account_password' => AppPasswordRules::optionalConfirmed(),
            'permissions' => ['nullable', 'array'],
        ]);

        $data['active'] = (bool) ($data['active'] ?? false);
        $data['salary'] = $data['salary'] ?? 0;
        $data['designation_id'] = isset($data['designation_id']) && $data['designation_id'] !== '' ? (int) $data['designation_id'] : null;
        $data['staff_category_id'] = isset($data['staff_category_id']) && $data['staff_category_id'] !== '' ? (int) $data['staff_category_id'] : null;
        $data['employee_no'] = trim((string) ($data['employee_no'] ?? '')) !== ''
            ? trim((string) $data['employee_no'])
            : Employee::generateNextEmployeeNo($cid);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $this->photos->storePassport($request->file('photo'));
        }

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
            $this->assertEmployeeUserAccountAvailable($cid, $userId);
        }

        $emp = null;
        try {
            DB::connection('tenant')->transaction(function () use ($data, $cid, $userId, $photoPath, &$emp) {
                $emp = Employee::create([
                    'company_id' => $cid,
                    'user_id' => $userId,
                    'employee_no' => $data['employee_no'],
                    'name' => $data['name'],
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'designation_id' => $data['designation_id'],
                    'staff_category_id' => $data['staff_category_id'],
                    'join_date' => $data['join_date'] ?? null,
                    'salary' => $data['salary'],
                    'father_name' => $data['father_name'] ?? null,
                    'cnic' => $data['cnic'] ?? null,
                    'address' => $data['address'] ?? null,
                    'city' => $data['city'] ?? null,
                    'district' => $data['district'] ?? null,
                    'photo_path' => $photoPath,
                    'active' => $data['active'],
                ]);
                $this->contactSync->ensureContactForEmployee($emp);
                ActivityLogger::log('employee.created', 'Employee created', $emp);
            });
        } catch (\Throwable $e) {
            if ($photoPath) {
                $this->photos->delete($photoPath);
            }
            if ($createdUser) {
                $createdUser->delete();
            }
            throw $e;
        }

        return redirect()
            ->route('employees.edit', $emp)
            ->with('status', 'Employee created. QR card print kar sakte hain.');
    }

    public function edit(Employee $employee)
    {
        $this->ensureEmployeePhotoSchema();
        $this->ensureEmployeeProfileSchema();
        $cid = current_company_id();
        abort_if($cid === null, 403);

        $employee->load(['user', 'designation', 'staffCategory']);
        $employee->ensureQrToken();
        $designations = EmployeeDesignation::query()->where('active', true)->orderBy('name')->get(['id', 'name']);
        $staffCategories = $this->staffCategoriesForForm($cid);
        $qrSvg = $this->qrAttendance->svgForEmployee($employee, 200);

        return view('employees.edit', compact('employee', 'designations', 'staffCategories', 'qrSvg'));
    }

    public function update(Request $request, Employee $employee)
    {
        $this->ensureEmployeePhotoSchema();
        $this->ensureEmployeeProfileSchema();
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
            'designation_id' => ['nullable', 'integer', 'exists:tenant.employee_designations,id'],
            'staff_category_id' => ['nullable', 'integer', 'exists:tenant.employee_staff_categories,id'],
            'join_date' => ['nullable', 'date'],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'father_name' => ['nullable', 'string', 'max:150'],
            'cnic' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'remove_photo' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],

            'account_username' => LoginUsername::rules($employee->user_id),
            'account_password' => AppPasswordRules::optionalConfirmed(),
            'permissions' => ['nullable', 'array'],
        ]);

        $data['active'] = (bool) ($data['active'] ?? false);
        $data['salary'] = $data['salary'] ?? 0;
        $data['designation_id'] = isset($data['designation_id']) && $data['designation_id'] !== '' ? (int) $data['designation_id'] : null;
        $data['staff_category_id'] = isset($data['staff_category_id']) && $data['staff_category_id'] !== '' ? (int) $data['staff_category_id'] : null;

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
                $this->assertEmployeeUserAccountAvailable($cid, $user->id, $employee->id);
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

        $photoPath = $employee->photo_path;
        if ($request->boolean('remove_photo')) {
            $this->photos->delete($photoPath);
            $photoPath = null;
        }

        if ($request->hasFile('photo')) {
            $this->photos->delete($employee->photo_path);
            $photoPath = $this->photos->storePassport($request->file('photo'));
        }

        $employee->update([
            'employee_no' => $data['employee_no'],
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'designation_id' => $data['designation_id'],
            'staff_category_id' => $data['staff_category_id'],
            'join_date' => $data['join_date'] ?? null,
            'salary' => $data['salary'],
            'father_name' => $data['father_name'] ?? null,
            'cnic' => $data['cnic'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'district' => $data['district'] ?? null,
            'photo_path' => $photoPath,
            'active' => $data['active'],
            'user_id' => $employee->user_id,
        ]);
        $this->contactSync->ensureContactForEmployee($employee->fresh());
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

    public function destroyLoginAccount(Employee $employee)
    {
        $cid = current_company_id();
        abort_if($cid === null || (int) $employee->company_id !== (int) $cid, 403);

        $employee->load('user');
        $user = $employee->user;

        if ($user === null) {
            return redirect()->back()->withErrors('Is employee ki koi login account nahi hai.');
        }

        if (! in_array($user->role ?? '', ['user'], true)) {
            return redirect()->back()->withErrors('Admin / company admin account yahan se delete nahi ho sakti. Users & roles use karein.');
        }

        if ((int) auth()->id() === (int) $user->id) {
            return redirect()->back()->withErrors('Apni khud ki login account yahan se delete nahi kar sakte.');
        }

        $username = LoginUsername::display($user->email);

        $employee->forceFill(['user_id' => null])->save();
        $user->delete();

        ActivityLogger::log('employee.login_account_deleted', 'Employee login account deleted', $employee->fresh(), [
            'username' => $username,
            'employee_no' => $employee->employee_no,
        ]);

        return redirect()
            ->route('employees.edit', $employee)
            ->with('status', "Login account \"{$username}\" delete ho gayi. Employee record same hai.");
    }

    public function destroy(Employee $employee)
    {
        $employee->load('user');
        if ($employee->user && in_array($employee->user->role ?? '', ['company_admin', 'super_admin', 'admin'], true)) {
            return redirect()->route('employees.index')->withErrors('This administrator employee record cannot be deleted.');
        }

        $employee->load('user');
        $user = $employee->user;
        $photoPath = $employee->photo_path;
        ActivityLogger::log('employee.deleted', 'Employee deleted', null, [
            'employee_no' => $employee->employee_no,
            'name' => $employee->name,
        ]);
        $employee->delete();
        $this->photos->delete($photoPath);
        if ($user && ($user->role ?? null) === 'user') {
            $user->delete();
        }
        return redirect()->route('employees.index')->with('status', 'Employee deleted.');
    }

    private function normalizePermissions(array $permissions): array
    {
        return ModuleAccess::normalize($permissions);
    }

    private function assertEmployeeUserAccountAvailable(int $companyId, int $userId, ?int $ignoreEmployeeId = null): void
    {
        $query = Employee::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId);

        if ($ignoreEmployeeId !== null) {
            $query->where('id', '!=', $ignoreEmployeeId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'account_username' => ['Ye login account pehle se kisi aur employee se linked hai.'],
            ]);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, EmployeeStaffCategory>
     */
    private function staffCategoriesForForm(int $companyId)
    {
        $this->seedDefaultStaffCategories($companyId);

        return EmployeeStaffCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
