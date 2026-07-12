<?php

namespace App\Models;

use App\Support\ModuleAccess;
use App\Support\LoginUsername;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Support\Facades\Schema;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    protected $connection = 'mysql';

    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'permissions',
        'company_id',
        'must_change_password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'permissions' => 'array',
        'must_change_password' => 'boolean',
        'two_factor_secret' => 'encrypted',
        'two_factor_recovery_codes' => AsEncryptedArrayObject::class,
        'two_factor_confirmed_at' => 'datetime',
    ];

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null
            && filled($this->two_factor_secret);
    }

    /** Plain login username shown in UI (stored in email column). */
    public function loginUsername(): ?string
    {
        return LoginUsername::display($this->email);
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /** Platform operator: manages companies and enters any tenant via session. */
    public function isPlatformSuperAdmin(): bool
    {
        return ($this->role ?? null) === 'super_admin';
    }

    /** Company owner: full access inside their company (former "admin"). */
    public function isCompanyAdmin(): bool
    {
        return ($this->role ?? null) === 'company_admin';
    }

    /** @deprecated Use isCompanyAdmin() or bypassesModulePermissions() */
    public function isSuperAdmin(): bool
    {
        return $this->isPlatformSuperAdmin() || $this->isCompanyAdmin();
    }

    public function bypassesModulePermissions(): bool
    {
        return $this->isPlatformSuperAdmin() || $this->isCompanyAdmin();
    }

    public function moduleAllows(string $module, string $action): bool
    {
        if ($this->bypassesModulePermissions()) {
            return true;
        }
        if (! in_array($module, ModuleAccess::moduleKeys(), true)
            && ! in_array($module, ['employees'], true)) {
            return false;
        }

        foreach (ModuleAccess::permissionKeysFor($module) as $key) {
            $p = (array) data_get($this->permissions ?? [], $key, []);
            if (! empty($p['all'])) {
                return true;
            }
            if ((bool) ($p[$action] ?? false)) {
                return true;
            }
        }

        return false;
    }

    public function canViewModule(string $module): bool
    {
        return $this->moduleAllows($module, 'view');
    }

    /** True if at least one launcher tile should appear (besides Settings). */
    public function hasAnyModuleLauncherAccess(): bool
    {
        if ($this->bypassesModulePermissions()) {
            return true;
        }
        foreach (ModuleAccess::moduleKeys() as $m) {
            if ($this->canViewModule($m)) {
                return true;
            }
        }

        return false;
    }

    /** Any permission on for this module (used e.g. POS → contacts search/store). */
    public function touchesModule(string $module): bool
    {
        if ($this->bypassesModulePermissions()) {
            return true;
        }

        foreach (ModuleAccess::permissionKeysFor($module) as $key) {
            $p = (array) data_get($this->permissions ?? [], $key, []);
            if (! empty($p['all'])) {
                return true;
            }
            foreach ($p as $k => $v) {
                if ($k !== 'all' && $v) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Manager / owner designation — team attendance mark / change. */
    public function canManageTeamAttendance(): bool
    {
        return $this->hasManagerDesignationAccess();
    }

    /** Payroll view / print / mark paid — owner, admin, or manager designation. */
    public function canManagePayroll(): bool
    {
        return $this->hasManagerDesignationAccess();
    }

    private function hasManagerDesignationAccess(): bool
    {
        if ($this->bypassesModulePermissions()) {
            return true;
        }

        if ($this->hasExplicitPayrollPermission()) {
            return true;
        }

        $employee = $this->resolveEmployeeForAccess();
        if ($employee === null) {
            return false;
        }

        return $this->designationGrantsManagerAccess($employee);
    }

    private function hasExplicitPayrollPermission(): bool
    {
        foreach (['hr', 'employees'] as $key) {
            $p = (array) data_get($this->permissions ?? [], $key, []);
            if (! empty($p['all']) || ! empty($p['payroll']) || ! empty($p['manage_payroll'])) {
                return true;
            }
        }

        return false;
    }

    private function resolveEmployeeForAccess(): ?Employee
    {
        $employee = $this->relationLoaded('employee') ? $this->getRelation('employee') : null;
        if ($employee !== null) {
            return $employee;
        }

        $query = Employee::withoutGlobalScope('company')
            ->where('user_id', $this->id)
            ->where('active', true);

        if ($this->company_id) {
            $query->where('company_id', $this->company_id);
        }

        $employee = $query->first();
        if ($employee !== null) {
            return $employee;
        }

        $login = mb_strtolower(trim((string) $this->loginUsername()), 'UTF-8');
        if ($login === '') {
            return null;
        }

        $fallback = Employee::withoutGlobalScope('company')
            ->where('active', true)
            ->when($this->company_id, fn ($q) => $q->where('company_id', $this->company_id))
            ->where(function ($q) use ($login) {
                $q->whereRaw('LOWER(email) = ?', [$login])
                    ->orWhereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $this->name), 'UTF-8')]);
            })
            ->first();

        return $fallback;
    }

    private function designationGrantsManagerAccess(Employee $employee): bool
    {
        $employee->loadMissing('designation:id,name');
        $designation = mb_strtolower(trim((string) ($employee->designation?->name ?? '')), 'UTF-8');

        if ($designation !== '' && $this->designationNameGrantsManagerAccess($designation)) {
            return true;
        }

        if (Schema::connection('tenant')->hasColumn('employees', 'designation')) {
            $legacy = mb_strtolower(trim((string) $employee->getAttribute('designation')), 'UTF-8');
            if ($legacy !== '' && $this->designationNameGrantsManagerAccess($legacy)) {
                return true;
            }
        }

        return false;
    }

    private function designationNameGrantsManagerAccess(string $designation): bool
    {
        foreach (['manager', 'owner', 'supervis', 'proprietor', 'director'] as $keyword) {
            if (str_contains($designation, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
