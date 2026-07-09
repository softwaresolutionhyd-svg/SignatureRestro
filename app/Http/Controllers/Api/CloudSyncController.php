<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Sync\CloudSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CloudSyncController extends Controller
{
    public function ping(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'role' => config('sync.role'),
            'time' => now()->toIso8601String(),
        ]);
    }

    public function push(Request $request, CloudSyncService $sync): JsonResponse
    {
        $data = $request->validate([
            'changes' => ['required', 'array', 'min:1'],
            'changes.*.id' => ['nullable', 'integer'],
            'changes.*.table' => ['required', 'string', 'max:128'],
            'changes.*.key' => ['required', 'string', 'max:64'],
            'changes.*.action' => ['required', 'in:upsert,delete'],
            'changes.*.payload' => ['nullable', 'array'],
        ]);

        $result = $sync->applyIncoming($data['changes']);

        return response()->json([
            'ok' => count($result['failed']) === 0,
            'applied' => $result['applied'],
            'failed' => $result['failed'],
        ]);
    }
}
