<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiOrderTakerAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->bypassesModulePermissions()) {
            return $next($request);
        }

        $action = match (strtoupper($request->method())) {
            'GET', 'HEAD' => 'view',
            'POST' => 'create',
            'PUT', 'PATCH' => 'edit',
            'DELETE' => 'delete',
            default => 'view',
        };

        if (! $user->moduleAllows('order-taker', $action)) {
            return response()->json(['message' => 'Order Taker access denied.'], 403);
        }

        return $next($request);
    }
}
