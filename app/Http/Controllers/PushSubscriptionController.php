<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PushSubscriptionController extends Controller
{
    public function vapidPublicKey(WebPushService $push): JsonResponse
    {
        return response()->json([
            'publicKey' => $push->publicKey(),
            'configured' => $push->isConfigured(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! Schema::hasTable('push_subscriptions')) {
            return response()->json(['ok' => false, 'message' => 'Push table missing — run migrations.'], 503);
        }

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'string', 'max:32'],
        ]);

        $user = $request->user();
        PushSubscription::query()->updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id' => $user->id,
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aesgcm',
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        if (! Schema::hasTable('push_subscriptions')) {
            return response()->json(['ok' => true]);
        }

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
        ]);

        PushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('endpoint', $data['endpoint'])
            ->delete();

        return response()->json(['ok' => true]);
    }
}
