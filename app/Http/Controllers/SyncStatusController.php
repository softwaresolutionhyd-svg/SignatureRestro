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

        $result = $sync->push();

        return response()->json($result, $result['ok'] ? 200 : 503);
    }
}
