<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->bypassesModulePermissions() || $user->receivesManagementNotifications()) {
            return $next($request);
        }

        if (in_array($user->role ?? null, ['super_admin', 'company_admin', 'admin'], true)) {
            return $next($request);
        }

        return response()->json(['message' => 'Admin panel access nahi hai.'], 403);
    }
}
