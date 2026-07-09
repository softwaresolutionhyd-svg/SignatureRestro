<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ModuleAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasModuleAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        // Full access roles bypass module matrix
        if (in_array($user->role ?? null, ['super_admin', 'company_admin', 'admin'], true)) {
            return $next($request);
        }

        $routeName = (string) ($request->route()?->getName() ?? '');
        $method = strtoupper((string) $request->method());

        // POS customer picker: contacts search/create without full Contacts module
        if (($routeName === 'contacts.search' && in_array($method, ['GET', 'HEAD'], true))
            || ($routeName === 'contacts.store' && $method === 'POST')) {
            if ($user instanceof User && $user->touchesModule('restaurant-pos')) {
                return $next($request);
            }
        }

        // Company updates / optional feature install — not part of module matrix
        if (str_starts_with($routeName, 'updates.')) {
            return $next($request);
        }

        $module = (string) $request->route()->parameter('module', '');
        if ($module === '') {
            $module = Str::before($routeName, '.');
        }

        $module = ModuleAccess::permissionModuleKey($module);

        if ($module === '') {
            return $next($request);
        }

        $permissions = (array) ($user->permissions ?? []);

        // Non-admin users must have at least one allowed action somewhere
        if (($user->role ?? null) === 'user' && !$this->permissionsHasAnyAllowed($permissions)) {
            abort(403, 'No module access configured for this user.');
        }

        $action = $this->resolveAction($request);

        foreach (ModuleAccess::permissionKeysFor($module) as $permKey) {
            $modPerm = (array) ($permissions[$permKey] ?? []);
            if (! empty($modPerm['all'])) {
                return $next($request);
            }

            if ((bool) ($modPerm[$action] ?? false)) {
                return $next($request);
            }
        }

        abort(403);
    }

    private function resolveAction(Request $request): string
    {
        $method = strtoupper((string) $request->method());
        $routeName = (string) ($request->route()?->getName() ?? '');

        if (in_array($method, ['GET', 'HEAD'], true)) {
            return 'view';
        }

        if ($method === 'DELETE') {
            return 'delete';
        }

        if (in_array($method, ['PUT', 'PATCH'], true)) {
            return 'edit';
        }

        // POST
        if (Str::endsWith($routeName, '.store')) {
            return 'create';
        }

        if (Str::endsWith($routeName, '.destroy')) {
            return 'delete';
        }

        if (Str::endsWith($routeName, '.toggle-star')) {
            return 'edit';
        }

        if (Str::contains($routeName, '.orders.') && Str::endsWith($routeName, '.confirm')) {
            return 'edit';
        }

        if ($routeName === 'inventory.stock-in.receive' && $method === 'POST') {
            return 'create';
        }

        if ($routeName === 'inventory.stock-check.submit' && $method === 'POST') {
            return 'create';
        }

        if ($routeName === 'manufacturing.orders.complete') {
            return 'edit';
        }

        if (Str::startsWith($routeName, 'restaurant-pos.')) {
            if (Str::endsWith($routeName, '.checkout') || Str::endsWith($routeName, '.hold')) {
                return 'create';
            }
            if (Str::endsWith($routeName, '.cash-movement') || Str::endsWith($routeName, '.session.open') || Str::endsWith($routeName, '.session.close')) {
                return 'edit';
            }
        }

        return 'edit';
    }

    private function permissionsHasAnyAllowed(array $permissions): bool
    {
        foreach ($permissions as $actions) {
            foreach ((array) $actions as $allowed) {
                if ($allowed) {
                    return true;
                }
            }
        }

        return false;
    }
}
