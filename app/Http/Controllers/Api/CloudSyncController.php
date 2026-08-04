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
        // Schema ensure is expensive — do it rarely, not on every status ping.
        if ($sync->isCloudRole()) {
            try {
                $done = \Illuminate\Support\Facades\Cache::get('sync:cloud_schema_ping');
                if (! $done) {
                    $schema->ensureAll();
                    \Illuminate\Support\Facades\Cache::put('sync:cloud_schema_ping', true, now()->addHours(6));
                }
            } catch (\Throwable) {
                // Reachability must stay cheap even if cache/schema fails.
            }
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

    /**
     * Cafe → hosting: delete remote rows whose ids are not in keep_ids
     * so hosting reports match the cafe DB (local is source of truth).
     */
    public function mirror(Request $request, CloudSyncService $sync): JsonResponse
    {
        $data = $request->validate([
            'table' => ['required', 'string', 'max:128'],
            'keep_ids' => ['present', 'array', 'max:20000'],
            'keep_ids.*' => ['integer', 'min:1'],
        ]);

        $result = $sync->mirrorTableKeepIds($data['table'], $data['keep_ids']);

        return response()->json($result, ($result['ok'] ?? false) ? 200 : 422);
    }

    /**
     * Cafe → hosting: fan-out Stair activity as Web Push to online-installed PWAs.
     */
    public function pushNotify(Request $request, \App\Services\WebPushService $webPush): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['nullable', 'integer', 'min:1'],
            'payload' => ['required', 'array'],
            'payload.title' => ['required', 'string', 'max:120'],
            'payload.message' => ['nullable', 'string', 'max:500'],
            'payload.url' => ['nullable', 'string', 'max:500'],
            'payload.action' => ['nullable', 'string', 'max:80'],
            'payload.level' => ['nullable', 'string', 'max:20'],
            'payload.icon' => ['nullable', 'string', 'max:80'],
            'payload.order_id' => ['nullable', 'integer'],
            'payload.order_no' => ['nullable', 'string', 'max:80'],
        ]);

        $sent = $webPush->sendToCompany(
            isset($data['company_id']) ? (int) $data['company_id'] : null,
            $data['payload']
        );

        return response()->json(['ok' => true, 'sent' => $sent]);
    }
}
