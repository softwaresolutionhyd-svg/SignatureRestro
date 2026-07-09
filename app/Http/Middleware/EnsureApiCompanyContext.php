<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }

        $companyId = $this->resolveCompanyId($user);
        if ($companyId === null) {
            return response()->json(['message' => 'Company context unavailable.'], 403);
        }

        $request->attributes->set('api_company_id', $companyId);

        return $next($request);
    }

    private function resolveCompanyId(User $user): ?int
    {
        if (($user->role ?? '') === 'super_admin') {
            $first = Company::query()->where('active', true)->orderBy('id')->first();
            if (! $first) {
                return null;
            }

            return (int) $first->id;
        }

        if (in_array($user->role ?? '', ['company_admin', 'user', 'admin'], true)) {
            return $user->company_id ? (int) $user->company_id : null;
        }

        return $user->company_id ? (int) $user->company_id : null;
    }
}
