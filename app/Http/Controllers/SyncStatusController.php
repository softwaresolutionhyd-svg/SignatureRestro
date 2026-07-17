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
        $push = $sync->push($force);

        $pull = ['ok' => true, 'pulled' => 0, 'failed' => 0, 'message' => 'Pull skipped.', 'online' => $sync->remoteReachable()];
        if (config('sync.auto_pull', true)) {
            $pull = $sync->pull($force);
        }

        $online = (bool) ($pull['online'] ?? $sync->remoteReachable());
        $pending = $push['pending'] ?? $sync->pendingCount();
        $httpOk = ($push['ok'] ?? false) || (($pull['pulled'] ?? 0) > 0) || (($pull['ok'] ?? false) && $pending === 0);

        return response()->json([
            'ok' => $httpOk,
            'online' => $online,
            'pending' => $pending,
            'pushed' => $push['pushed'] ?? 0,
            'pulled' => $pull['pulled'] ?? 0,
            'message' => trim(($push['message'] ?? '').' '.($pull['message'] ?? '')),
            'push' => $push,
            'pull' => $pull,
        ], $httpOk || $online ? 200 : 503);
    }

    public function pull(Request $request, CloudSyncService $sync): JsonResponse
    {
        if (! $sync->isLocalRole()) {
            return response()->json([
                'ok' => false,
                'message' => 'Pull only runs on the local (offline) install.',
            ], 400);
        }

        $result = $sync->pull($request->boolean('force'));

        return response()->json($result, $result['ok'] ? 200 : 503);
    }
}
