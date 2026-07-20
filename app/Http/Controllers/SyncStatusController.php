<?php

namespace App\Http\Controllers;

use App\Services\Sync\CloudSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncStatusController extends Controller
{
    public function status(CloudSyncService $sync): JsonResponse
    {
        return response()->json($sync->status());
    }

    public function push(Request $request, CloudSyncService $sync): JsonResponse
    {
        if (! $sync->isLocalRole()) {
            return response()->json([
                'ok' => false,
                'message' => 'Push only runs on the local (offline) install.',
            ], 400);
        }

        $force = $request->boolean('force');
        // One-way by default: pull only when SYNC_PULL_ENABLED (and request/auto_pull says so).
        $pullEnabled = (bool) config('sync.pull_enabled', false);
        $withPull = $pullEnabled && ($request->has('pull') ? $request->boolean('pull') : (bool) config('sync.auto_pull', false));
        $result = $sync->syncBoth($force, false, $withPull);

        return response()->json([
            'ok' => $result['ok'] || (($result['pending'] ?? 0) === 0 && ($result['online'] ?? false)),
            'online' => $result['online'] ?? false,
            'pending' => $result['pending'] ?? 0,
            'pushed' => $result['pushed'] ?? 0,
            'pulled' => $result['pulled'] ?? 0,
            'message' => $result['message'] ?? '',
            'push' => $result['push'] ?? null,
            'pull' => $result['pull'] ?? null,
        ], ($result['ok'] ?? false) || ($result['online'] ?? false) ? 200 : 503);
    }

    public function pull(Request $request, CloudSyncService $sync): JsonResponse
    {
        if (! $sync->isLocalRole()) {
            return response()->json([
                'ok' => false,
                'message' => 'Pull only runs on the local (offline) install.',
            ], 400);
        }

        if (! config('sync.pull_enabled', false)) {
            return response()->json([
                'ok' => true,
                'pulled' => 0,
                'failed' => 0,
                'message' => 'Online → offline pull band hai. Sirf cafe → hosting sync chalta hai.',
                'online' => true,
            ]);
        }

        $result = $sync->pull($request->boolean('force'), $request->boolean('reset'));

        return response()->json($result, $result['ok'] ? 200 : 503);
    }
}
