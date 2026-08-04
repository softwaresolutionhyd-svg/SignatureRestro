<?php

namespace App\Http\Controllers;

use App\Notifications\PosActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $management = $user->receivesManagementNotifications();

        $query = $user->notifications()->latest();
        if (! $management) {
            $query->where('type', PosActivity::class);
        }

        $notifications = $query
            ->limit(20)
            ->get()
            ->map(function ($n) {
                return [
                    'id' => $n->id,
                    'read_at' => $n->read_at,
                    'created_at' => optional($n->created_at)?->toIso8601String(),
                    'created_ago' => optional($n->created_at)?->diffForHumans(),
                    'data' => $n->data,
                ];
            });

        $unreadQuery = $user->unreadNotifications();
        if (! $management) {
            $unreadQuery->where('type', PosActivity::class);
        }

        return response()->json([
            'unread_count' => $unreadQuery->count(),
            'notifications' => $notifications,
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = $user->unreadNotifications();
        if (! $user->receivesManagementNotifications()) {
            $query->where('type', PosActivity::class);
        }
        $query->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->whereKey($id)->first();
        if ($notification) {
            if (! $user->receivesManagementNotifications()
                && $notification->type !== PosActivity::class) {
                return response()->json(['ok' => true]);
            }
            $notification->markAsRead();
        }

        return response()->json(['ok' => true]);
    }
}
