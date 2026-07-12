<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class DeployHookController extends Controller
{
    /** POST after FTP deploy — runs pending migrations (requires DEPLOY_KEY in .env). */
    public function migrate(Request $request): JsonResponse
    {
        $key = (string) config('app.deploy_key', '');
        if ($key === '') {
            return response()->json(['ok' => false, 'error' => 'DEPLOY_KEY not configured'], 503);
        }

        $given = (string) $request->header('X-Deploy-Key', '');
        if (! hash_equals($key, $given)) {
            abort(403);
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
            $migrateOut = trim(Artisan::output());
            try {
                Artisan::call('optimize:clear');
            } catch (\Throwable) {
                // non-fatal
            }

            return response()->json([
                'ok' => true,
                'migrate' => $migrateOut,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
