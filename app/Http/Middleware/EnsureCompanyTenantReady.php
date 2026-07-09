<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyTenantReady
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }

        $companyId = null;
        if (($user->role ?? '') === 'super_admin') {
            $cid = session('active_company_id');
            $companyId = ($cid !== null && $cid !== '') ? (int) $cid : null;
        } elseif (in_array($user->role ?? '', ['company_admin', 'user'], true)) {
            $companyId = $user->company_id ? (int) $user->company_id : null;
        }

        if ($companyId === null) {
            return $next($request);
        }

        $company = Company::query()->find($companyId);
        if (! $company) {
            return $next($request);
        }

        // Shared DB: no separate tenant database — skip background-provision gates.
        if (! $company->database_name) {
            return $next($request);
        }

        if ($company->tenant_provision_failed_at) {
            return response()->view('companies.provision-failed', [
                'company' => $company,
            ]);
        }

        if (! $company->tenant_ready_at) {
            return response()->view('companies.provisioning', [
                'company' => $company,
            ]);
        }

        return $next($request);
    }
}
