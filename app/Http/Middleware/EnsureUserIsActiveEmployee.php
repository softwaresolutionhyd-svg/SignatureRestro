<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActiveEmployee
{
    /**
     * Only users linked to an active employees row may use the app (after login).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        if ($request->routeIs('logout')) {
            return $next($request);
        }

        // Platform super admin and company admin may use the app without an employee row
        if (in_array($user->role ?? null, ['super_admin', 'company_admin', 'admin'], true)) {
            return $next($request);
        }

        $ok = Employee::query()
            ->where('user_id', $user->id)
            ->where('active', true)
            ->exists();

        if (!$ok) {
            if ($request->expectsJson()) {
                abort(403, 'Not an active employee.');
            }

            return response()->view('auth.no-employee', [], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
