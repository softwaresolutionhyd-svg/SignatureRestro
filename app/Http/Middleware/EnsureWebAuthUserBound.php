<?php

namespace App\Http\Middleware;

use App\Support\WebAuthSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Logs out if session user binding does not match authenticated user. */
class EnsureWebAuthUserBound
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            WebAuthSession::enforceBoundUser($request);

            if (! Auth::check()) {
                return redirect()
                    ->route('login')
                    ->withErrors(['login' => 'Session expire ho gayi. Dobara login karein.']);
            }
        }

        return $next($request);
    }
}
