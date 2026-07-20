<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hosting (SYNC_ROLE=cloud): view-only — block create / update / delete.
 * Local cafe sync push to /api/sync/push stays allowed.
 */
class EnsureCloudReadOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldEnforce()) {
            return $next($request);
        }

        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        if ($this->isAllowlisted($request)) {
            return $next($request);
        }

        $message = 'Online server view-only hai. Add / edit / delete sirf cafe (offline) PC se karein — changes sync se online aa jati hain.';

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $message, 'cloud_read_only' => true], 403);
        }

        return redirect()
            ->back()
            ->withInput()
            ->with('error', $message);
    }

    private function shouldEnforce(): bool
    {
        if (config('sync.role') !== 'cloud') {
            return false;
        }

        return (bool) config('sync.cloud_read_only', true);
    }

    private function isAllowlisted(Request $request): bool
    {
        $path = trim($request->path(), '/');

        $exact = [
            'login',
            'logout',
            'login/verify-totp',
            'request-password-reset',
            'api/login',
            'api/logout',
            'api/sync/ping',
            'api/sync/push',
            'api/sync/pull',
            'api/sync/pull-multi',
            'api/sync/pull-ids',
            'deploy/hooks/migrate',
        ];

        if (in_array($path, $exact, true)) {
            return true;
        }

        // Named route fallbacks (path may include locale / prefix edge cases)
        $route = $request->route()?->getName();
        $allowedRoutes = [
            'login',
            'logout',
            'logout.get',
            'login.verify-totp',
            'login.verify-totp.submit',
            'password-reset-request.create',
            'password-reset-request.store',
            'deploy.hooks.migrate',
        ];

        return $route !== null && in_array($route, $allowedRoutes, true);
    }
}
