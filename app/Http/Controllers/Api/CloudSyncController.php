<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Sync\CloudSyncService;
use App\Services\Sync\SyncTargetSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CloudSyncController extends Controller
{
    public function ping(CloudSyncService $sync, SyncTargetSchemaService $schema): JsonResponse
    {
        if ($sync->isCloudRole()) {
            $schema->ensureAll();
        }

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

    /** Hosting → cafe: export rows changed since cursor (credit book / sales). */
    public function pull(Request $request, CloudSyncService $sync): JsonResponse
    {
        $data = $request->validate([
            'since' => ['nullable', 'string', 'max:40'],
            'tables' => ['nullable', 'array', 'max:100'],
            'tables.*' => ['string', 'max:128'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $result = $sync->exportPullBatch(
            $data['since'] ?? null,
            $data['tables'] ?? null,
            (int) ($data['limit'] ?? 200)
        );

        return response()->json($result);
    }

    /** Hosting → cafe: many tables + per-table cursors in one request (fast). */
    public function pullMulti(Request $request, CloudSyncService $sync): JsonResponse
    {
        $data = $request->validate([
            'cursors' => ['required', 'array', 'min:1', 'max:120'],
            'cursors.*' => ['nullable', 'string', 'max:40'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $result = $sync->exportPullMulti(
            $data['cursors'],
            (int) ($data['limit'] ?? 400)
        );

        return response()->json($result);
    }

    /** Hosting → cafe: export specific rows by id (related POS orders for credit sales). */
    public function pullIds(Request $request, CloudSyncService $sync): JsonResponse
    {
        $data = $request->validate([
            'table' => ['required', 'string', 'max:128'],
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'min:1'],
            'by' => ['nullable', 'in:id,order_id'],
        ]);

        $result = $sync->exportRowsByIds($data['table'], $data['ids'], (string) ($data['by'] ?? 'id'));

        return response()->json($result);
    }
}
