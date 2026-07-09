<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ConfigureTenantDatabase
{
    /**
     * Point the tenant connection at the same database as mysql (single-DB deployment).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() instanceof User) {
            return $next($request);
        }

        $landlordDb = (string) config('database.connections.mysql.database', '');
        $tenantDb = (string) config('database.connections.tenant.database', '');
        if ($tenantDb !== $landlordDb) {
            config(['database.connections.tenant.database' => $landlordDb]);
            DB::purge('tenant');
        }

        return $next($request);
    }
}
