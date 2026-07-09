<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanySelected
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if (($user->role ?? '') === 'super_admin') {
            if ($request->routeIs('logout')) {
                return $next($request);
            }

            $first = Company::query()->where('active', true)->orderBy('id')->first();
            if (! $first) {
                abort(503, 'No active company is configured. Add or activate a company in the database.');
            }

            session(['active_company_id' => (int) $first->id]);

            return $next($request);
        }

        if (in_array($user->role ?? '', ['company_admin', 'user'], true)) {
            if (! $user->company_id) {
                abort(403, 'This account is not linked to a company.');
            }
        }

        return $next($request);
    }
}
