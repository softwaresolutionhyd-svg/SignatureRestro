<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\AppPasswordRules;
use App\Support\ModuleAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $cid = current_company_id();
        abort_if($cid === null, 403);

        $q = trim((string) $request->query('q', ''));
        $users = User::query()
            ->where('company_id', $cid)
            ->where('role', '!=', 'super_admin')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(Setting::pageSize('employees_per_page', 20))
            ->withQueryString();

        return view('admin.users.index', compact('users', 'q'));
    }

    public function edit(User $user)
    {
        $this->authorizeTenantUser($user);

        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeTenantUser($user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:200', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', 'in:company_admin,user'],
            'password' => AppPasswordRules::optionalConfirmed(),
            'permissions' => ['nullable', 'array'],
        ]);

        $adminCount = User::query()
            ->where('company_id', $user->company_id)
            ->where('role', 'company_admin')
            ->count();
        if ($user->role === 'company_admin' && $data['role'] === 'user' && $adminCount <= 1) {
            return redirect()->back()->withErrors('At least one company administrator is required.');
        }

        if ($user->id === $request->user()->id && $data['role'] === 'user') {
            return redirect()->back()->withErrors('You cannot remove your own administrator role.');
        }

        DB::transaction(function () use ($user, $data, $request) {
            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->role = $data['role'];
            if ($user->role === 'user') {
                $user->permissions = $this->normalizePermissions($request->input('permissions', []));
            } else {
                $user->permissions = [];
            }
            if (! empty($data['password'])) {
                $user->password = $data['password'];
                $user->must_change_password = false;
            }
            $user->save();
        });

        ActivityLogger::log('admin.user.updated', 'User account updated', $user, [
            'email' => $user->email,
            'role' => $user->role,
        ]);

        return redirect()->route('admin.users.index')->with('status', 'User updated.');
    }

    private function normalizePermissions(array $permissions): array
    {
        return ModuleAccess::normalize($permissions);
    }

    private function authorizeTenantUser(User $user): void
    {
        $cid = current_company_id();
        abort_if($cid === null || (int) $user->company_id !== (int) $cid || $user->role === 'super_admin', 403);
    }
}
