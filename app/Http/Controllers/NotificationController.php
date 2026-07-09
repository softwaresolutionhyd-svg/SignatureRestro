<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->limit(15)
            ->get()
            ->map(function ($n) {
                return [
                    'id' => $n->id,
                    'read_at' => $n->read_at,
                    'created_at' => $n->created_at,
                    'data' => $n->data,
                ];
            });

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $notifications,
        ]);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['ok' => true]);
    }
}
