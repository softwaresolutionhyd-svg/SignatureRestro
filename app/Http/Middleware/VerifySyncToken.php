<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySyncToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('sync.enabled') || config('sync.role') !== 'cloud') {
            return response()->json(['ok' => false, 'message' => 'Cloud sync is not enabled on this server.'], 403);
        }

        $expected = (string) config('sync.token');
        if ($expected === '') {
            return response()->json(['ok' => false, 'message' => 'SYNC_TOKEN is not configured.'], 500);
        }

        $token = (string) $request->bearerToken();
        if ($token === '' || ! hash_equals($expected, $token)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
