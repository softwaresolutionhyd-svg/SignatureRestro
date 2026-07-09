<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * Force users with a temporary / reset password to set a new one before using the app.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! ($user->must_change_password ?? false)) {
            return $next($request);
        }

        if ($request->routeIs(
            'logout',
            'profile.edit',
            'profile.update',
            'profile.two-factor.setup',
            'profile.two-factor.confirm',
            'profile.two-factor.recovery',
            'profile.two-factor.disable'
        )) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Please set a new password before continuing.');
        }

        return redirect()
            ->route('profile.edit')
            ->with('warning', 'Security: pehle naya password set karein, phir software use karein.');
    }
}
